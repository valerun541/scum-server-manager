<?php

namespace App\Services\Scum;

use FTP\Connection;
use Illuminate\Support\Facades\Log;

/**
 * FTP клиент через встроенные функции PHP — порт ftpClient.ts
 */
class RemoteFtpClient
{
    use CreatesTempFiles;

    private ?Connection $conn = null;

    private string $lastError = '';

    private bool $connected = false;

    private bool $noRest = false;

    public function connect(array $config): bool
    {
        $this->disconnect();
        // ftp_rawlist()/ftp_nlist() буферизуют вывод во временный файл через
        // TMPDIR/TEMP/TMP. В процессах, запущенных не из интерактивной сессии
        // (web-сервер, демоны), эти переменные могут указывать в недоступный
        // каталог — явно направляем их в гарантированно записываемое место.
        $this->ensureTempEnv();
        try {
            $host = $config['host'];
            $port = (int) ($config['port'] ?? 21);
            $secure = (bool) ($config['secure'] ?? false);

            if ($secure) {
                $conn = @ftp_ssl_connect($host, $port, 15);
            } else {
                $conn = @ftp_connect($host, $port, 15);
            }
            if (! $conn) {
                $this->lastError = "FTP connect failed to {$host}:{$port}";

                return false;
            }
            $login = @ftp_login($conn, $config['username'] ?? '', $config['password'] ?? '');
            if (! $login) {
                $this->lastError = 'FTP login failed';
                @ftp_close($conn);

                return false;
            }
            // Пассивный режим
            @ftp_pasv($conn, true);
            $this->conn = $conn;
            $this->connected = true;
            $this->lastError = '';
            Log::channel('scum')->info("[FtpClient] Connected to {$host}:{$port}");

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
        if ($this->conn) {
            @ftp_close($this->conn);
        }
        $this->conn = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->conn instanceof Connection;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * Сервер отвечает "350" на REST, но игнорирует его (отдаёт файл с начала).
     * После вызова REST больше не используется — данные срезаются локально.
     */
    public function setNoRest(bool $value = true): void
    {
        $this->noRest = $value;
    }

    /** @return array{name:string,size:int}[] */
    public function listFiles(string $remotePath): array
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        try {
            // Один round-trip: ftp_rawlist уже содержит имена и размеры
            $raw = @ftp_rawlist($this->conn, $remotePath);
            if ($raw === false) {
                // Соединение могло умереть после простоя — пометить как отключённое,
                // чтобы демон переподключился вместо тихого ожидания
                $this->connected = false;
                $this->lastError = 'FTP list failed (connection lost)';

                return [];
            }
            $result = [];
            foreach ($raw as $line) {
                $parsed = $this->parseUnixListing($line);
                if ($parsed && $parsed['type'] === 'file') {
                    $result[] = ['name' => $parsed['name'], 'size' => (int) $parsed['size']];
                }
            }
            // Fallback: если rawlist не дал размеров — используем ftp_size для каждого имени из nlist
            if (empty($result)) {
                $items = @ftp_nlist($this->conn, $remotePath);
                if ($items) {
                    foreach ($items as $name) {
                        $base = basename($name);
                        $size = @ftp_size($this->conn, rtrim($remotePath, '/').'/'.$base);
                        $result[] = ['name' => $base, 'size' => $size === -1 ? 0 : (int) $size];
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $this->connected = false;
            throw $e;
        }
    }

    private function ensureTempEnv(): void
    {
        $dir = storage_path('app/scum/ftp_tmp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_writable($dir)) {
            putenv('TMPDIR='.$dir);
            putenv('TEMP='.$dir);
            putenv('TMP='.$dir);
        }
    }

    private function parseUnixListing(string $line): ?array
    {
        // drwxr-xr-x 2 user group 4096 Jan 01 12:34 name
        if (preg_match('/^([bcdlps-][rwxStT-]{9})\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\w{3})\s+(\d{1,2})\s+[\d:]{4,5}\s+(.+)$/', $line, $m)) {
            return [
                'type' => $m[1][0] === 'd' ? 'dir' : 'file',
                'size' => (int) $m[2],
                'name' => trim($m[5]),
            ];
        }
        // Windows: 01-01-26  12:34PM       <DIR>          folder
        if (preg_match('/^\d{2}-\d{2}-\d{2}\s+[\d:]+\s+(?:<DIR>|\d+)\s+(.+)$/', $line, $m)) {
            return ['type' => str_contains($line, '<DIR>') ? 'dir' : 'file', 'size' => 0, 'name' => trim($m[1])];
        }

        return null;
    }

    public function downloadBuffer(string $remotePath, int $startAt = 0): string
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        try {
            $tmp = $this->scumTempFile();
            try {
                $sliced = false;
                if ($startAt > 0 && ! $this->noRest) {
                    // REST <offset> — сервер начинает передачу с нужного байта.
                    // Это резко ускоряет цикл опроса логов: качаем только новые байты.
                    $resp = @ftp_raw($this->conn, 'REST '.(int) $startAt);
                    if (is_array($resp) && isset($resp[0]) && str_starts_with($resp[0], '350')) {
                        $sliced = true;
                    } else {
                        // Сервер не поддерживает REST — качаем целиком и срезаем локально
                        $this->noRest = true;
                    }
                }

                $res = @ftp_get($this->conn, $tmp, $remotePath, FTP_BINARY);
                if ($res === false) {
                    $this->connected = false;
                    $this->lastError = 'FTP download failed';

                    return '';
                }
                $data = @file_get_contents($tmp);
                if ($data === false) {
                    $data = '';
                }
                // Если REST "сработал" (350), но файл получен целиком с начала —
                // сервер игнорирует REST. Надёжное определение: сличение хвоста
                // с полным содержимым выполняется в FtpLogWatcherService по fileSize.
                // Здесь при 350 обрезаем локально, чтобы результат был всегда "с стартАта".
                if (! $sliced && $startAt > 0 && strlen($data) > $startAt) {
                    $data = substr($data, $startAt);
                }

                return $data;
            } finally {
                @unlink($tmp);
            }
        } catch (\Throwable $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();

            return '';
        }
    }

    public function fileExists(string $remotePath): bool
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $size = @ftp_size($this->conn, $remotePath);
        if ($size === -1 && @ftp_pwd($this->conn) === false) {
            $this->connected = false;
            $this->lastError = 'FTP size failed (connection lost)';

            return false;
        }

        return $size !== -1;
    }

    public function fileSize(string $remotePath): int
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $size = @ftp_size($this->conn, $remotePath);
        if ($size === -1 && @ftp_pwd($this->conn) === false) {
            $this->connected = false;
            $this->lastError = 'FTP size failed (connection lost)';

            return -1;
        }

        return $size;
    }

    /**
     * Время модификации файла на сервере (MDTM). -1 — сервер не поддерживает.
     */
    public function fileMtime(string $remotePath): int
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $t = @ftp_mdtm($this->conn, $remotePath);
        if ($t === -1 && @ftp_pwd($this->conn) === false) {
            $this->connected = false;
            $this->lastError = 'FTP mdtm failed (connection lost)';

            return -1;
        }

        return $t;
    }

    /**
     * Скачивание сразу в локальный файл (без загрузки в память) — для больших файлов.
     */
    public function downloadToFile(string $remotePath, string $localPath): bool
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        try {
            $ok = @ftp_get($this->conn, $localPath, $remotePath, FTP_BINARY);
            if ($ok === false) {
                $this->connected = false;
                $this->lastError = 'FTP download failed';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function uploadBuffer(string $remotePath, string $data): bool
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        try {
            $tmp = $this->scumTempFile();
            file_put_contents($tmp, $data);
            // Прямая запись поверх существующего файла на этом хостинге падает
            // («Unknown error»), если файл занят игровым процессом. Поэтому качаем
            // во временное имя и атомарно переименовываем поверх целевого.
            $remoteTmp = $remotePath.'.upload.'.bin2hex(random_bytes(4));
            $ok = @ftp_put($this->conn, $remoteTmp, $tmp, FTP_BINARY);
            @unlink($tmp);
            if (! $ok) {
                $this->connected = false;
                $this->lastError = 'FTP upload failed';

                return false;
            }
            if (! @ftp_rename($this->conn, $remoteTmp, $remotePath)) {
                $this->lastError = 'FTP replace (rename) failed';
                @ftp_delete($this->conn, $remoteTmp);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
}
