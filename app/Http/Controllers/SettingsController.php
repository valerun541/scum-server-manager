<?php

namespace App\Http\Controllers;

use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\RconService;
use App\Services\Scum\SettingsService;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private RconService $rcon,
        private FtpLogWatcherService $watcher,
    ) {}

    public function connectRcon()
    {
        $config = $this->settings->getAppConfig();
        $rcon = $config['rcon'] ?? [];
        if (empty($rcon['host'])) {
            return back()->with('error', 'Не указан адрес RCON');
        }
        $result = $this->rcon->connect($rcon['host'], (int) $rcon['port'], $rcon['password'] ?? '');

        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'RCON подключён' : ($result['error'] ?? 'Ошибка подключения'));
    }

    public function disconnectRcon()
    {
        $this->rcon->disconnect();

        return back()->with('success', 'RCON отключён');
    }

    public function connectFtp()
    {
        $config = $this->settings->getAppConfig();
        $ftp = $config['hostFtp'] ?? [];
        if (empty($ftp['host'])) {
            return back()->with('error', 'Не указан адрес FTP');
        }
        $result = $this->watcher->connect();

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'FTP подключён' : ($result['error'] ?? 'Ошибка подключения FTP'));
    }

    public function disconnectFtp()
    {
        $this->watcher->disconnect();

        return back()->with('success', 'FTP подключение закрыто');
    }
}
