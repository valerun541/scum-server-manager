<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;

/**
 * Фасад над FTP/SFTP — порт remoteClient.ts
 * Выбирает протокол по config.protocol, добавляет basePath к путям.
 */
class RemoteClient
{
    private ?RemoteFtpClient $ftp = null;

    private ?RemoteSftpClient $sftp = null;

    private string $protocol = 'ftp';

    private string $basePath = '';

    /** Ленивое создание клиента по протоколу — phpseclib грузится только при использовании SFTP */
    private function ftp(): RemoteFtpClient
    {
        return $this->ftp ??= new RemoteFtpClient;
    }

    private function sftp(): RemoteSftpClient
    {
        return $this->sftp ??= new RemoteSftpClient;
    }

    public function connect(array $config): bool
    {
        $this->protocol = $config['protocol'] ?? 'ftp';
        $this->basePath = rtrim($config['basePath'] ?? '', '/');
        if ($this->protocol === 'sftp') {
            return $this->sftp()->connect($config);
        }

        return $this->ftp()->connect($config);
    }

    public function disconnect(): void
    {
        if ($this->protocol === 'sftp') {
            $this->sftp()->disconnect();
        } else {
            $this->ftp()->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return $this->protocol === 'sftp' ? ($this->sftp?->isConnected() ?? false) : ($this->ftp?->isConnected() ?? false);
    }

    public function lastError(): string
    {
        return $this->protocol === 'sftp' ? ($this->sftp?->lastError() ?? '') : ($this->ftp?->lastError() ?? '');
    }

    private function resolve(string $remotePath): string
    {
        if (! $this->basePath) {
            return $remotePath;
        }
        $p = str_starts_with($remotePath, '/') ? substr($remotePath, 1) : $remotePath;

        return $this->basePath.'/'.$p;
    }

    /** @return array{name:string,size:int}[] */
    public function listFiles(string $remotePath): array
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->listFiles($resolved) : $this->ftp()->listFiles($resolved);
    }

    public function setNoRest(bool $value = true): void
    {
        if ($this->protocol !== 'sftp') {
            $this->ftp()->setNoRest($value);
        }
    }

    public function downloadBuffer(string $remotePath, int $startAt = 0): string
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->downloadBuffer($resolved, $startAt) : $this->ftp()->downloadBuffer($resolved, $startAt);
    }

    public function fileExists(string $remotePath): bool
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->fileExists($resolved) : $this->ftp()->fileExists($resolved);
    }

    public function fileSize(string $remotePath): int
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->fileSize($resolved) : $this->ftp()->fileSize($resolved);
    }

    public function fileMtime(string $remotePath): int
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->fileMtime($resolved) : $this->ftp()->fileMtime($resolved);
    }

    public function downloadToFile(string $remotePath, string $localPath): bool
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->downloadToFile($resolved, $localPath) : $this->ftp()->downloadToFile($resolved, $localPath);
    }

    public function uploadBuffer(string $remotePath, string $data): bool
    {
        $resolved = $this->resolve($remotePath);

        return $this->protocol === 'sftp' ? $this->sftp()->uploadBuffer($resolved, $data) : $this->ftp()->uploadBuffer($resolved, $data);
    }

    public function readTextFile(string $remotePath): ?string
    {
        try {
            if (! $this->fileExists($remotePath)) {
                return null;
            }
            $buffer = $this->downloadBuffer($remotePath);
            if ($buffer === '') {
                return '';
            }

            return $this->decodeBuffer($buffer);
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[RemoteClient] readTextFile error: {$e->getMessage()}");

            return null;
        }
    }

    public function decodeBuffer(string $buffer): string
    {
        if ($buffer === '') {
            return '';
        }
        $isUtf16 = strlen($buffer) >= 2 && ord($buffer[1]) === 0x00;
        $data = $isUtf16 ? mb_convert_encoding($buffer, 'UTF-8', 'UTF-16LE') : $buffer;
        // Убрать BOM
        if (($c0 = ord($data[0] ?? "\x00")) === 0xEF && ($c1 = ord($data[1] ?? "\x00")) === 0xBB && ($c2 = ord($data[2] ?? "\x00")) === 0xBF) {
            $data = substr($data, 3);
        } elseif ($data !== '' && (ord($data[0]) === 0xFE || ord($data[0]) === 0xFF)) {
            $data = substr($data, 1);
        }

        return str_replace("\r", '', $data);
    }
}
