<?php

namespace App\Console\Commands;

use App\Services\Scum\DaemonService;
use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\ServerService;
use App\Services\Scum\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScumWatch extends Command
{
    protected $signature = 'scum:watch {--poll=500 : интервал опроса FTP в мс} {--once : выполнить один цикл и выйти}';

    protected $description = 'FTP-демон: чтение логов SCUM через FTP и постановка задач в очередь для RCON-демона';

    public function handle(FtpLogWatcherService $watcher, SettingsService $settings, DaemonService $daemons, ServerService $servers): int
    {
        // Демон стартует сразу с конфигом активного сервера: не ждём, пока
        // пользователь зайдёт в панель (раньше это делал ServersController).
        $servers->ensureActiveConfigInSettings();
        $watcher->reloadConfig();

        $config = $settings->getAppConfig();
        $pollMs = max(100, (int) $this->option('poll'));

        // Только FTP watcher. RCON принадлежит процессу scum:rcon (единственное соединение).
        // Чат-команды и события ставятся в scum_jobs и исполняются там.
        $hostFtp = $config['hostFtp'] ?? [];
        if (! empty($hostFtp['enabled']) && ! empty($hostFtp['host'])) {
            $this->info('[FTP] Подключение к '.$hostFtp['host'].':'.($hostFtp['port'] ?? 21));
            if ($watcher->start()) {
                $this->info('[FTP] Подключено, начат мониторинг логов');
            } else {
                $this->warn('[FTP] Ошибка: '.$watcher->status()['lastError']);
            }
        } else {
            $this->warn('[FTP] FTP мониторинг отключён в конфигурации');
        }

        // Периодическая синхронизация SCUM.db в фоне — веб-страницы читают свежую
        // локальную копию без FTP в запросе. Дешёвая проверка mtime внутри syncScumDb
        // не качает файл, если серверная копия не изменилась.
        $scumDbSyncSec = max(10, (int) ($hostFtp['scumDbSyncSec'] ?? 60));
        $lastScumDbSync = 0;

        // Кэш сундуков для карты: демон подогревает его в фоне, чтобы веб-запрос
        // /map не качал chest_ownership*.log через FTP (каждая операция ~2.8с).
        $lastChestWarm = 0;

        if ($this->option('once')) {
            $watcher->poll();

            return self::SUCCESS;
        }

        $this->info("[WATCH] FTP-демон запущен, интервал опроса {$pollMs}мс, синк SCUM.db каждые {$scumDbSyncSec}с. Ctrl+C для остановки.");
        $lastConfigReload = 0;
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
                // Конфиг активного сервера перечитываем каждые 5 секунд — если
                // панель сохранила новый сервер уже после старта демона, подхватим
                // его без рестарта. reloadConfig() дешёвый (файловый кэш).
                if (time() - $lastConfigReload >= 5) {
                    $lastConfigReload = time();
                    $servers->ensureActiveConfigInSettings();
                    $watcher->reloadConfig();
                }

                $watcher->poll();
                $watcher->refreshState();
                if (! empty($hostFtp['enabled']) && ! empty($hostFtp['host'])
                    && time() - $lastScumDbSync >= $scumDbSyncSec) {
                    $lastScumDbSync = time();
                    try {
                        $result = $watcher->syncScumDb();
                        if (empty($result['ok']) && empty($result['upToDate']) && empty($result['busy'])) {
                            Log::channel('scum')->warning('[Scumdb] daemon sync failed: '.($result['error'] ?? 'unknown error'));
                        }
                    } catch (\Throwable $e) {
                        Log::channel('scum')->warning('[Scumdb] daemon sync error: '.$e->getMessage());
                    }
                }
                // Подогрев кэша сундуков (карта) — раз в 30 секунд в фоне.
                if (! empty($hostFtp['enabled']) && ! empty($hostFtp['host'])
                    && time() - $lastChestWarm >= 30) {
                    $lastChestWarm = time();
                    try {
                        $watcher->getChestData();
                    } catch (\Throwable $e) {
                        Log::channel('scum')->warning('[Scumdb] chest cache warm error: '.$e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('scum')->error('[watch] '.$e->getMessage());
            }
            $daemons->heartbeat(DaemonService::WATCH);
            usleep(max(10000, $pollMs * 1000));
        }

        $this->info('[WATCH] Получен сигнал завершения. Отключение watcher...');
        $watcher->disconnect();

        return self::SUCCESS;
    }
}
