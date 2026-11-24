<?php

namespace App\Services\Scum;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Source RCON protocol client — порт rconClient.ts
 */
class RconService
{
    private const SERVERDATA_AUTH = 3;

    private const SERVERDATA_AUTH_RESPONSE = 2;

    private const SERVERDATA_EXECCOMMAND = 2;

    private const SERVERDATA_RESPONSE_VALUE = 0;

    private const CONNECT_TIMEOUT = 5;

    private const AUTH_TIMEOUT = 3;

    private const COMMAND_TIMEOUT = 15;

    private const RESPONSE_DEBOUNCE_MS = 100;

    private const STATE_TTL = 300;

    /** @var resource|null */
    private $socket = null;

    private bool $connected = false;

    private array $config = [];

    private int $packetId = 1;

    private bool $connecting = false;

    public function connect(string $host, int $port, string $password): array
    {
        if ($this->connecting) {
            return ['success' => false, 'response' => '', 'error' => 'Connection already in progress'];
        }
        // Закрываем локальный сокет, но НЕ трогаем персистентное состояние:
        // его мог записать демон, который уже держит единственное RCON-соединение.
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
        $this->connecting = true;
        $this->config = ['host' => $host, 'port' => $port, 'password' => $password];

        $address = $host === 'localhost' ? '127.0.0.1' : $host;
        $errno = 0;
        $errstr = '';
        $timeout = (int) config('panel.rcon.connect_timeout', self::CONNECT_TIMEOUT);

        try {
            $socket = @stream_socket_client(
                "tcp://{$address}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT
            );
            if (! $socket) {
                $this->connecting = false;
                $msg = $errstr ?: "Connection failed (errno $errno)";
                if (str_contains($msg, 'timed out')) {
                    $msg = 'Connection timeout';
                } elseif (str_contains($msg, 'refused') || $errno === 10061) {
                    $msg = "Connection refused - check if server is running and RCON port {$port} is open";
                } elseif (str_contains($msg, 'reset') || $errno === 10054) {
                    $msg = 'Connection reset - wrong password or RCON not enabled on server';
                }

                return ['success' => false, 'response' => '', 'error' => $msg];
            }

            stream_set_timeout($socket, (int) config('panel.rcon.connect_timeout', self::CONNECT_TIMEOUT));
            $this->socket = $socket;

            // Auth
            $authResult = $this->sendAuth($password);
            if (! $authResult['success']) {
                fclose($socket);
                $this->socket = null;
                $this->connecting = false;

                return ['success' => false, 'response' => '', 'error' => $authResult['error']];
            }

            $this->connected = true;
            $this->connecting = false;
            $this->writeState(['connected' => true, 'host' => $host, 'port' => (int) $port, 'at' => time()]);
            Log::channel('scum')->info("[RCON] Connected to {$address}:{$port}");

            return ['success' => true, 'response' => 'Connected successfully'];
        } catch (\Throwable $e) {
            $this->connecting = false;
            $this->connected = false;
            if ($this->socket) {
                @fclose($this->socket);
                $this->socket = null;
            }

            return ['success' => false, 'response' => '', 'error' => $e->getMessage()];
        }
    }

    private function sendAuth(string $password): array
    {
        if (! $this->socket) {
            return ['success' => false, 'error' => 'No socket'];
        }
        $id = $this->packetId++;
        $packet = $this->createPacket($id, self::SERVERDATA_AUTH, $password);
        fwrite($this->socket, $packet);

        $deadline = microtime(true) + (int) config('panel.rcon.auth_timeout', self::AUTH_TIMEOUT);
        while (microtime(true) < $deadline) {
            $response = $this->readPacket($id);
            if ($response === 'auth_ok') {
                return ['success' => true];
            }
            if ($response === 'auth_fail') {
                return ['success' => false, 'error' => 'Wrong rcon password'];
            }
            if ($response === null) {
                usleep(50000);
            }
        }

        return ['success' => false, 'error' => 'Auth timeout'];
    }

