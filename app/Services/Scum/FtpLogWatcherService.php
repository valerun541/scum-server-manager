<?php

namespace App\Services\Scum;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Чтение логов сервера через FTP и обработка событий — порт ftpLogWatcher.ts
 * Управляется через artisan command scum:watch (демон с poll-циклом).
 * Чат-команды и события не исполняются здесь напрямую, а ставятся в очередь
 * (scum_jobs) для RCON-демона scum:rcon — чтобы FTP-цикл не блокировался.
 */
class FtpLogWatcherService
{
    private const STATE_TTL = 300;

    private RemoteClient $remote;

    private array $config;

    private array $offsets = [];

    private array $lineCounts = [];

    private array $pending = [];

    private bool $running = false;

    private bool $initialized = false;

    /** Счётчик строк с последней чистки БД событий (чистим пакетами, а не на каждой строке) */
    private int $linesSinceCleanup = 0;

    private const CLEANUP_EVERY_LINES = 100;

    private const KEEP_EVENTS = 50000;

    /** Максимум хранимых смещений чат-файлов — защита от бесконечного роста карты */
    private const OFFSETS_MAX = 2000;

    /** Последний обнаруженный файл для каждой категории логов: prefix => ['path'=>, 'size'=>, 'checkedAt'=>] */
    private array $lastLogFiles = [];

    /** Категории логов SCUM и их префиксы имён (админ/чат/вход/транспорт и др.) */
    private const LOG_CATEGORIES = ['chat', 'admin', 'login', 'vehicle', 'chest_ownership', 'economy', 'famepoints', 'gameplay', 'kill', 'quests', 'sentry', 'loot', 'violations'];

    /**
     * Кэш последнего листинга каталога логов (reuse между checkLogs и chest warm).
     * Полный LIST — редкая и дорогая операция (~2.8с), поэтому делимся результатом
     * между всеми потребителями в течение короткого окна.
     */
    private ?array $listingCache = null;

    private int $listingCacheAt = 0;

    /** Последний вызов checkScumLog (throttle — не чаще 1с на SIZE round-trip) */
    private int $scumLogCheckedAt = 0;

    /**
     * Частота опроса размера файла по категориям (сек). Чат/админ важны для
     * скорости ответа команд; экономика/сундуки — нет. Снижает число SIZE
     * round-trip: раньше все 11 категорий проверялись минимум раз в секунду.
     */
    private const CATEGORY_MIN_CHECK_SEC = [
        'chat' => 1,
        'admin' => 1,
        'login' => 2,
        'vehicle' => 2,
        'chest_ownership' => 10,
        'economy' => 10,
        'famepoints' => 10,
        'gameplay' => 10,
        'kill' => 10,
        'quests' => 10,
        'sentry' => 10,
        'loot' => 10,
        'violations' => 10,
    ];

    /** Ключ хранения офсета SCUM.log в ftp_offsets.json (отдельно от chat-файлов) */
    private const SCUM_LOG_KEY = '__scumlog__';

    /**
     * Exponential backoff при реконнекте FTP (сек): 5 → 10 → 20 → 30 → 60 → ...
     * После серии неудач интервал доходит до 300с (5 минут), чтобы не долбить
     * лежащий FTP-сервер каждые 30с. ВРЕМЕННО — вернуть к [5, 10, 20, 30, 30],
     * когда FTP после обновления игрового сервера станет стабильно доступен.
     */
    private const BACKOFF_SEQUENCE = [5, 10, 20, 30, 60, 120, 180, 240, 300, 300];

    private int $backoffStep = 0;

    private int $nextReconnectAt = 0;

    /**
     * True, когда мы знаем, что FTP недоступен (backoff ещё активен).
     * Используется как глобальный предохранитель: пока окно не истекло,
     * не создаём соединений к зависшему серверу (poll, syncScumDb, chest).
     */
    private function ftpBackoffActive(): bool
    {
        return ! $this->remote->isConnected() && time() < $this->nextReconnectAt;
    }

    /**
     * Отметить неуспешный коннект — включить следующий шаг backoff.
     * Вызывается из всех точек, где коннект реально провалился, чтобы
     * остальные циклы (sync/chest/web) тоже не дёргали FTP до истечения окна.
     */
    private function markFtpFailure(): void
    {
        $delay = self::BACKOFF_SEQUENCE[min($this->backoffStep, count(self::BACKOFF_SEQUENCE) - 1)];
        $this->backoffStep++;
        $this->nextReconnectAt = time() + $delay;
        Log::channel('scum')->warning("[FtpLogWatcher] FTP недоступен, повтор через {$delay}s (step {$this->backoffStep})");
    }

    public function __construct(
        private SettingsService $settings,
        private LolkaService $lolka,
        private RatingService $rating,
        private ScumDbService $scumDb,
        private ScumJobService $jobs,
    ) {
        $this->remote = new RemoteClient;
        $this->config = $settings->getAppConfig()['hostFtp'] ?? [];
        $this->loadOffsets();
    }

    public function reloadConfig(): void
    {
        $this->config = $this->settings->getAppConfig()['hostFtp'] ?? [];
    }

    /**
     * Подключение по сохранённому конфигу (используется из веб-запросов).
     */
    public function connect(): array
    {
        if (empty($this->config['host'])) {
            $this->clearState();

            return ['ok' => false, 'error' => 'FTP не настроен — добавьте сервер или укажите FTP адрес в настройках'];
        }
        $ok = $this->remote->connect($this->config);
        if ($ok) {
            $this->running = true;
            $this->writeState([
                'connected' => true,
                'host' => $this->config['host'],
                'port' => (int) ($this->config['port'] ?? 21),
                'protocol' => $this->config['protocol'] ?? 'ftp',
                'at' => time(),
            ]);
        } else {
            $this->clearState();
            $this->writeFailureState($this->remote->lastError());
        }

        return ['ok' => $ok, 'error' => $ok ? null : $this->remote->lastError()];
    }

    public function start(): bool
    {
        $this->running = true;
        if (empty($this->config['host'])) {
            return false;
        }

        return $this->remote->connect($this->config);
    }

    public function isRunning(): bool
    {
        return $this->running && $this->remote->isConnected();
    }

    public function status(): array
    {
        $cfg = $this->settings->getAppConfig()['hostFtp'] ?? [];
        $daemon = app(DaemonService::class)->status(DaemonService::WATCH);
        $connected = false;
        $error = $this->remote->lastError();
        // Статус строго зависит от демона: без запущенного демона FTP Watcher не работает
        if ($daemon['running']) {
            $connected = $this->remote->isConnected();
            $state = $this->readState();
            if (! $connected && $state && ! empty($state['connected']) && (time() - ($state['at'] ?? 0)) < (int) config('panel.ftp.state_ttl', self::STATE_TTL)) {
                $connected = true;
            } elseif (! $connected && empty($state['error']) && $daemon['running']) {
                // Если демон активен, сокет удерживается и ошибок нет
                $connected = true;
            }
            if (! empty($state['error'])) {
                $error = $state['error'];
                $connected = false;
            }
        }

        return [
            'running' => $connected,
            'connected' => $connected,
            'daemon' => $daemon,
            'config' => [
                'host' => $cfg['host'] ?? '',
                'port' => $cfg['port'] ?? 21,
                'username' => $cfg['username'] ?? '',
                'logPath' => $cfg['logPath'] ?? 'SCUM/Saved/SaveFiles/Logs',
                'pollIntervalMs' => $cfg['pollIntervalMs'] ?? (int) config('panel.ftp.poll_interval_ms', 2000),
                'protocol' => $cfg['protocol'] ?? 'ftp',
            ],
            'lastError' => $error,
        ];
    }

    public function disconnect(): void
    {
        $this->running = false;
        $this->remote->disconnect();
        $this->saveOffsets();
        $this->offsets = [];
        $this->lineCounts = [];
        $this->pending = [];
        $this->initialized = false;
        $this->lastLogFiles = [];
        $this->linesSinceCleanup = 0;
        $this->clearState();
    }

    public function __destruct()
    {
        if ($this->running) {
            $this->disconnect();
        }
    }

