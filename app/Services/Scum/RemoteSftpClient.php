<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SFTP;

/**
 * SFTP клиент через phpseclib — порт sftpClient.ts
 */
class RemoteSftpClient
{
    use CreatesTempFiles;

    private const SFTP_TYPE_REGULAR = 1;

    private ?SFTP $sftp = null;

    private string $lastError = '';

    private bool $connected = false;

    public function connect(array $config): bool
    {
        $this->disconnect();
        try {
            $host = $config['host'];
            $port = (int) ($config['port'] ?? 22);
            $timeout = (int) config('panel.sftp.timeout', config('panel.ftp.connect_timeout', 15));

            $this->sftp = new SFTP($host, $port, $timeout);
            $login = $this->sftp->login($config['username'] ?? '', $config['password'] ?? '');
            if (! $login) {
                $this->lastError = 'SFTP login failed';
                $this->sftp = null;

                return false;
            }
            $this->connected = true;
            $this->lastError = '';
            Log::channel('scum')->info("[SftpClient] Connected to {$host}:{$port}");

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->sftp = null;

            return false;
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
        if ($this->sftp) {
            try {
                $this->sftp->disconnect();
            } catch (\Throwable $e) {
            }
        }
        $this->sftp = null;
        $this->config = [];
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->sftp !== null;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    /** @return array{name:string,size:int}[] */
    public function listFiles(string $remotePath): array
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $files = $this->sftp->rawlist($remotePath);
        if (! is_array($files)) {
            return [];
        }
        $result = [];
        foreach ($files as $name => $info) {
            if (! isset($info['type']) || ! is_array($info)) {
                continue;
            }
            if (($info['type'] ?? self::SFTP_TYPE_REGULAR) !== self::SFTP_TYPE_REGULAR) {
                continue;
            }
            if ($name === '.' || $name === '..') {
                continue;
            }
            $result[] = ['name' => $name, 'size' => (int) ($info['size'] ?? 0)];
        }

        return $result;
    }

    public function downloadBuffer(string $remotePath, int $startAt = 0): string
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $data = $this->sftp->get($remotePath);
        if ($data === false) {
            $this->connected = false;
            $this->lastError = 'SFTP download failed';

            return '';
        }
        if ($startAt > 0 && strlen($data) > $startAt) {
            $data = substr($data, $startAt);
        }

        return $data;
    }

    public function fileExists(string $remotePath): bool
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $stat = $this->sftp->stat($remotePath);

        return is_array($stat);
    }

    public function fileSize(string $remotePath): int
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $stat = $this->sftp->stat($remotePath);

        return is_array($stat) ? (int) ($stat['size'] ?? 0) : -1;
    }

    /**
     * Время модификации файла на сервере. -1 — получить не удалось.
     */
    public function fileMtime(string $remotePath): int
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        $stat = $this->sftp->stat($remotePath);

        return is_array($stat) ? (int) ($stat['mtime'] ?? -1) : -1;
    }

    /**
     * Скачивание сразу в локальный файл (без загрузки в память).
     */
    public function downloadToFile(string $remotePath, string $localPath): bool
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected');
        }
        try {
            $ok = $this->sftp->get($remotePath, $localPath);
            if ($ok === false) {
                $this->connected = false;
                $this->lastError = 'SFTP download failed';

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
            $ok = $this->sftp->put($remotePath, $tmp, SFTP::SOURCE_LOCAL_FILE);
            @unlink($tmp);
            if (! $ok) {
                $this->connected = false;
                $this->lastError = 'SFTP upload failed';
            }

            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
}