    public function disconnect(): void
    {
        $this->autoReconnect = false;
        if ($this->socket) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->connected = false;
        $this->config = [];
        $this->clearState();
    }

    public function __destruct()
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function sendCommand(string $command, bool $log = true): array
    {
        if (stripos(trim($command), 'SendChat') === 0) {
            $command = $this->prepareSendChat($command);
        }
        if (! $this->connected || ! $this->socket || ! $this->isAlive()) {
            // Авто-переподключение: веб-процесс живёт только в рамках запроса,
            // сокет теряется между запросами — коннектимся заново по конфигу.
            $this->ensureConfigLoaded();
            if (empty($this->config['host'])) {
                return ['success' => false, 'response' => '', 'error' => 'RCON не настроен — добавьте сервер или укажите RCON адрес в настройках'];
            }
            $result = $this->connect($this->config['host'], (int) $this->config['port'], $this->config['password'] ?? '');
            if (! $result['success']) {
                return ['success' => false, 'response' => '', 'error' => $result['error']];
            }
        }
        try {
            $response = $this->execCommand($command);
            if ($log) {
                $this->logCommand($command, $response);
            }

            return ['success' => true, 'response' => $response ?: 'Command executed (no response)'];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'timeout') || str_contains($msg, 'Broken pipe') || str_contains($msg, 'Connection reset') || str_contains($msg, 'EOF')) {
                $this->clearState();
                $this->connected = false;
                // Одна повторная попытка после разрыва — команда не должна теряться
                $this->ensureConfigLoaded();
                if (! empty($this->config['host'])) {
                    $result = $this->connect($this->config['host'], (int) $this->config['port'], $this->config['password'] ?? '');
                    if ($result['success']) {
                        try {
                            $response = $this->execCommand($command);
                            if ($log) {
                                $this->logCommand($command, $response);
                            }

                            return ['success' => true, 'response' => $response ?: 'Command executed (no response)'];
                        } catch (\Throwable $e2) {
                            $msg = $e2->getMessage();
                        }
                    }
                }
            }
            if ($log) {
                $this->logCommand($command, '', $msg);
            }