    /**
     * Один цикл опроса — вызывается демоном в цикле.
     */
    public function poll(): void
    {
        if (empty($this->config['host'])) {
            return;
        }
        if (! $this->remote->isConnected()) {
            // Exponential backoff: не долбить FTP-сервер при каждом poll (250-500мс).
            if (time() < $this->nextReconnectAt) {
                return;
            }
            $ok = $this->remote->connect($this->config);
            if (! $ok) {
                $this->clearState();
                $this->writeFailureState($this->remote->lastError());
                $this->markFtpFailure();

                return;
            }
            $this->backoffStep = 0;
            $this->nextReconnectAt = 0;
            $this->writeState([
                'connected' => true,
                'host' => $this->config['host'],
                'port' => (int) ($this->config['port'] ?? 21),
                'protocol' => $this->config['protocol'] ?? 'ftp',
                'at' => time(),
            ]);
            $this->initState(true);
        } elseif (! $this->initialized) {
            // Первый опрос после connect() (ScumWatch::start) — инициализируем смещения,
            // чтобы не перечитывать старые строки.
            $this->initState(false);
        }
        $logPath = $this->config['logPath'] ?? 'SCUM/Saved/SaveFiles/Logs';
        try {
            $this->checkLogs($logPath);
            $this->checkScumLog();
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[FtpLogWatcher] Poll error: '.$e->getMessage());
            try {
                $this->remote->disconnect();
            } catch (\Throwable $e2) {
            }
            $this->clearState();
            $this->writeFailureState($e->getMessage());
            $this->markFtpFailure();
        }
    }

    /**
     * Проверка всех лог-категорий (chat/admin/login/vehicle). Быстрый путь:
     * кэшируем последний файл каждой категории и опрашиваем только его размер
     * (1 round-trip на файл) вместо полного листинга каталога.
     * Полный листинг — только если кэш устарел или файл исчез.
     */
    private function checkLogs(string $logPath): void
    {
        $now = time();
        // Интервал полного листинга каталога — ловит ротацию (новый файл после рестарта).
        // 120с вместо 30с: LIST — дорогая операция (~2.8с), а ротация логов редка;
        // исчезновение файла дополнительно триггерит листинг через fileSize()==-1.
        $rotateCheckSec = max(5, (int) ($this->config['rotationCheckSec'] ?? 30));

        // Листинг по расписанию (кэш устарел) или каталог ещё не смотрели вообще.
        // Пустой каталог (обрыв FTP / сервер перезапущен) тоже кэшируем с таймстампом,
        // чтобы не долбить LIST каждые 500мс пока логи не появились.
        $needListing = $this->listingCache === null
            || ($now - $this->listingCacheAt) >= $rotateCheckSec
            || ! empty($this->lastLogFiles['_needListing']);
        $this->lastLogFiles['_needListing'] = false;

        if ($needListing) {
            $this->listingCacheAt = $now;
            $files = $this->remote->listFiles($logPath);
            $this->listingCache = $files;
            foreach (self::LOG_CATEGORIES as $cat) {
                $catFiles = array_values(array_filter($files, fn ($f) => str_starts_with(strtolower((string) $f['name']), $cat)
                    && str_ends_with(strtolower((string) $f['name']), '.log')));
                if (empty($catFiles)) {
                    unset($this->lastLogFiles[$cat]);

                    continue;
                }
                usort($catFiles, fn ($a, $b) => strcmp((string) $b['name'], (string) $a['name']));
                $latest = $catFiles[0];
                $remotePath = rtrim($logPath, '/').'/'.$latest['name'];

                // Активный файл сменился (ротация) — дочитываем хвост старого,
                // чтобы не потерять последние строки перед переключением.
                $prev = $this->lastLogFiles[$cat] ?? null;
                if ($prev !== null && ! empty($prev['path']) && $prev['path'] !== $remotePath) {
                    $oldOffset = $this->getOffset($prev['path']);
                    $oldSize = $this->remote->fileSize($prev['path']);
                    if ($oldSize > $oldOffset) {
                        try {
                            $this->readFromFtp($prev['path'], basename($prev['path']), $oldOffset, $oldSize);
                        } catch (\Throwable $e) {
                            Log::channel('scum')->error('[FtpLogWatcher] tail read error '.$prev['path'].': '.$e->getMessage());
                        }
                    }
                    // Сбросить pending для старого файла — если осталась неполная
                    // строка после чтения хвоста, она уже не будет дописана
                    // (файл больше не ротируется в старое имя).
                    unset($this->pending[basename($prev['path'])]);
                }

                $this->lastLogFiles[$cat] = ['path' => $remotePath, 'size' => (int) $latest['size'], 'checkedAt' => $now];

                // Новый файл: читаем с последнего сохранённого смещения (или с 0)
                $curOff = $this->getOffset($remotePath);
                if ((int) $latest['size'] > $curOff) {
                    $this->readFromFtp($remotePath, $latest['name'], $curOff, (int) $latest['size']);
                }
            }
            if ($this->lastLogFiles === []) {
                $this->lastLogFiles['_checkedAt'] = $now;
            }

            return;
        }

        // Обычный цикл: опрашиваем размер файла только для категорий, чей
        // интервал проверки истёк. Чат/админ — каждую секунду (команды бота),
        // остальные — реже, чтобы не долбить FTP при неактивной игре.
        foreach (self::LOG_CATEGORIES as $cat) {
            $cache = $this->lastLogFiles[$cat] ?? null;
            if ($cache === null || empty($cache['path'])) {
                continue;
            }

            $minCheckSec = (float) ($this->config['categoryMinCheckSec'][$cat] ?? self::CATEGORY_MIN_CHECK_SEC[$cat]);
            $checkedAt = (int) ($cache['checkedAt'] ?? 0);
            if (($now - $checkedAt) < $minCheckSec) {
                continue;
            }
            $this->lastLogFiles[$cat]['checkedAt'] = $now;

            $remotePath = $cache['path'];
            // Дешёвая проверка размера — один SIZE round-trip вместо листинга каталога
            $size = $this->remote->fileSize($remotePath);
            if ($size < 0) {
                // Файл пропал (ротация?) — сбросить кэш и запросить полный листинг
                // в следующем цикле, чтобы быстро подхватить новый файл категории.
                unset($this->lastLogFiles[$cat]);
                $this->lastLogFiles['_needListing'] = true;

                continue;
            }
            $this->lastLogFiles[$cat]['size'] = $size;

            $lastOffset = $this->getOffset($remotePath);
            if ($size < $lastOffset) {
                $this->setOffset($remotePath, 0);
                $this->saveOffsets();
                $lastOffset = 0;
                Log::channel('scum')->info("[FtpLogWatcher] {$remotePath} shrunk, resetting offset to 0");
            }
            if ($size > $lastOffset) {
                $this->readFromFtp($remotePath, basename($remotePath), $lastOffset, $size);
            }
        }
    }

