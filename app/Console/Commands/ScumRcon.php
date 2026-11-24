<?php

namespace App\Console\Commands;

use App\Services\Scum\AutomationService;
use App\Services\Scum\ChatCommandProcessor;
use App\Services\Scum\DaemonService;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumJobService;
use App\Services\Scum\ServerService;
use App\Services\Scum\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RCON-демон — единственный владелец RCON-соединения SCUM-сервера.
 * Исполняет задачи из scum_jobs (чат-команды, события автоматизации,
 * периодические тики плагинов, команды веб-консоли) и крутит refreshState().
 */
class ScumRcon extends Command
{
    protected $signature = 'scum:rcon {--poll=150 : интервал опроса очереди в мс} {--once : выполнить один цикл и выйти}';

    protected $description = 'RCON-демон: исполнение очереди scum_jobs (единственный владелец RCON-сокета)';

    public function handle(
        RconService $rcon,
        SettingsService $settings,
        ScumJobService $jobs,
        ChatCommandProcessor $chatProcessor,
        AutomationService $automation,
        PluginService $plugins,
        DaemonService $daemons,
        ServerService $servers,
    ): int {
        $config = $settings->getAppConfig();
        $pollMs = max(50, (int) $this->option('poll'));

        // Демон стартует сразу с конфигом активного сервера: не ждём, пока
        // пользователь зайдёт в панель (раньше это делал ServersController).
        $config = $servers->ensureActiveConfigInSettings();

        // Подключение RCON (единственный сокет)
        $rconCfg = $config['rcon'] ?? [];
        if (! empty($rconCfg['enabled']) && ! empty($rconCfg['host'])) {
            $this->info('[RCON] Подключение к '.$rconCfg['host'].':'.$rconCfg['port']);
            $rcon->setAutoReconnect(true);
            $result = $rcon->connect($rconCfg['host'], (int) $rconCfg['port'], $rconCfg['password'] ?? '');
            if ($result['success']) {
                $this->info('[RCON] Подключено');
            } else {
                $this->warn('[RCON] Ошибка: '.($result['error'] ?? 'unknown'));
            }
        } else {
            $this->warn('[RCON] RCON отключён в конфигурации');
        }

        if ($this->option('once')) {
            $this->processJobs($jobs, $chatProcessor, $automation, $plugins, $rcon);

            return self::SUCCESS;
        }

        $this->info("[RCON] Демон запущен, интервал опроса {$pollMs}мс. Ctrl+C для остановки.");
        $lastOnlineWarm = 0;
        $running = true;
        $iteration = 0;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
            });
        }

        while ($running) {
            $iteration++;
            if ($iteration % 100 === 0) {
                gc_collect_cycles();
                DB::connection()->flushQueryLog();
            }
            try {
                $daemons->heartbeat(DaemonService::RCON);

                // Авто-подключение: если RCON лежал при старте (сервер был недоступен)
                // или соединение оборвалось — переподключаемся по экспоненциальному backoff,
                // как у FTP: 5→10→20→30→60→...→300 сек.
                if (! $rcon->isAlive()) {
                    if (time() < $this->nextReconnectAt) {
                        // Ещё не время — пропускаем попытку
                    } else {
                        $wasConnected = $this->lastRconConnected;
                        $this->autoReconnect($rcon, $settings);
                        if ($rcon->isAlive()) {
                            $this->backoffStep = 0;
                            $this->nextReconnectAt = 0;
                        } else {
                            $delay = self::BACKOFF_SEQUENCE[min($this->backoffStep, count(self::BACKOFF_SEQUENCE) - 1)];
                            $this->backoffStep++;
                            $this->nextReconnectAt = time() + $delay;
                            Log::channel('scum')->warning("[RCON] Reconnect failed, next attempt in {$delay}s (step {$this->backoffStep})");
                        }
                    }
                }

                $rcon->refreshState();
                $this->processJobs($jobs, $chatProcessor, $automation, $plugins, $rcon);
                $this->lastRconConnected = $rcon->isAlive();

                // Кэш онлайн-игроков: демон обновляет каждые 3 с, веб читает кэш
                // и не коннектится к RCON (реконнект спорит с демоном за слот).
                // Частота 3 с не подходит планировщику (минимум 1 мин), поэтому остаётся здесь.
                if (time() - $lastOnlineWarm >= 3) {
                    $lastOnlineWarm = time();
                    try {
                        $plugins->warmOnlinePlayers();
                    } catch (\Throwable $e) {
                        Log::channel('scum')->warning('[rcon] online warm: '.$e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if ($msg !== $this->lastLoggedError) {
                    Log::channel('scum')->error('[rcon] '.$msg);
                    $this->lastLoggedError = $msg;
                } elseif (($this->lastLogAt ?? 0) < time() - 60) {
                    Log::channel('scum')->warning('[rcon] (same error repeating, last: '.$msg.')');
                    $this->lastLogAt = time();
                }
            }
            usleep(max(10000, $pollMs * 1000));
        }

        $this->info('[RCON] Получен сигнал завершения. Отключение RCON...');
        $rcon->disconnect();

        return self::SUCCESS;
    }

    /** @var string|null последняя залогированная ошибка (для throttle) */
    private ?string $lastLoggedError = null;

    /** @var int|null время последнего лога повторяющейся ошибки */
    private ?int $lastLogAt = null;

    /** Следим за состоянием RCON, чтобы ловить момент переподключения */
    private bool $lastRconConnected = false;

    /** Exponential backoff при реконнекте RCON (сек), как у FTP */
    private const BACKOFF_SEQUENCE = [5, 10, 20, 30, 60, 120, 180, 240, 300, 300];

    private int $backoffStep = 0;

    private int $nextReconnectAt = 0;

    private function autoReconnect(RconService $rcon, SettingsService $settings): void
    {
        $cfg = $settings->getAppConfig()['rcon'] ?? [];
        if (empty($cfg['enabled']) || empty($cfg['host'])) {
            return;
        }
        $rcon->setAutoReconnect(true);
        $result = $rcon->connect($cfg['host'], (int) $cfg['port'], $cfg['password'] ?? '');
        if ($result['success']) {
            $this->info('[RCON] Переподключено: '.$cfg['host'].':'.$cfg['port']);
        } else {
            $this->warn('[RCON] Переподключение не удалось: '.($result['error'] ?? 'unknown'));
        }
    }

    private function processJobs(ScumJobService $jobs, ChatCommandProcessor $chatProcessor, AutomationService $automation, PluginService $plugins, RconService $rcon): void
    {
        // Ограничиваем 50 задач за цикл, чтобы не упасть в бесконечный цикл при переполнении
        for ($i = 0; $i < 50; $i++) {
            $job = $jobs->claim();
            if (! $job) {
                break;
            }

            try {
                $payload = $job['payload'];
                if ($job['type'] === 'chat_command') {
                    if (empty($payload['message']) || ! str_starts_with(trim($payload['message']), '!')) {
                        $jobs->done($job['id']);

                        continue;
                    }
                    // Если RCON мёртв — возвращаем задачу в очередь, чтобы
                    // обработать после переподключения. Не сжигаем её.
                    if (! $rcon->isAlive()) {
                        $jobs->requeue($job['id']);
                        usleep(50000);
                        break;
                    }
                    $chatProcessor->process(
                        $payload['steamId'] ?? '',
                        $payload['playerName'] ?? '',
                        $payload['message']
                    );
                } elseif ($job['type'] === 'event') {
                    $automation->processEvent($payload['event'] ?? '', $payload['data'] ?? []);
                } elseif ($job['type'] === 'tick') {
                    // Периодический тик из планировщика (schedule:work ставит задачу,
                    // демон исполняет — только он владеет RCON-сокетом).
                    foreach ($payload['tasks'] ?? [] as $task) {
                        try {
                            if ($task === 'schedules') {
                                $automation->processSchedules();
                            } elseif ($task === 'airdrop') {
                                $plugins->autoDropTick();
                            } elseif ($task === 'hourly') {
                                $plugins->hourlyRewardsTick();
                            } elseif ($task === 'top') {
                                $plugins->topRewardsTick();
                            }
                        } catch (\Throwable $e) {
                            Log::channel('scum')->error("[rcon] tick {$task}: ".$e->getMessage());
                        }
                    }
                } elseif ($job['type'] === 'rcon_command') {
                    // Команда из веб-консоли: исполняется здесь, т.к. только демон
                    // держит единственное RCON-соединение. Результат сохраняем в job.
                    $result = $rcon->sendCommand((string) ($payload['command'] ?? ''));
                    $jobs->respond(
                        $job['id'],
                        $result['success'] ? ($result['response'] ?? '') : ($result['error'] ?? '')
                    );

                    continue; // respond уже пометил done
                } else {
                    $jobs->done($job['id']);

                    continue;
                }
                $jobs->done($job['id']);
            } catch (\Throwable $e) {
                Log::channel('scum')->error('[rcon] job #'.$job['id'].' '.$job['type'].': '.$e->getMessage());
                $jobs->fail($job['id'], $e->getMessage());
            }
        }
    }
}