            return ['success' => false, 'response' => '', 'error' => $msg];
        }
    }

    public function isConnected(): bool
    {
        // Живой сокет (в пределах текущего запроса)
        if ($this->connected && is_resource($this->socket)) {
            return true;
        }
        // Персистентное состояние: соединение удерживает демон/другой запрос
        $this->ensureConfigLoaded();
        $state = $this->readState();

        return $state && ! empty($state['connected']) && (time() - ($state['at'] ?? 0)) < (int) config('panel.rcon.state_ttl', self::STATE_TTL);
    }

    /**
     * Неблокирующая проверка живости сокета (0 мс). Возвращает false,
     * если пир закрыл соединение (EOF) — сокет украден другим процессом.
     * Данные не потребляются — только проверка состояния.
     */
    public function isAlive(): bool
    {
        if (! $this->connected || ! is_resource($this->socket)) {
            return false;
        }
        $read = [$this->socket];
        $write = null;
        $except = null;
        $n = @stream_select($read, $write, $except, 0, 0);
        if ($n === false) {
            return false;
        }
        if ($n > 0) {
            // В Source RCON сервер отвечает только на запросы — в простое событие чтения
            // означает либо входящий ответ, либо закрытие пиром (FIN/RST/EOF).
            // Проверяем с помощью STREAM_PEEK без извлечения данных из буфера.
            $peek = @stream_socket_recvfrom($this->socket, 1, STREAM_PEEK);
            if ($peek === '' || $peek === false) {
                $this->connected = false;
                @fclose($this->socket);
                $this->socket = null;

                return false;
            }
        }

        return true;
    }

    public function status(): array
    {
        $daemon = app(DaemonService::class)->status(DaemonService::RCON);
        // Статус строго зависит от демона: без запущенного demona RCON не работает
        if (empty($daemon['running'])) {
            return ['connected' => false, 'daemon' => $daemon];
        }
        // Живой сокет в текущем процессе (демон или долгоживущий запрос)
        if ($this->connected && is_resource($this->socket)) {
            return ['connected' => true, 'host' => $this->config['host'] ?? '', 'port' => $this->config['port'] ?? 0, 'daemon' => $daemon];
        }
        // Состояние персистентное: соединение удерживает демон
        $this->ensureConfigLoaded();
        $state = $this->readState();
        $recent = $state && (time() - ($state['at'] ?? 0)) < (int) config('panel.rcon.state_ttl', self::STATE_TTL);
        if ($recent && ! empty($state['connected'])) {
            return ['connected' => true, 'host' => $state['host'] ?? '', 'port' => $state['port'] ?? 0, 'persisted' => true, 'daemon' => $daemon];
        }

        return ['connected' => false, 'daemon' => $daemon];
    }

    public function setAutoReconnect(bool $enabled): void
    {
        $this->autoReconnect = $enabled;
    }

    /**
     * Обновляет персистентную пометку «подключено» для веб-процессов.
     * Вызывается демоном в каждом цикле, чтобы не истекал STATE_TTL.
     */
    public function refreshState(): void
    {
        if ($this->connected && is_resource($this->socket) && $this->isAlive()) {
            $this->writeState([
                'connected' => true,
                'host' => $this->config['host'] ?? '',
                'port' => (int) ($this->config['port'] ?? 0),
                'at' => time(),
            ]);
        }
    }

    /**
     * Легковесная проверка подключения без изменения текущего активного соединения.
     */
    public function probe(string $host, int $port, string $password): array
    {
        $address = $host === 'localhost' ? '127.0.0.1' : $host;
        $errno = 0;
        $errstr = '';
        $probeSocket = @stream_socket_client(
            "tcp://{$address}:{$port}",
            $errno,
            $errstr,
            (int) config('panel.rcon.connect_timeout', self::CONNECT_TIMEOUT),
            STREAM_CLIENT_CONNECT
        );
        if (! $probeSocket) {
            $msg = $errstr ?: "Connection failed (errno $errno)";
            if (str_contains($msg, 'timed out')) {
                $msg = 'Connection timeout';
            } elseif (str_contains($msg, 'refused') || $errno === 10061) {
                $msg = "Connection refused - port {$port} closed";
            }

            return ['success' => false, 'error' => $msg];
        }

        stream_set_timeout($probeSocket, (int) config('panel.rcon.connect_timeout', self::CONNECT_TIMEOUT));
        $saved = $this->socket;
        $this->socket = $probeSocket;
        try {
            $result = $this->sendAuth($password);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        } finally {
            @fclose($probeSocket);
            $this->socket = $saved;
        }

        return $result;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    private function statePath(): string
    {
        return storage_path('rcon_state.json');
    }

    private function writeState(array $data): void
    {
        @file_put_contents($this->statePath(), json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
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

    private function ensureConfigLoaded(): void
    {
        if (! empty($this->config)) {
            return;
        }
        try {
            $cfg = app(SettingsService::class)->getAppConfig()['rcon'] ?? [];
            if (! empty($cfg['host'])) {
                $this->config = [
                    'host' => $cfg['host'],
                    'port' => (int) ($cfg['port'] ?? 28015),
                    'password' => $cfg['password'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            // ignore — конфиг просто не загрузится
        }
    }

    private function execCommand(string $command): string
    {
        if (! $this->socket) {
            throw new \RuntimeException('No socket');
        }
        if (feof($this->socket)) {
            $this->connected = false;
            throw new \RuntimeException('EOF before write');
        }
        $id = $this->packetId++;
        $packet = $this->createPacket($id, self::SERVERDATA_EXECCOMMAND, $command);
        $written = @fwrite($this->socket, $packet);
        if ($written === false || $written < strlen($packet)) {
            $this->connected = false;
            throw new \RuntimeException('Broken pipe while writing');
        }

        $deadline = microtime(true) + (int) config('panel.rcon.command_timeout', self::COMMAND_TIMEOUT);
        $buffers = [];
        $lastData = microtime(true);

        while (microtime(true) < $deadline) {
            // Быстрый выход при закрытии пиром — не ждём COMMAND_TIMEOUT
            if (feof($this->socket)) {
                $this->connected = false;
                throw new \RuntimeException('EOF while waiting response');
            }
            $packets = $this->readAvailablePackets();
            foreach ($packets as $pkt) {
                if ($pkt['id'] !== $id) {
                    continue;
                }
                if ($pkt['type'] === self::SERVERDATA_RESPONSE_VALUE) {
                    $lastData = microtime(true);
                    if ($pkt['body'] === '') {
                        // Терминатор — ответ собран
                        return implode('', $buffers);
                    }
                    $buffers[] = $pkt['body'];
                }
            }
            // Debounce 100ms: если данные не поступали — считаем ответ собранным
            if ($buffers && (microtime(true) - $lastData) * 1000 > (int) config('panel.rcon.response_debounce_ms', self::RESPONSE_DEBOUNCE_MS)) {
                return implode('', $buffers);
            }
            if ($buffers && $this->dataWaiting()) {
                usleep(20000);

                continue;
            }
            if (! $buffers) {
                usleep(50000);
            }
        }

        if ($buffers) {
            return implode('', $buffers);
        }
        throw new \RuntimeException('Command timeout');
    }

    private function dataWaiting(): bool
    {
        if (! $this->socket) {
            return false;
        }
        $read = [$this->socket];
        $write = null;
        $except = null;

        return stream_select($read, $write, $except, 0, 20000) > 0;
    }

    /** Читает один пакет и ждёт auth-ответ */
    private function readPacket(int $expectedId): ?string
    {
        $packets = $this->readAvailablePackets();
        foreach ($packets as $pkt) {
            if ($pkt['type'] === self::SERVERDATA_AUTH_RESPONSE) {
                if ($pkt['id'] === -1) {
                    return 'auth_fail';
                }
                if ($pkt['id'] === $expectedId) {
                    return 'auth_ok';
                }
            }
        }

        return null;
    }

    /** Читает ровно $length байт из сокета, не допуская обрыва на границе TCP MTU */
    private function readExact(int $length, float $timeout = 0.5): ?string
    {
        if (! $this->socket) {
            return null;
        }
        $buffer = '';
        $remaining = $length;
        $deadline = microtime(true) + $timeout;

        while ($remaining > 0 && microtime(true) < $deadline) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                $read = [$this->socket];
                $write = null;
                $except = null;
                if (stream_select($read, $write, $except, 0, 10000) <= 0) {
                    usleep(5000);
                }

                continue;
            }
            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }

        return strlen($buffer) === $length ? $buffer : null;
    }

    /** Читает все доступные пакеты из буфера сокета */
    private function readAvailablePackets(): array
    {
        $packets = [];
        if (! $this->socket) {
            return $packets;
        }

        while ($this->dataWaiting()) {
            $head = $this->readExact(4, 0.1);
            if ($head === null) {
                break;
            }
            $size = unpack('V', $head)[1];
            if ($size < 10 || $size > 65535) {
                break;
            }
            $body = $this->readExact($size, 1.0);
            if ($body === null) {
                break;
            }

            $id = unpack('V', substr($body, 0, 4))[1];
            $type = unpack('V', substr($body, 4, 4))[1];
            $payload = substr($body, 8, $size - 10);
            $packets[] = ['id' => $id, 'type' => $type, 'body' => $payload];
        }

        return $packets;
    }

    private function createPacket(int $id, int $type, string $body): string
    {
        $size = 4 + 4 + strlen($body) + 2; // id + type + body + 2 null
        $packet = pack('V', $size)
            .pack('V', $id)
            .pack('V', $type)
            .$body
            ."\x00\x00";

        return $packet;
    }

    /**
     * Очистка текста игрового чата от эмодзи, управляющих символов и спецзнаков,
     * которые не поддерживаются шрифтом SCUM и ломают кодировку/отображение.
     */
    public static function sanitizeChatText(string $text): string
    {
        $emojiMap = [
            '✅' => '[OK]',
            '❌' => '[X]',
            '⏳' => '[..]',
            '💰' => '[$]',
            '🎁' => '[AirDrop]',
            '🏆' => '[TOP]',
            '⭐' => '*',
            '📦' => '[Box]',
            '🚗' => '[Auto]',
            '🚕' => '[Taxi]',
            '🛵' => '[Moto]',
            '⚠️' => '[!]',
            '🔔' => '[!]',
            '💬' => '[>]',
            '🛒' => '[Shop]',
            '👑' => '[VIP]',
            '«' => '"',
            '»' => '"',
            '“' => '"',
            '”' => '"',
            '—' => '-',
            '–' => '-',
        ];
        $text = strtr($text, $emojiMap);

        // Удаляем 4-байтные UTF-8 (эмодзи и доп. плоскости Unicode)
        $clean = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
        if ($clean !== null) {
            $text = $clean;
        }

        // Удаляем диапазон символов/пиктограмм BMP (U+2600-U+27BF)
        $clean = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);
        if ($clean !== null) {
            $text = $clean;
        }

        // Удаляем переводы строк и заменяем кавычки
        $text = str_replace(["\r", "\n", '"'], ['', '', "'"], $text);

        return trim($text);
    }

    /**
     * Нормализация команды SendChat перед отправкой в RCON сокет
     */
    private function prepareSendChat(string $command): string
    {
        // 1. SendChat <channel> "<message>" [<steamId>]
        if (preg_match('/^SendChat\s+(\d+)\s+"(.*)"(?:\s+(\d{17}))?$/is', $command, $m)) {
            $ch = $m[1];
            $msg = self::sanitizeChatText($m[2]);
            $sid = ! empty($m[3]) ? ' '.$m[3] : '';

            return "SendChat {$ch} \"{$msg}\"{$sid}";
        }
        // 2. SendChat <channel> <message>
        if (preg_match('/^SendChat\s+(\d+)\s+(.+)$/is', $command, $m)) {
            $ch = $m[1];
            $rest = trim($m[2]);
            $sid = '';
            if (preg_match('/\s+(\d{17})$/', $rest, $sm)) {
                $sid = ' '.$sm[1];
                $rest = trim(substr($rest, 0, -strlen($sm[0])));
            }
            $rest = trim($rest, "\"'");
            $msg = self::sanitizeChatText($rest);

            return "SendChat {$ch} \"{$msg}\"{$sid}";
        }

        return $command;
    }

    private function logCommand(string $command, string $response, string $error = ''): void
    {
        $logPath = storage_path('logs/rcon_commands.log');

        // Авторотация текстового файла лога при превышении 10 МБ
        if (is_file($logPath) && filesize($logPath) > 10 * 1024 * 1024) {
            @rename($logPath, $logPath.'.1');
        }

        // Для частых служебных команд вроде ListSpawnedVehicles обрезаем ответ до 500 символов,
        // чтобы не раздувать логи мегабайтами списков техники
        $logResp = strlen($response) > 500 && stripos($command, 'ListSpawnedVehicles') === 0
            ? substr($response, 0, 500).'... [truncated '.strlen($response).' bytes]'
            : $response;

        $timestamp = now()->toISOString();
        $line = "[{$timestamp}] COMMAND: {$command} | RESPONSE: {$logResp}".($error ? " | ERROR: {$error}" : '')."\n";
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);

        \DB::table('rcon_command_logs')->insert([
            'command' => $command,
            'response' => $logResp ?: null,
            'error' => $error ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Получить пагинированную историю RCON-команд для веб-консоли.
     */
    public function getCommandHistory(int $perPage = 10): LengthAwarePaginator
    {
        return \Illuminate\Support\Facades\DB::table('rcon_command_logs')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