    /**
     * Проверка SCUM.log (SCUM/Saved/Logs/SCUM.log) — ловим события входа/выхода
     * и счётчик онлайна, которые SCUM пишет только в этот файл (порт
     * logWatcher.ts readScumLogTail/parseScumText). Читаем только новые байты
     * от сохранённого офсета, офсет храним в ftp_offsets.json отдельным ключом.
     */
    private function checkScumLog(): void
    {
        // Throttle: SCUM.log проверяем не чаще раза в секунду — вход/выход игроков
        // не требует мгновенной реакции, а каждый SIZE — отдельный round-trip к FTP.
        $minCheckSec = max(1, (int) ($this->config['scumLogCheckSec'] ?? 1));
        $now = time();
        if ($now - $this->scumLogCheckedAt < $minCheckSec) {
            return;
        }
        $this->scumLogCheckedAt = $now;

        $scumLogPath = $this->config['scumLogPath'] ?? 'SCUM/Saved/Logs/SCUM.log';
        try {
            // Один SIZE round-trip: fileSize() возвращает -1 и для отсутствующего файла,
            // и при ошибке — этого достаточно вместо отдельного fileExists().
            $size = $this->remote->fileSize($scumLogPath);
            if ($size < 0) {
                return;
            }

            $lastOffset = (int) ($this->offsets[self::SCUM_LOG_KEY] ?? 0);

            // Файл урезан/пересоздан (рестарт сервера) — начинаем с начала.
            if ($size < $lastOffset) {
                $lastOffset = 0;
            }
            if ($size <= $lastOffset) {
                return;
            }

            $readSize = $size - $lastOffset;
            // Не читаем более 1 МБ за раз — на случай очень большого прироста.
            if ($readSize > 1048576) {
                $lastOffset = $size - 1048576;
                $readSize = 1048576;
            }
            $buffer = $this->remote->downloadBuffer($scumLogPath, $lastOffset);
            // Некоторые FTP-серверы игнорируют REST и отдают весь файл — срезаем.
            if ($lastOffset > 0 && $size > 0 && strlen($buffer) >= $size) {
                $this->remote->setNoRest(true);
                $buffer = substr($buffer, $lastOffset);
            }
            $this->offsets[self::SCUM_LOG_KEY] = $size;
            $this->saveOffsets();
            if ($buffer === '') {
                return;
            }

            $this->parseScumText($this->remote->decodeBuffer($buffer));
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[FtpLogWatcher] SCUM.log read error: '.$e->getMessage());
        }
    }

    private function parseScumText(string $text): void
    {
        foreach (preg_split('/\r?\n/', $text) as $rawLine) {
            $line = str_replace("\u{FEFF}", '', trim($rawLine));
            if ($line === '') {
                continue;
            }

            if (preg_match('/HandlePossessedBy:\s*(\d+),\s*(\d+),\s*(\S+)/', $line, $m)) {
                $playerName = trim($m[3]);
                $this->handleScumJoin($playerName, '', $line);

                continue;
            }
            if (preg_match("/LogSCUM:.+'(\d+):([^(]+)\((\d+)\)'.+logged in/", $line, $m)) {
                $this->handleScumJoin(trim($m[2]), $m[1], $line);

                continue;
            }
            if (preg_match("/LogSCUM:.+'(\d+):([^(]+)\(\d+\)'.+logged out/", $line, $m)) {
                $this->handleScumLeave(trim($m[2]), $m[1], $line);

                continue;
            }
            if (preg_match('/Prisoner logging out:\s*([^(]+)\s*\(\d+\)/', $line, $m)) {
                $this->handleScumLeave(trim($m[1]), '', $line);

                continue;
            }
            if (preg_match('/Global Stats:.*?P:\s*(\d+)/', $line, $m)) {
                $this->addEvent('online', "Онлайн: {$m[1]}");
            }
        }
    }

    private function handleScumJoin(string $playerName, string $steamId, string $line): void
    {
        if ($playerName === '' && $steamId === '') {
            return;
        }
        $this->lolka->sendLoginEvent($playerName, $steamId, 'join');
        $this->addEvent('login', "{$playerName} connected");
        $this->rating->playerConnected($steamId, $playerName);
        $this->jobs->enqueue('event', ['event' => 'player_join', 'data' => ['playerName' => $playerName, 'steamId' => $steamId, 'line' => $line]]);
    }

    private function handleScumLeave(string $playerName, string $steamId, string $line): void
    {
        if ($playerName === '' && $steamId === '') {
            return;
        }
        $this->lolka->sendLoginEvent($playerName, $steamId, 'leave');
        $this->addEvent('login', "{$playerName} disconnected");
        if ($steamId !== '') {
            $this->rating->playerDisconnected($steamId);
        }
        $this->jobs->enqueue('event', ['event' => 'player_leave', 'data' => ['playerName' => $playerName, 'steamId' => $steamId, 'line' => $line]]);
    }

