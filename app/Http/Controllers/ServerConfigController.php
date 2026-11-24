<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigFileRequest;
use App\Services\Scum\ServerConfigService;
use App\Services\Security\AuditService;
use Illuminate\Support\Facades\Cache;

/**
 * Редактор конфигурационных файлов сервера по FTP
 * (ServerSettings.ini, EconomyOverride.json, RaidSettings.json и др.)
 */
class ServerConfigController extends Controller
{
    private const LISTING_CACHE_KEY = 'scum_config_listing';

    private const LISTING_CACHE_TTL = 30;

    public function __construct(
        private ServerConfigService $config,
        private AuditService $audit,
    ) {}

    public function index()
    {
        $cached = Cache::get(self::LISTING_CACHE_KEY);
        if (is_array($cached) && isset($cached['configDir'], $cached['files'])) {
            return view('config-files.index', [
                'enabled' => true,
                'connected' => true,
                'error' => null,
                'configDir' => $cached['configDir'],
                'files' => $cached['files'],
                'backups' => $this->config->listBackups(),
            ]);
        }
        $this->config->reloadConfig();
        $enabled = $this->config->enabled();
        $connected = false;
        $error = null;
        $configDir = null;
        $files = [];
        if ($enabled) {
            $conn = $this->config->connect();
            $connected = ! empty($conn['ok']);
            $error = $conn['error'] ?? null;
            if ($connected) {
                $configDir = $this->config->detectConfigDir();
                $files = $this->config->listConfigFiles();
            }
        }
        if ($connected && $configDir !== null) {
            Cache::put(self::LISTING_CACHE_KEY, ['configDir' => $configDir, 'files' => $files], self::LISTING_CACHE_TTL);
        }

        return view('config-files.index', [
            'enabled' => $enabled,
            'connected' => $connected,
            'error' => $error,
            'configDir' => $configDir,
            'files' => $files,
            'backups' => $this->config->listBackups(),
        ]);
    }

    public function read(ConfigFileRequest $request)
    {
        $this->config->reloadConfig();
        $this->config->connect();

        return response()->json($this->config->readFile((string) $request->input('file', '')));
    }

    public function save(ConfigFileRequest $request)
    {
        $file = (string) $request->input('file', '');
        $content = (string) $request->input('content', '');
        $encoding = (string) $request->input('encoding', 'utf-8');
        $this->config->reloadConfig();
        $this->config->connect();
        $result = $this->config->saveFile($file, $content, $encoding);
        Cache::forget(self::LISTING_CACHE_KEY);
        $message = $result['ok']
            ? "Файл {$file} сохранён на сервер"
            : "Не удалось сохранить {$file}: ".($result['error'] ?? 'неизвестная ошибка');

        $this->audit->log('config.file.save', 'config', $result['ok'] ? "Конфигурационный файл {$file} успешно обновлён" : "Ошибка сохранения конфиг-файла {$file}", [
            'file' => $file,
            'bytes' => strlen($content),
            'encoding' => $encoding,
            'error' => $result['error'] ?? null,
        ], $result['ok'] ? 'success' : 'failed');

        return response()->json([
            'success' => $result['ok'],
            'message' => $message,
            'backups' => $this->config->listBackups(),
        ]);
    }

    public function backup(ConfigFileRequest $request)
    {
        $file = (string) $request->input('file', '');
        $this->config->reloadConfig();
        $this->config->connect();
        $result = $this->config->createBackup($file);
        $message = $result['ok']
            ? "Создан локальный бэкап для {$file}"
            : 'Не удалось создать бэкап: '.($result['error'] ?? 'неизвестная ошибка');

        $this->audit->log('config.file.backup', 'config', "Создание бэкапа для файла {$file}", [
            'file' => $file,
            'backup_path' => $result['path'] ?? null,
        ], $result['ok'] ? 'success' : 'failed');

        return response()->json([
            'success' => $result['ok'],
            'message' => $message,
            'backups' => $this->config->listBackups(),
        ]);
    }

    public function restore(ConfigFileRequest $request)
    {
        $path = (string) $request->input('path', '');
        $this->config->reloadConfig();
        $this->config->connect();
        $result = $this->config->restoreBackup($path);
        Cache::forget(self::LISTING_CACHE_KEY);
        $message = $result['ok']
            ? 'Бэкап восстановлен на сервер'
            : 'Не удалось восстановить: '.($result['error'] ?? 'неизвестная ошибка');

        $this->audit->log('config.file.restore', 'config', "Восстановление конфигурации из бэкапа {$path}", [
            'path' => $path,
            'error' => $result['error'] ?? null,
        ], $result['ok'] ? 'success' : 'failed');

        return response()->json([
            'success' => $result['ok'],
            'message' => $message,
            'backups' => $this->config->listBackups(),
        ]);
    }
}
