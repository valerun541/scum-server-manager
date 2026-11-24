<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;

/**
 * Чтение/правка конфигурационных файлов сервера SCUM через FTP
 * (ServerSettings.ini, EconomyOverride.json, RaidSettings.json и др.)
 * — порт LootEditor/GameSettings/EconomySettings/RaidSettings из оригинала.
 * Перед каждой заливкой старая версия сохраняется локально в бэкапы.
 */
class ServerConfigService
{
    private array $config;

    private ?string $configDir = null;

    public function __construct(
        private SettingsService $settings,
        private RemoteClient $remote,
    ) {
        $this->config = $settings->getAppConfig()['hostFtp'] ?? [];
    }

    public function reloadConfig(): void
    {
        $this->config = $this->settings->getAppConfig()['hostFtp'] ?? [];
    }

    public function enabled(): bool
    {
        return ! empty($this->config['enabled']) && ! empty($this->config['host']);
    }

    public function connect(): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'FTP не настроен — укажите доступ в настройках хоста'];
        }
        $ok = $this->remote->connect($this->config);

        return ['ok' => $ok, 'error' => $ok ? null : $this->remote->lastError()];
    }

    /**
     * Кандидаты на каталог конфигов WindowsServer (в порядке приоритета).
     * Пользовательский путь из настроек FTP учитывается первым.
     */
    public function candidateDirs(): array
    {
        $candidates = [];
        if (! empty($this->config['configPath'])) {
            $candidates[] = trim((string) $this->config['configPath'], '/');
        }
        $candidates = array_merge($candidates, [
            'SCUM/Saved/Config/WindowsServer',
            'Saved/Config/WindowsServer',
            'SCUM/Saved/Config',
            'Saved/Config',
        ]);

        return array_values(array_unique($candidates));
    }

    /**
     * Определяем каталог конфигов: ищем файлы, характерные для конфигурации сервера.
     */
    public function detectConfigDir(): ?string
    {
        if ($this->configDir !== null) {
            return $this->configDir;
        }
        if (! $this->remote->isConnected()) {
            return null;
        }
        foreach ($this->candidateDirs() as $dir) {
            try {
                foreach (['ServerSettings.ini', 'Game.ini', 'Engine.ini', 'EconomyOverride.json'] as $probe) {
                    if ($this->remote->fileExists($dir.'/'.$probe)) {
                        return $this->configDir = $dir;
                    }
                }
            } catch (\Throwable $e) {
                // каталог недоступен — пробуем следующий кандидат
            }
        }

        return $this->configDir = null;
    }

    /**
     * @return array{name:string,size:int}[]
     *
     * Хостинг временами отказывает в data-соединении при конкуренции с демоном
     * слежения (ftp_rawlist => false, клиент помечается отключённым).
     * В таких случаях — переподключаемся и повторяем.
     */
    public function listConfigFiles(): array
    {
        $dir = $this->detectConfigDir();
        if (! $dir) {
            return [];
        }
        $allowed = ['ini', 'json', 'cfg', 'txt', 'xml'];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $listed = $this->remote->listFiles($dir);
                if (! empty($listed) || $this->remote->isConnected()) {
                    $out = [];
                    foreach ($listed as $f) {
                        $name = $f['name'];
                        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed, true)) {
                            continue;
                        }
                        $out[] = ['name' => $name, 'size' => (int) $f['size']];
                    }
                    usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

                    return $out;
                }
            } catch (\Throwable $e) {
                Log::channel('scum')->warning('[ServerConfig] list attempt '.($attempt + 1).' failed: '.$e->getMessage());
            }
            if ($attempt < 2) {
                Log::channel('scum')->info('[ServerConfig] list retry '.($attempt + 1));
                $this->connect();
            }
        }

        return [];
    }

    private function validName(string $name): bool
    {
        return $name !== '' && $name !== '.' && $name !== '..' && strpbrk($name, '/\\') === false
            && preg_match('/^[a-zA-Z0-9._-]+$/', $name) === 1;
    }

    /** @return array{ok:bool,error?:string,content?:string,encoding?:string} */
    public function readFile(string $name): array
    {
        $dir = $this->detectConfigDir();
        if (! $dir || ! $this->validName($name)) {
            return ['ok' => false, 'error' => 'Недопустимое имя файла'];
        }
        $lastError = '';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $buffer = $this->remote->downloadBuffer($dir.'/'.$name);
                if ($buffer === '' && $this->remote->lastError() !== '') {
                    throw new \RuntimeException($this->remote->lastError());
                }
                $isUtf16 = strlen($buffer) >= 2 && ord($buffer[1]) === 0x00;
                $content = $this->remote->decodeBuffer($buffer);

                return ['ok' => true, 'content' => $content, 'encoding' => $isUtf16 ? 'utf-16le' : 'utf-8'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::channel('scum')->info('[ServerConfig] read retry: '.$lastError);
                $this->connect();
            }
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /** @return array{ok:bool,error?:string} */
    public function saveFile(string $name, string $content, string $encoding = 'utf-8'): array
    {
        $dir = $this->detectConfigDir();
        if (! $dir || ! $this->validName($name)) {
            return ['ok' => false, 'error' => 'Недопустимое имя файла'];
        }
        $this->backupLocal($name);
        $data = $content;
        if ($encoding === 'utf-16le') {
            $data = "\xFF\xFE".mb_convert_encoding($data, 'UTF-16LE', 'UTF-8');
        }
        $lastError = 'FTP upload failed';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($this->remote->uploadBuffer($dir.'/'.$name, $data)) {
                return ['ok' => true];
            }
            $lastError = $this->remote->lastError() ?: 'FTP upload failed';
            Log::channel('scum')->info('[ServerConfig] save retry: '.$lastError);
            $this->connect();
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /** @return array{ok:bool,error?:string} */
    public function restoreBackup(string $path): array
    {
        $dir = $this->detectConfigDir();
        $base = realpath($this->backupDir());
        $rp = realpath($path);
        if (! $dir || $base === false || $rp === false || ! str_starts_with($rp, $base)) {
            return ['ok' => false, 'error' => 'Недопустимый путь бэкапа'];
        }
        $rel = substr($rp, strlen($base) + 1);
        $parts = explode('/', str_replace('\\', '/', $rel));
        $file = $parts[0];
        if (! $this->validName($file)) {
            return ['ok' => false, 'error' => 'Недопустимое имя файла'];
        }
        $data = @file_get_contents($rp);
        if ($data === false) {
            return ['ok' => false, 'error' => 'Не удалось прочитать бэкап'];
        }
        $lastError = 'FTP upload failed';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($this->remote->uploadBuffer($dir.'/'.$file, $data)) {
                return ['ok' => true];
            }
            $lastError = $this->remote->lastError() ?: 'FTP upload failed';
            Log::channel('scum')->info('[ServerConfig] restore retry: '.$lastError);
            $this->connect();
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /* ===== Локальные бэкапы ===== */

    public function backupDir(): string
    {
        return storage_path('app/scum/config_backups');
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    }

    public function createBackup(string $name): array
    {
        $dir = $this->detectConfigDir();
        if (! $dir || ! $this->validName($name)) {
            return ['ok' => false, 'error' => 'Недопустимое имя файла'];
        }
        $this->backupLocal($name);

        return ['ok' => true, 'backups' => $this->listBackups()];
    }

    private function backupLocal(string $name): void
    {
        $dir = $this->detectConfigDir();
        if (! $dir) {
            return;
        }
        $dirLocal = $this->backupDir().'/'.$this->safeName($name);
        if (! is_dir($dirLocal)) {
            @mkdir($dirLocal, 0755, true);
        }
        try {
            $data = $this->remote->downloadBuffer($dir.'/'.$name);
            @file_put_contents($dirLocal.'/'.date('Ymd_His').'.bak', $data);
        } catch (\Throwable $e) {
            return;
        }
        // Ротация: храним не более 20 бэкапов на файл.
        $baks = glob($dirLocal.'/*.bak');
        if (is_array($baks) && count($baks) > 20) {
            $old = array_slice($baks, 0, count($baks) - 20);
            foreach ($old as $f) {
                @unlink($f);
            }
        }
    }

    /** @return array{file:string,stamp:string,size:int,path:string}[] */
    public function listBackups(): array
    {
        $dir = $this->backupDir();
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir.'/*/*.bak') ?: [] as $f) {
            $rel = substr($f, strlen($dir) + 1);
            [$file, $stamp] = explode('/', $rel.'/');
            $out[] = [
                'file' => $file,
                'stamp' => str_replace('.bak', '', $stamp),
                'size' => (int) @filesize($f),
                'path' => $f,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['stamp'], $a['stamp']));

        return $out;
    }
}