    private function initState(bool $reconnecting = false): void
    {
        $this->initialized = true;
        try {
            $logPath = $this->config['logPath'] ?? 'SCUM/Saved/SaveFiles/Logs';
            $files = $this->remote->listFiles($logPath);
            if ($files !== []) {
                $this->listingCache = $files;
                $this->listingCacheAt = time();
            }
            $this->lastLogFiles['_checkedAt'] = time();

            foreach (self::LOG_CATEGORIES as $cat) {
                $catFiles = array_values(array_filter($files, fn ($f) => str_starts_with(strtolower((string) $f['name']), $cat)
                    && str_ends_with(strtolower((string) $f['name']), '.log')));
                if (empty($catFiles)) {
                    continue;
                }

                // Сортируем хронологически от старых к новым (ascending),
                // чтобы исторические логи ложились в базу в правильном временном порядке.
                usort($catFiles, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

                $totalCatFiles = count($catFiles);
                for ($idx = 0; $idx < $totalCatFiles; $idx++) {
                    $f = $catFiles[$idx];
                    $remotePath = rtrim($logPath, '/').'/'.$f['name'];
                    $fileSize = (int) $f['size'];
                    $currentOffset = $this->getOffset($remotePath);
                    $isLatestFile = ($idx === $totalCatFiles - 1);

                    // Если в файле есть непрочитанные байты — дочитываем в тихом режиме backfill
                    // (пишет в базу log_events/chat_events, но не спамит вебхуками и не дублирует команды)
                    if ($fileSize > $currentOffset) {
                        try {
                            $this->readFromFtp($remotePath, $f['name'], $currentOffset, $fileSize, backfill: true);
                        } catch (\Throwable $e) {
                            Log::channel('scum')->error("[FtpLogWatcher] Backfill error {$remotePath}: ".$e->getMessage());
                        }
                    }

                    // Самый свежий файл категории регистрируем для живого поллинга новых строк
                    if ($isLatestFile) {
                        $this->lastLogFiles[$cat] = [
                            'path' => $remotePath,
                            'size' => (int) $this->getOffset($remotePath),
                            'checkedAt' => time(),
                        ];
                        $this->lineCounts[$f['name']] = 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[FtpLogWatcher] Init error: '.$e->getMessage());
        }
    }

    private function readFromFtp(string $remotePath, string $fileName, int $startOffset, int $fileSize, bool $backfill = false): void
    {
        try {
            // Качаем только новые байты (от последнего смещения) через FTP REST,
            // а не весь файл заново — это главное ускорение цикла обработки триггеров.
            $buffer = $this->remote->downloadBuffer($remotePath, $startOffset);
            if ($buffer === '') {
                return;
            }
            // Некоторые FTP-серверы отвечают на REST "350", но игнорируют его и
            // отдают весь файл. В этом случае срезаем прочитанное локально.
            if ($startOffset > 0 && $fileSize > 0 && strlen($buffer) > $startOffset && strlen($buffer) >= $fileSize) {
                // Сервер ответил "350", но отдал файл целиком — REST не работает.
                // Отключаем его, чтобы не тратить лишний round-trip на каждый опрос.
                $this->remote->setNoRest(true);
                $buffer = substr($buffer, $startOffset);
            }
            // Смещение всегда растёт от последнего обработанного байта (в т.ч. при
            // локальной обрезке, если сервер не поддерживает REST) — защита от дублей.
            $this->setOffset($remotePath, $startOffset + strlen($buffer));
            $this->saveOffsets();

            $data = $this->remote->decodeBuffer($buffer);
            // Частичная строка на хвосте (сервер мог дописать её позже) — держим до следующего опроса
            $pending = $this->pending[$fileName] ?? '';
            $chunk = $pending.$data;
            $this->pending[$fileName] = '';

            $lines = explode("\n", $chunk);
            if ($chunk !== '' && ! str_ends_with($chunk, "\n")) {
                // SCUM не пишет перевод строки после последней записи лога,
                // поэтому последний фрагмент откладываем только если он явно неполный
                // (оборван на середине). Полная строка обрабатывается сразу.
                $last = (string) array_pop($lines);
                if (! $this->looksLikeCompleteLine($last, $fileName)) {
                    $this->pending[$fileName] = $last;
                } else {
                    $lines[] = $last;
                }
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $this->processLine($fileName, $line, $backfill);
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[FtpLogWatcher] Error reading {$remotePath}: ".$e->getMessage());
        }
    }

    /**
     * SCUM не пишет перевод строки после последней записи лога, поэтому строка,
     * у которой нет завершающего \n, не обязательно неполная. Считаем строку
     * завершённой, если она заканчивается закрывающей кавычкой/скобкой (типично
     * для chat/login/admin логов), полностью совпадает с chat-шаблоном или
     * (для не-chat логов) начинается с метки времени SCUM.
     *
     * Для chat-файлов метка времени НЕ является признаком завершённости, потому
     * что формат чата:
     *   YYYY.MM.DD-HH.MM.SS: 'SteamId:Name(123)' 'Channel: Message'
     * и обрезанная строка (без закрывающей ') тоже начинается с timestamp,
     * но является неполной — её содержимое теряется при обработке.
     */
    private function looksLikeCompleteLine(string $line, string $fileName = ''): bool
    {
        if ($line === '') {
            return false;
        }
        $tail = substr($line, -1);
        if ($tail === "'" || $tail === '"' || $tail === ')') {
            return true;
        }
        if ((bool) preg_match("/'(\d+):([^(]+)\(\d+\)'[^']*'([^:]+):\s*(.+)/", $line)) {
            return true;
        }

        // Для chat-файлов: полная chat-строка ВСЕГДА заканчивается на '
        // (формат: 'SteamId:Name(123)' 'Channel: Message'). Если нет — строка обрезана,
        // даже если начинается с timestamp. Не считаем её завершённой.
        if (str_starts_with(strtolower(basename($fileName)), 'chat')) {
            return false;
        }

        // Для остальных логов (admin/login/vehicle и т.д.): строка, начинающаяся
        // с метки времени SCUM (YYYY.MM.DD-HH.MM.SS:), — полная запись.
        return (bool) preg_match('/^[\d]{4}\.[\d]{2}\.[\d]{2}-[\d]{2}\.[\d]{2}\.[\d]{2}:/', $line);
    }

    private function processLine(string $fileName, string $line, bool $skipActions = false): void
    {
        // Заголовок-версия в начале каждого ротированного файла — не событие.
        if (preg_match('/^[\d.]+-[\d.]+:\s*Game version:/i', $line)) {
            return;
        }
        $lowerName = strtolower($fileName);
        $event = ['type' => 'system', 'message' => $line, 'details' => null];

        $createdAt = now();
        if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})-(\d{2})\.(\d{2})\.(\d{2}):/', $line, $tm)) {
            try {
                $createdAt = Carbon::create(
                    (int) $tm[1], (int) $tm[2], (int) $tm[3],
                    (int) $tm[4], (int) $tm[5], (int) $tm[6]
                );
            } catch (\Throwable) {
            }
        }

        if (str_starts_with($lowerName, 'admin')) {
            $event['type'] = 'admin';
            if (! $skipActions) {
                $this->lolka->sendAdminLog($line);
                $this->jobs->enqueue('event', ['event' => 'admin_action', 'data' => ['line' => $line]]);
            }
            if (preg_match('/Kill:\s*(.+?)\s*\((\d+)\)\s*killed\s*(.+?)\s*\((\d+)\)/i', $line, $killMatch)) {
                if (! $skipActions) {
                    $this->jobs->enqueue('event', ['event' => 'player_kill', 'data' => [
                        'killerName' => trim($killMatch[1]), 'killerSteamId' => $killMatch[2],
                        'victimName' => trim($killMatch[3]), 'victimSteamId' => $killMatch[4], 'line' => $line,
                    ]]);
                }
            }
            if (preg_match('/Explosion.*base|Base.*raid|C4.*destroy|Dynamite.*destroy/i', $line)) {
                if (! $skipActions) {
                    $this->jobs->enqueue('event', ['event' => 'base_raid', 'data' => ['line' => $line]]);
                }
            }
        } elseif (str_starts_with($lowerName, 'chat')) {
            $event['type'] = 'chat';
            $matched = false;
            $steamId = null;
            $playerName = null;
            $channel = 'Global';
            $message = null;

            // 1. Стандартный SCUM: 'SteamId:Name(Session)' 'Channel: Message' или 'SteamId:Name' 'Channel: Message'
            if (preg_match("/'(\d+):([^(']+)(?:\(\d+\))?'[^']*'([^:]+):\s*(.+)/", $line, $m)) {
                $steamId = $m[1];
                $playerName = trim($m[2]);
                $channel = trim($m[3]);
                $message = rtrim(trim($m[4]), "'");
                $matched = true;
            } elseif (preg_match("/'([^:']+):([^(']+)(?:\(\d+\))?'[^']*'([^:]+):\s*(.+)/", $line, $m)) {
                // Сервер/Админ/Бот с префиксом: 'Server:Admin' 'Global: Message'
                $steamId = trim($m[1]);
                $playerName = trim($m[2]);
                $channel = trim($m[3]);
                $message = rtrim(trim($m[4]), "'");
                $matched = true;
            } elseif (preg_match("/'([^']+)'\s*'([^:]+):\s*(.+)/", $line, $m)) {
                // Имя без ID: 'PlayerName' 'Channel: Message'
                $steamId = null;
                $playerName = trim($m[1]);
                $channel = trim($m[2]);
                $message = rtrim(trim($m[3]), "'");
                $matched = true;
            } elseif (preg_match("/:\s*(?:'([^']+)'\s*)?(.+)/", $line, $m) && ! str_contains($line, 'Game version:')) {
                // Произвольное сообщение чата после timestamp
                $steamId = null;
                $playerName = ! empty($m[1]) ? trim($m[1]) : 'Игрок';
                $channel = 'Chat';
                $message = trim($m[2]);
                $matched = true;
            }

            if ($matched && ! empty($message)) {
                $event['details'] = ['steamId' => $steamId, 'playerName' => $playerName, 'channel' => $channel, 'message' => $message];
                if (! $skipActions && ! str_starts_with(trim($message), '!')) {
                    $this->lolka->sendChatMessage($playerName, $message);
                }
                DB::table('chat_events')->insert([
                    'steam_id' => $steamId, 'player_name' => $playerName, 'channel' => $channel,
                    'message' => $message, 'created_at' => $createdAt, 'updated_at' => $createdAt,
                ]);
                if (! $skipActions && str_starts_with(trim($message), '!')) {
                    $this->jobs->enqueue('chat_command', [
                        'steamId' => $steamId, 'playerName' => $playerName, 'message' => $message,
                    ], priority: 5);
                }
            }
        } elseif (str_starts_with($lowerName, 'login')) {
            $event['type'] = 'login';
            if (preg_match('/LoginComm: Login: (.+?)\((\d+)\)/', $line, $m)) {
                $playerName = trim($m[1]);
                $steamId = $m[2];
                if (! $skipActions) {
                    $this->lolka->sendLoginEvent($playerName, $steamId, 'join');
                    $this->rating->playerConnected($steamId, $playerName);
                    $this->jobs->enqueue('event', ['event' => 'player_join', 'data' => ['playerName' => $playerName, 'steamId' => $steamId, 'line' => $line]]);
                }
            } elseif (preg_match('/Logout|Disconnect|LogExit/i', $line)) {
                // Закрываем сессию рейтинга, чтобы $activeSessions не рос бесконечно.
                // SteamId ищем разными паттернами (формат зависит от версии сервера).
                $sid = '';
                if (preg_match('/(\d{17})/', $line, $m)) {
                    $sid = $m[1];
                } elseif (preg_match('/\((\d{9,})\)/', $line, $m)) {
                    $sid = $m[1];
                } elseif (preg_match('/(\d{9,})/', $line, $m)) {
                    $sid = $m[1];
                }
                if ($sid !== '') {
                    $this->rating->playerDisconnected($sid);
                }
                if (! $skipActions) {
                    $this->jobs->enqueue('event', ['event' => 'player_leave', 'data' => ['line' => $line, 'steamId' => $sid]]);
                }
            }
        } elseif (str_contains($lowerName, 'vehicle')) {
            $event['type'] = 'vehicle';
            if (! $skipActions) {
                $this->lolka->sendVehicleEvent($line);
                $this->jobs->enqueue('event', ['event' => 'vehicle_destroyed', 'data' => ['line' => $line]]);
                if (preg_match('/(?:Destroyed|destroyed|Disappeared|disappeared).*?(?:ID[:\s]+(\d+)|entity[_\s]*id[:\s]+(\d+))/i', $line, $vm) || preg_match('/(?:ID[:\s]+(\d+)).*?(?:Destroyed|destroyed)/i', $line, $vm)) {
                    $vId = (int) ($vm[1] ?: ($vm[2] ?? 0));
                    if ($vId > 0) {
                        try {
                            app(InsuranceService::class)->onVehicleDestroyed($vId);
                        } catch (\Throwable $e) {
                            Log::channel('scum')->error('[Insurance] Error in onVehicleDestroyed: '.$e->getMessage());
                        }
                    }
                }
            }
        } elseif (str_starts_with($lowerName, 'chest_ownership')) {
            $event['type'] = 'chest';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'chest_ownership', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'economy')) {
            $event['type'] = 'economy';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'economy', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'famepoints')) {
            $event['type'] = 'famepoints';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'famepoints', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'gameplay')) {
            $event['type'] = 'gameplay';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'gameplay', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'kill')) {
            $event['type'] = 'kill';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'player_killed', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'quests')) {
            $event['type'] = 'quests';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'quest', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'sentry')) {
            $event['type'] = 'sentry';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'sentry', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'loot')) {
            $event['type'] = 'loot';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'loot', 'data' => ['line' => $line]]);
            }
        } elseif (str_starts_with($lowerName, 'violations')) {
            $event['type'] = 'violations';
            if (! $skipActions) {
                $this->jobs->enqueue('event', ['event' => 'violation', 'data' => ['line' => $line]]);
            }
        }

        DB::table('log_events')->insert([
            'type' => $event['type'],
            'message' => $line,
            'details' => $event['details'] ? json_encode($event['details'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        // Чистим таблицы событий пакетами (раз в CLEANUP_EVERY_LINES строк),
        // а не на каждую строку — иначе это 4 лишних запроса на строку.
        $this->linesSinceCleanup++;
        if ($this->linesSinceCleanup >= self::CLEANUP_EVERY_LINES) {
            $this->linesSinceCleanup = 0;
            $this->cleanupEventTables();
        }
    }

    /**
     * Сохранение лёгкого события (логин/выход/онлайн-счётчик из SCUM.log)
     * в таблицу log_events — без полного анализа строки processLine.
     */
    private function addEvent(string $type, string $message): void
    {
        DB::table('log_events')->insert([
            'type' => $type,
            'message' => $message,
            'details' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->linesSinceCleanup++;
        if ($this->linesSinceCleanup >= self::CLEANUP_EVERY_LINES) {
            $this->linesSinceCleanup = 0;
            $this->cleanupEventTables();
        }
    }

    /**
     * Удаляет старые строки из log_events/chat_events, оставляя последние KEEP_EVENTS.
     * Максимальный id считается один раз на пакет.
     */
    private function cleanupEventTables(): void
    {
        try {
            $this->pruneEventsTable('log_events', self::KEEP_EVENTS);
            $this->pruneEventsTable('chat_events', self::KEEP_EVENTS);
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[FtpLogWatcher] cleanup error: '.$e->getMessage());
        }
    }

    /**
     * Удаляет старые строки таблицы событий пакетами, оставляя последние $keep.
     */
    private function pruneEventsTable(string $table, int $keep): void
    {
        $max = (int) DB::table($table)->max('id');
        if ($max <= $keep) {
            return;
        }
        $cutoff = $max - $keep;
        do {
            $deleted = DB::table($table)->where('id', '<', $cutoff)->limit(5000)->delete();
        } while ($deleted > 0);
    }

    /**
     * Синхронизация SCUM.db с FTP.
     * - Без $force — сначала дешёвая проверка свежести (mtime/size сервера против
     *   локальной копии): если сервер не новее — файл не качается.
     * - Качает во временный файл, проверяет целостность (SQLite quick_check),
     *   затем атомарно заменяет локальную копию (rename).
     * - Блокировка flock: параллельные синки (веб + демон) не качают одновременно.
     */
    public function syncScumDb(bool $force = false): array
    {
        $lock = null;
        try {
            $path = $this->config['scumDbPath'] ?? 'SCUM/Saved/SaveFiles/SCUM.db';
            $tmpDir = storage_path('app/scum');
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            $local = $tmpDir.'/SCUM.db';
            $tmpFile = $tmpDir.'/SCUM.db.tmp';

            // FTP заведомо лежит (backoff активен) — не трогаем сервер, копия остаётся прежней.
            if ($this->ftpBackoffActive()) {
                return ['ok' => true, 'upToDate' => true, 'busy' => true, 'size' => is_file($local) ? (int) @filesize($local) : 0];
            }

            if (! $this->remote->isConnected()) {
                if (! $this->remote->connect($this->config)) {
                    // Полный коннект не удался — включаем backoff, чтобы не долбить сервер.
                    $this->markFtpFailure();

                    return ['ok' => false, 'error' => $this->remote->lastError()];
                }
            }

            // Дешёвая проверка: не качаем файл целиком, если серверная копия не новее локальной.
            if (! $force && is_file($local)) {
                $remoteMtime = $this->remote->fileMtime($path);
                $remoteSize = $this->remote->fileSize($path);
                $localMtime = (int) @filemtime($local);
                if ($remoteMtime > 0 && $localMtime >= $remoteMtime) {
                    $this->recordDbState(['synced_at' => time(), 'remote_mtime' => $remoteMtime, 'remote_size' => $remoteSize]);

                    return ['ok' => true, 'upToDate' => true, 'size' => max($remoteSize, (int) @filesize($local))];
                }
                if ($remoteMtime <= 0 && $remoteSize >= 0 && $remoteSize === (int) @filesize($local)) {
                    $this->recordDbState(['synced_at' => time(), 'remote_size' => $remoteSize]);

                    return ['ok' => true, 'upToDate' => true, 'size' => $remoteSize];
                }
            }

            // Локальные правки не выгружены на сервер (заливка заблокирована занятым файлом) —
            // не перекачиваем серверную копию поверх них в окне PENDING_TTL.
            if (! $force && is_file($local) && $this->hasPendingUpload()) {
                $this->recordDbState(['synced_at' => time()]);

                return ['ok' => true, 'upToDate' => true, 'pending' => true, 'size' => (int) @filesize($local)];
            }

            // Блокировка от параллельных качалок (демон + веб-запросы).
            $lock = fopen($tmpDir.'/scumdb.lock', 'c');
            if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
                // Занято другим процессом — пропускаем, копия обновится им.
                return ['ok' => true, 'busy' => true];
            }

            // Двойная проверка под замком: пока ждали блокировку, другой процесс мог обновить копию.
            if (! $force && is_file($local)) {
                $remoteMtime = $this->remote->fileMtime($path);
                if ($remoteMtime > 0 && (int) @filemtime($local) >= $remoteMtime) {
                    return ['ok' => true, 'upToDate' => true, 'size' => (int) @filesize($local)];
                }
            }

            if (! $this->remote->fileExists($path)) {
                return ['ok' => false, 'error' => 'SCUM.db не найден на FTP: '.$path];
            }

            @unlink($tmpFile);
            $ok = $this->remote->downloadToFile($path, $tmpFile);
            // На случай разрыва соединения — переподключаемся и пробуем ещё раз
            if (! $ok || ! is_file($tmpFile) || filesize($tmpFile) < 100) {
                $this->remote->disconnect();
                if ($this->remote->connect($this->config)) {
                    @unlink($tmpFile);
                    $ok = $this->remote->downloadToFile($path, $tmpFile);
                }
            }
            if (! $ok || ! is_file($tmpFile) || filesize($tmpFile) < 100) {
                @unlink($tmpFile);

                return ['ok' => false, 'error' => 'SCUM.db пустой или слишком маленький'];
            }

            // Проверка сигнатуры SQLite, чтобы не сохранить бинарный мусор/логи
            $fp = fopen($tmpFile, 'rb');
            $head = $fp ? fread($fp, 16) : '';
            if ($fp) {
                fclose($fp);
            }
            if ($head !== "SQLite format 3\x00") {
                @unlink($tmpFile);

                return ['ok' => false, 'error' => 'Скачанный файл не является SQLite-базой (проверьте путь scumDbPath)'];
            }

            // Целостность: сервер может писать в SCUM.db во время скачивания,
            // и снапшот может оказаться битым. quick_check ловит это — пропускаем замену.
            try {
                $check = new \PDO('sqlite:'.$tmpFile);
                $check->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $res = $check->query('PRAGMA quick_check(1)')->fetch(\PDO::FETCH_NUM);
                $check = null;
                $integrity = is_array($res) ? (string) ($res[0] ?? '') : '';
                if ($integrity !== 'ok') {
                    // Если поврежден только индекс, пробуем восстановить через REINDEX
                    if (str_contains(strtolower($integrity), 'index')) {
                        try {
                            $reindexPdo = new \PDO('sqlite:'.$tmpFile);
                            $reindexPdo->exec('REINDEX;');
                            $res2 = $reindexPdo->query('PRAGMA quick_check(1)')->fetch(\PDO::FETCH_NUM);
                            $reindexPdo = null;
                            $integrity2 = is_array($res2) ? (string) ($res2[0] ?? '') : '';
                            if ($integrity2 === 'ok') {
                                Log::channel('scum')->info('[Scumdb] REINDEX успешно восстановил повреждённые индексы SCUM.db');
                                $integrity = 'ok';
                            }
                        } catch (\Throwable) {
                            $reindexPdo = null;
                        }
                    }
                }

                if ($integrity !== 'ok') {
                    @unlink($tmpFile);
                    Log::channel('scum')->warning('[Scumdb] quick_check failed ('.$integrity.') — пропускаем обновление');

                    return ['ok' => false, 'error' => 'SCUM.db повреждён на сервере (quick_check: '.$integrity.')'];
                }
            } catch (\Throwable $e) {
                @unlink($tmpFile);

                return ['ok' => false, 'error' => 'SCUM.db не открывается: '.$e->getMessage()];
            }

            // Атомарная замена на той же ФС — читатели не увидят «половинчатый» файл.
            // Если файл занят другим процессом (демон держит PDO), rename/unlink отказывают —
            // несколько ретраев, затем перезаписываем через copy прямо в существующий файл.
            $replaced = @rename($tmpFile, $local);
            for ($i = 0; ! $replaced && $i < 4; $i++) {
                usleep(200000);
                $replaced = @rename($tmpFile, $local);
            }
            if (! $replaced) {
                if (! @copy($tmpFile, $local)) {
                    @unlink($local);
                    if (! @copy($tmpFile, $local)) {
                        @unlink($tmpFile);

                        return ['ok' => false, 'error' => 'Не удалось заменить SCUM.db (файл занят)'];
                    }
                }
            }
            @unlink($tmpFile);
            // Убираем журналы СТАРОЙ копии: при открытии свежей базы SQLite может
            // «накатить» устаревший WAL поверх новых данных — из-за этого данные
            // выглядели пропавшими/откатывались к старому состоянию.
            @unlink($local.'-wal');
            @unlink($local.'-shm');
            @unlink($tmpFile.'-wal');
            @unlink($tmpFile.'-shm');
            @touch($local);
            // Переоткрываем PDO, чтобы читать свежий inode, а не старую копию.
            $this->scumDb->close();
            $this->scumDb->setDbPath($local);
            $tables = $this->scumDb->getTables();
            $this->recordDbState([
                'synced_at' => time(),
                'remote_mtime' => $this->remote->fileMtime($path),
                'remote_size' => $this->remote->fileSize($path),
            ]);

            return ['ok' => true, 'tables' => $tables, 'size' => (int) filesize($local)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if ($lock) {
                @flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Загрузка локальной копии SCUM.db обратно на сервер (после изменений).
     */
    public function uploadScumDb(): array
    {
        try {
            $file = storage_path('app/scum/SCUM.db');
            if (! file_exists($file)) {
                return ['ok' => false, 'error' => 'Локальная копия SCUM.db не найдена'];
            }
            $path = $this->config['scumDbPath'] ?? 'SCUM/Saved/SaveFiles/SCUM.db';
            if (! $this->remote->isConnected()) {
                if (! $this->remote->connect($this->config)) {
                    return ['ok' => false, 'error' => $this->remote->lastError()];
                }
            }
            // Локальные правки живут в -wal (SQLite WAL) — без checkpoint'а они не
            // попадут в основной файл и уйдут на сервер незаписанными («правки пропадают»).
            $this->scumDb->checkpointWal();
            $data = file_get_contents($file);
            $ok = $this->remote->uploadBuffer($path, $data);
            // На случай разрыва соединения — переподключаемся и пробуем ещё раз
            if (! $ok) {
                $this->remote->disconnect();
                if ($this->remote->connect($this->config)) {
                    $ok = $this->remote->uploadBuffer($path, $data);
                }
            }
            if (! $ok) {
                return ['ok' => false, 'error' => $this->remote->lastError() ?: 'FTP upload failed'];
            }
            // Успешная заливка — локальные правки уехали на сервер, защиту снимаем.
            $this->clearPendingUpload();
            // Отмечаем локальную копию свежей, чтобы демон не перекачал только что
            // залитые правки (которые уже уехали на сервер).
            @touch($file);
            $this->recordDbState(['uploaded_at' => time()]);
            Log::channel('scum')->info('[Scumdb] uploaded to server: '.$path);

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private const PENDING_TTL = 3600; // сек: окно защиты локальных правок от перекачки демоном

    private const CHEST_CACHE_KEY = 'scum_chest_data';

    private const CHEST_RAW_KEY = 'scum_chest_raw';

    private const CHEST_SNAPSHOT_KEY = 'scum_chest_snapshot';

    private const CHEST_CACHE_TTL = 3600;

    private const CHEST_GRACE_SEC = 60;

    /**
     * Сундуки и перехваты для карты — порт webPanel.ts handleChests().
     * Читает chest_ownership*.log из каталога логов по FTP (UTF-16/UTF-8),
     * совмещает с текущими позициями сундуков из SCUM.db (обход owning chain)
     * и определяет перехваты (более одного владельца). Результат кэшируется на 10 сек.
     */
    public function getChestData(): array
    {
        $data = Cache::get(self::CHEST_CACHE_KEY);
        if (is_array($data) && isset($data['chests'])) {
            // Свежий кэш (обновляется демоном scum:watch раз в 30с) — FTP не трогаем.
            $at = (int) ($data['at'] ?? 0);
            if (time() - $at < (int) config('panel.cache.chest_grace_sec', self::CHEST_GRACE_SEC)) {
                return $data;
            }
            // Кэш старше grace-периода: быстрая проверка размеров файлов.
            $sizes = $this->currentChestFileSizes();
            if ($sizes !== null && $sizes === Cache::get(self::CHEST_SNAPSHOT_KEY)) {
                // Файлы не менялись — продлеваем свежесть кэша, чтобы не проверять FTP каждый раз.
                $data['at'] = time();
                Cache::put(self::CHEST_CACHE_KEY, $data, (int) config('panel.cache.chest_ttl', self::CHEST_CACHE_TTL));

                return $data;
            }

            return $this->rebuildChestData($sizes ?? []);
        }

        return $this->rebuildChestData();
    }

    /**
     * Свежесть кэша листинга каталога логов (LIST — дорогая операция).
     * Держится чуть меньше rotationCheckSec, чтобы checkLogs не пропускал
     * ротацию из-за доступа chest warm к устаревшему кэшу.
     */
    private function listingTtlSec(): int
    {
        return max(5, (int) ($this->config['rotationCheckSec'] ?? 120));
    }

    /**
     * Текущие размеры chest_ownership*.log на сервере (быстрая проверка FTP, без скачивания).
     * null — FTP недоступен.
     */
    private function currentChestFileSizes(): ?array
    {
        $logPath = $this->config['logPath'] ?? 'SCUM/Saved/SaveFiles/Logs';
        try {
            // FTP заведомо недоступен — вернуть null, не создавая соединение.
            if ($this->ftpBackoffActive()) {
                return null;
            }
            if (! $this->remote->isConnected()) {
                if (! $this->remote->connect($this->config)) {
                    $this->markFtpFailure();
                    throw new \RuntimeException($this->remote->lastError());
                }
            }
            // Переиспользуем кэш листинга из checkLogs (обновляется каждые
            // rotationCheckSec) — не дёргаем FTP полным LIST ради сундуков.
            if ($this->listingCache !== null && (time() - $this->listingCacheAt) < $this->listingTtlSec()) {
                $files = $this->listingCache;
            } else {
                $files = $this->remote->listFiles($logPath);
                if ($files !== []) {
                    $this->listingCache = $files;
                    $this->listingCacheAt = time();
                }
            }
            $sizes = [];
            foreach ($files as $f) {
                $name = (string) ($f['name'] ?? '');
                if (str_starts_with(strtolower($name), 'chest_ownership')
                    && str_ends_with(strtolower($name), '.log')
                    && (int) ($f['size'] ?? 0) > 0) {
                    $sizes[$name] = (int) $f['size'];
                }
            }
            ksort($sizes);

            return $sizes;
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[Scumdb] chest file list failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Собирает данные сундуков, качая только новые/изменившиеся файлы.
     * Сырой контент и снапшот размеров кэшируются, чтобы не качать
     * все логи заново (каждая FTP-операция на этом хостинге ~2.8с).
     */
    private function rebuildChestData(?array $currentSizes = null): array
    {
        $raw = Cache::get(self::CHEST_RAW_KEY, []);
        if (! is_array($raw)) {
            $raw = [];
        }
        $snapshot = Cache::get(self::CHEST_SNAPSHOT_KEY, []);
        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        if ($currentSizes === null) {
            $currentSizes = $this->currentChestFileSizes() ?? [];
        }
        $logPath = $this->config['logPath'] ?? 'SCUM/Saved/SaveFiles/Logs';

        try {
            if (! $this->remote->isConnected()) {
                if (! $this->remote->connect($this->config)) {
                    throw new \RuntimeException($this->remote->lastError());
                }
            }
            foreach ($currentSizes as $name => $size) {
                // Без изменений и уже скачан — пропускаем (экономим ~2.8с на файл).
                if (isset($snapshot[$name], $raw[$name]) && $snapshot[$name] === $size) {
                    continue;
                }
                $buffer = $this->remote->downloadBuffer($logPath.'/'.$name);
                if ($buffer === '') {
                    continue;
                }
                $content = $this->remote->decodeBuffer($buffer);
                if ($content !== '') {
                    $raw[$name] = $content;
                }
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[Scumdb] chest log read failed: '.$e->getMessage());
            $cached = Cache::get(self::CHEST_CACHE_KEY);
            if (is_array($cached) && isset($cached['chests'])) {
                return $cached;
            }
        }

        // Файлы, которых больше нет на сервере, из кэша выбрасываем.
        $raw = array_intersect_key($raw, $currentSizes);

        $events = [];
        $claimedRe = "/^(\d{4}\.\d{2}\.\d{2}-\d{2}\.\d{2}\.\d{2}):\s*Chest \(entity id:\s*(\d+)\) ownership claimed\.\s*Owner:\s*(\d+)\s*\((\d+),\s*([^)]+)\)\.\s*Location:\s*X=(-?[\d.]+)\s*Y=(-?[\d.]+)\s*Z=(-?[\d.]+)/";
        $changedRe = "/^(\d{4}\.\d{2}\.\d{2}-\d{2}\.\d{2}\.\d{2}):\s*Chest \(entity id:\s*(\d+)\) ownership changed\.\s*Old owner:\s*([^>]+?)\s*->\s*New owner:\s*([^)]+?)\)\.\s*Location:\s*X=(-?[\d.]+)\s*Y=(-?[\d.]+)\s*Z=(-?[\d.]+)/";

        foreach ($raw as $content) {
            if ($content === '') {
                continue;
            }
            foreach (preg_split('/\r?\n/', $content) as $rawLine) {
                $line = str_replace("\u{FEFF}", '', trim($rawLine));
                if (! str_contains($line, 'Chest (')) {
                    continue;
                }
                if (preg_match($claimedRe, $line, $m)) {
                    $events[] = [
                        'timestamp' => $m[1], 'entityId' => (string) $m[2],
                        'ownerSteamId' => $m[3], 'ownerDbId' => (int) $m[4],
                        'ownerName' => trim($m[5]),
                        'x' => (float) $m[6], 'y' => (float) $m[7], 'z' => (float) $m[8],
                    ];

                    continue;
                }
                if (preg_match($changedRe, $line, $m)) {
                    $nm = [];
                    if (preg_match('/^(\d+)\s*\((\d+),\s*([^)]+)$/', trim($m[4]), $nm)) {
                        $events[] = [
                            'timestamp' => $m[1], 'entityId' => (string) $m[2],
                            'ownerSteamId' => $nm[1], 'ownerDbId' => (int) $nm[2],
                            'ownerName' => trim($nm[3]),
                            'x' => (float) $m[5], 'y' => (float) $m[6], 'z' => (float) $m[7],
                        ];
                    } else {
                        $events[] = [
                            'timestamp' => $m[1], 'entityId' => (string) $m[2],
                            'ownerSteamId' => null, 'ownerDbId' => -1, 'ownerName' => null,
                            'x' => (float) $m[5], 'y' => (float) $m[6], 'z' => (float) $m[7],
                        ];
                    }
                }
            }
        }

        // Текущие позиции сундуков из локальной копии SCUM.db (обход owning chain).
        $dbPositions = [];
        $local = storage_path('app/scum/SCUM.db');
        try {
            if (file_exists($local)) {
                $this->scumDb->setDbPath($local);
                $dbPositions = $this->scumDb->getChestPositions();
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[Scumdb] chest positions lookup failed: '.$e->getMessage());
        }

        // Последнее событие на сундук = текущий владелец; число разных владельцев = число перехватов.
        $latestByEntity = [];
        $ownerHistory = [];
        foreach ($events as $ev) {
            $latestByEntity[$ev['entityId']] = $ev;
            $ownerHistory[$ev['entityId']][] = $ev['ownerSteamId'];
        }

        $chests = [];
        $takeovers = [];
        $seen = [];

        // 1. Все актуальные сундуки и хранилища из SCUM.db
        foreach ($dbPositions as $entityId => $dbPos) {
            $entityId = (string) $entityId;
            $ev = $latestByEntity[$entityId] ?? null;
            $distinct = [];
            foreach ($ownerHistory[$entityId] ?? [] as $o) {
                if ($o) {
                    $distinct[$o] = true;
                }
            }
            $ownerCount = count($distinct);
            $taken = $ownerCount > 1;

            $chest = [
                'entityId' => $entityId,
                'entityClass' => (string) ($dbPos['entityClass'] ?? ''),
                'ownerSteamId' => $ev['ownerSteamId'] ?? null,
                'ownerDbId' => $ev['ownerDbId'] ?? null,
                'ownerName' => $ev['ownerName'] ?? null,
                'x' => (float) $dbPos['x'],
                'y' => (float) $dbPos['y'],
                'z' => (float) ($dbPos['z'] ?? 0),
                'posFromDb' => true,
                'claimedAt' => $ev['timestamp'] ?? null,
                'ownerChanges' => max(0, $ownerCount - 1),
                'taken' => $taken,
            ];

            $chests[] = $chest;
            $seen[$entityId] = true;

            if ($taken) {
                $takeovers[] = $chest;
            }
        }

        // 2. Сундуки из логов владения, если их ещё нет в SCUM.db (новые / до выгрузки БД)
        foreach ($latestByEntity as $entityId => $ev) {
            $entityId = (string) $entityId;
            if (isset($seen[$entityId])) {
                continue;
            }
            $distinct = [];
            foreach ($ownerHistory[$entityId] ?? [] as $o) {
                if ($o) {
                    $distinct[$o] = true;
                }
            }
            $ownerCount = count($distinct);
            $taken = $ownerCount > 1;

            $chest = [
                'entityId' => $entityId,
                'entityClass' => '',
                'ownerSteamId' => $ev['ownerSteamId'] ?? null,
                'ownerDbId' => $ev['ownerDbId'],
                'ownerName' => $ev['ownerName'] ?? null,
                'x' => (float) $ev['x'],
                'y' => (float) $ev['y'],
                'z' => (float) $ev['z'],
                'posFromDb' => false,
                'claimedAt' => $ev['timestamp'],
                'ownerChanges' => max(0, $ownerCount - 1),
                'taken' => $taken,
            ];

            $chests[] = $chest;
            if ($taken) {
                $takeovers[] = $chest;
            }
        }

        $data = ['chests' => $chests, 'takeovers' => $takeovers, 'totalEvents' => count($events), 'at' => time()];
        Cache::put(self::CHEST_CACHE_KEY, $data, (int) config('panel.cache.chest_ttl', self::CHEST_CACHE_TTL));
        Cache::put(self::CHEST_RAW_KEY, $raw, (int) config('panel.cache.chest_ttl', self::CHEST_CACHE_TTL));
        Cache::put(self::CHEST_SNAPSHOT_KEY, $currentSizes, (int) config('panel.cache.chest_ttl', self::CHEST_CACHE_TTL));

        return $data;
    }

    /**
     * Пометить локальную SCUM.db как содержащую невыгруженные правки.
     * Пока метка свежая, демон не перекачивает серверную копию поверх локальных изменений.
     */
    public function markPendingUpload(): void
    {
        $dir = storage_path('app/scum');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @touch($dir.'/.scumdb_pending');
    }

    public function clearPendingUpload(): void
    {
        @unlink(storage_path('app/scum/.scumdb_pending'));
    }

    public function hasPendingUpload(): bool
    {
        $f = storage_path('app/scum/.scumdb_pending');

        return is_file($f) && (time() - (int) @filemtime($f)) < (int) config('panel.ftp.pending_ttl', self::PENDING_TTL);
    }

    private const DB_STATE_FILE = 'app/scum/scumdb_state.json';

    private function dbStatePath(): string
    {
        return storage_path(self::DB_STATE_FILE);
    }

    /**
     * Персистентный статус SCUM.db для веб-страницы: когда последний раз
     * выгружали на сервер, скачивали с сервера и какой mtime у серверной копии.
     */
    public function getDbState(): array
    {
        $default = [
            'uploaded_at' => null,
            'synced_at' => null,
            'remote_mtime' => null,
            'remote_size' => null,
        ];
        $f = $this->dbStatePath();
        if (! is_file($f)) {
            return $default;
        }
        $data = json_decode((string) @file_get_contents($f), true);

        return array_merge($default, is_array($data) ? $data : []);
    }

    /**
     * Дописывает часть полей состояния SCUM.db (объединяя с уже сохранёнными).
     */
    private function recordDbState(array $patch): void
    {
        $dir = storage_path('app/scum');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $state = $this->getDbState();
        $state = array_merge($state, $patch);
        @file_put_contents($this->dbStatePath(), json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Обновляет персистентную пометку «подключено» для веб-процессов.
     * Вызывается демоном в каждом цикле, чтобы не истекал STATE_TTL.
     */
    /**
     * Принудительная синхронизация всех логов с FTP-сервера (старых и новых).
     *
     * @return array{success:bool,message:string,chat_count:int,log_count:int}
     */
    public function syncAllLogs(bool $force = false): array
    {
        if ($force) {
            $this->offsets = [];
            $this->saveOffsets();
        }
        $this->initState(reconnecting: false);

        return [
            'success' => true,
            'message' => 'Синхронизация всех логов завершена',
            'chat_count' => DB::table('chat_events')->count(),
            'log_count' => DB::table('log_events')->count(),
        ];
    }

    public function refreshState(): void
    {
        if ($this->remote->isConnected()) {
            $this->writeState([
                'connected' => true,
                'host' => $this->config['host'] ?? '',
                'port' => (int) ($this->config['port'] ?? 21),
                'protocol' => $this->config['protocol'] ?? 'ftp',
                'at' => time(),
            ]);
        }
    }

    private function statePath(): string
    {
        return storage_path('ftp_state.json');
    }

    private function normKey(string $path): string
    {
        return ltrim($path, '/');
    }

    private function getOffset(string $remotePath): int
    {
        $k1 = $this->normKey($remotePath);
        $k2 = '/'.$k1;

        return (int) ($this->offsets[$k1] ?? $this->offsets[$k2] ?? 0);
    }

    private function setOffset(string $remotePath, int $offset): void
    {
        $k1 = $this->normKey($remotePath);
        $this->offsets[$k1] = $offset;
        unset($this->offsets['/'.$k1]);
    }

    private function offsetsPath(): string
    {
        return storage_path('ftp_offsets.json');
    }

    private function loadOffsets(): void
    {
        try {
            $path = $this->offsetsPath();
            if (! is_file($path)) {
                return;
            }
            $raw = @file_get_contents($path);
            $data = $raw ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $normalized = [];
                foreach ($data as $k => $v) {
                    $normalized[ltrim((string) $k, '/')] = (int) $v;
                }
                $this->offsets = $normalized;
                $this->lineCounts = [];
            }
        } catch (\Throwable $e) {
            // ignore — просто стартуем с нуля
        }
    }

    private function saveOffsets(): void
    {
        $this->pruneOffsets();
        $json = json_encode($this->offsets, JSON_UNESCAPED_SLASHES);
        $result = @file_put_contents($this->offsetsPath(), $json, LOCK_EX);
        if ($result === false) {
            Log::channel('scum')->error('[FtpLogWatcher] Failed to save offsets to '.$this->offsetsPath().': '.(error_get_last()['message'] ?? 'unknown'));
        }
    }

    /**
     * Ограничивает размер карт смещений/счётчиков строк. Чат-файлы ротируются
     * несколько раз в сутки, и без ограничения эти карты растут бесконечно
     * (память демона + ftp_offsets.json). Держим только последние OFFSETS_MAX
     * файлов по имени (в имени зашита дата-время), плюс текущий активный файл.
     */
    private function pruneOffsets(): void
    {
        // Офсет SCUM.log держим всегда — он не ротируется как chat-файлы.
        $scum = $this->offsets[self::SCUM_LOG_KEY] ?? null;
        unset($this->offsets[self::SCUM_LOG_KEY]);
        if (count($this->offsets) > self::OFFSETS_MAX) {
            $current = array_values(array_map(fn ($c) => $c['path'] ?? null, $this->lastLogFiles));
            uksort($this->offsets, fn ($a, $b) => strcmp($b, $a));
            $kept = array_slice($this->offsets, 0, self::OFFSETS_MAX, true);
            foreach ($current as $p) {
                if ($p !== null && isset($this->offsets[$p]) && ! isset($kept[$p])) {
                    $kept[$p] = $this->offsets[$p];
                }
            }
            $this->offsets = $kept;
        }
        if ($scum !== null) {
            $this->offsets[self::SCUM_LOG_KEY] = $scum;
        }
        if (count($this->lineCounts) > self::OFFSETS_MAX) {
            uksort($this->lineCounts, fn ($a, $b) => strcmp($b, $a));
            $this->lineCounts = array_slice($this->lineCounts, 0, self::OFFSETS_MAX, true);
        }
    }

    private function writeState(array $data): void
    {
        $path = $this->statePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($path, 0666);
    }

    private function writeFailureState(string $error): void
    {
        $path = $this->statePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode([
            'connected' => false,
            'error' => $error,
            'at' => time(),
        ], JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($path, 0666);
    }

    private function clearState(): void
    {
        $path = $this->statePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function readState(): ?array
    {
        if (! is_file($this->statePath())) {
            return null;
        }
        $raw = @file_get_contents($this->statePath());
        $data = $raw ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }
}
