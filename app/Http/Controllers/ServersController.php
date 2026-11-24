<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerRequest;
use App\Services\Scum\DaemonService;
use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\RconService;
use App\Services\Scum\RemoteClient;
use App\Services\Scum\ServerService;
use App\Services\Scum\SettingsService;
use App\Services\System\SystemMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServersController extends Controller
{
    public function __construct(
        private ServerService $servers,
        private SettingsService $settings,
        private RconService $rcon,
        private FtpLogWatcherService $watcher,
        private DaemonService $daemons,
        private SystemMetricsService $metrics,
    ) {}

    public function index()
    {
        $servers = $this->servers->all();
        $activeServerId = (int) session('active_server_id');

        // После запуска панели активный сервер мог не сохраниться в сессии.
        // Автоматически активируем первый включённый сервер, если активного нет.
        if ($activeServerId <= 0 || ! $this->servers->get($activeServerId)) {
            foreach ($servers as $server) {
                if ((int) $server['enabled']) {
                    $this->activateServer((int) $server['id']);
                    $activeServerId = (int) $server['id'];
                    break;
                }
            }
        }

        $activeServer = $this->servers->get($activeServerId);

        $ftpDaemon = $this->daemons->status(DaemonService::WATCH);
        $rconDaemon = $this->daemons->status(DaemonService::RCON);
        $ftpDaemonRunning = ! empty($ftpDaemon['running']);
        $rconDaemonRunning = ! empty($rconDaemon['running']);

        $statuses = [];
        $activeStatus = null;
        foreach ($servers as $server) {
            $isActive = (int) $server['id'] === $activeServerId;
            $statuses[(int) $server['id']] = $this->statusFor((array) $server, $isActive);
            if ($isActive) {
                $activeStatus = $statuses[(int) $server['id']];
            }
        }

        // Пароли не должны попадать в window.SERVERS (см. serverForm.js) — маскируем.
        $viewServers = array_map(fn ($s) => [...$s, 'ftp_password' => '', 'rcon_password' => ''], $servers);

        $config = $this->settings->getAppConfig();

        return view('servers.index', [
            'servers' => $viewServers,
            'activeServer' => $activeServer,
            'activeId' => $activeServerId,
            'activeServerId' => $activeServerId,
            'ftpDaemonRunning' => $ftpDaemonRunning,
            'rconDaemonRunning' => $rconDaemonRunning,
            'statuses' => $statuses,
            'activeStatus' => $activeStatus,
            'lolkaConfig' => $config['lolka'] ?? [],
            'pluginsConfig' => $config['plugins'] ?? [],
            'hostMetrics' => $this->metrics->getMetrics(),
        ]);
    }

    /**
     * Статус подключений RCON и FTP Watcher для карточки сервера.
     */
    private function statusFor(array $server, bool $isActive): array
    {
        $status = [
            'rcon' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false]],
            'ftp' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false]],
        ];

        $appConfig = $this->settings->getAppConfig();
        $activeRcon = $appConfig['rcon'] ?? [];
        $activeFtp = $appConfig['hostFtp'] ?? [];

        if (! empty($server['rcon_host'])) {
            $status['rcon']['configured'] = true;
            $matchesActive = ! empty($activeRcon['host'])
                && $activeRcon['host'] === $server['rcon_host']
                && (int) ($activeRcon['port'] ?? 0) === (int) ($server['rcon_port'] ?? 0);

            if ($isActive || $matchesActive) {
                $r = $this->rcon->status();
                $status['rcon']['connected'] = ! empty($r['connected']);
                $status['rcon']['error'] = ! empty($r['connected']) ? null : 'Не подключено';
                $status['rcon']['daemon'] = $r['daemon'] ?? $this->daemons->status(DaemonService::RCON);
            } else {
                $r = Cache::remember('srv_probe_rcon_'.$server['id'], 30, function () use ($server) {
                    return $this->rcon->probe(
                        $server['rcon_host'],
                        (int) $server['rcon_port'],
                        $server['rcon_password'] ?? ''
                    );
                });
                $status['rcon']['connected'] = ! empty($r['success']);
                $status['rcon']['error'] = $r['error'] ?? null;
            }
        }

        if (! empty($server['ftp_host'])) {
            $status['ftp']['configured'] = true;
            $matchesActive = ! empty($activeFtp['host'])
                && $activeFtp['host'] === $server['ftp_host']
                && (int) ($activeFtp['port'] ?? 0) === (int) ($server['ftp_port'] ?? 21);

            if ($isActive || $matchesActive) {
                $f = $this->watcher->status();
                $status['ftp']['connected'] = ! empty($f['connected']);
                $status['ftp']['error'] = $f['lastError'] ?? null;
                $status['ftp']['daemon'] = $f['daemon'] ?? $this->daemons->status(DaemonService::WATCH);
            } else {
                $f = Cache::remember('srv_probe_ftp_'.$server['id'], 30, function () use ($server) {
                    $client = new RemoteClient;
                    $ok = $client->connect($this->servers->toHostFtp($server));
                    $error = $ok ? null : $client->lastError();
                    if ($ok) {
                        $client->disconnect();
                    }

                    return ['ok' => $ok, 'error' => $error];
                });
                $status['ftp']['connected'] = ! empty($f['ok']);
                $status['ftp']['error'] = $f['error'] ?? null;
            }
        }

        return $status;
    }

    public function store(ServerRequest $request)
    {
        $validated = $request->validated();
        $validated['enabled'] = $request->has('enabled') && $request->input('enabled') === '1' ? 1 : 0;

        $this->savePanelExtras($request);

        $id = $this->servers->create($validated);

        // Сразу активируем добавленный сервер — кнопка «Активировать» не нужна
        $this->activateServer($id);

        return redirect()->route('servers.index')->with('success', 'Сервер добавлен и активирован');
    }

    public function update(int $id, ServerRequest $request)
    {
        $server = $this->servers->get($id);
        if (! $server) {
            return back()->with('error', 'Сервер не найден');
        }
        $validated = $request->validated();
        $validated['enabled'] = $request->has('enabled') && $request->input('enabled') === '1' ? 1 : 0;

        $this->savePanelExtras($request);

        $this->servers->update($id, $validated);

        // Если редактируем активный сервер — сразу применяем конфиг в настройках
        if ((int) session('active_server_id') === $id) {
            $this->activateServer($id);
        }

        return back()->with('success', 'Сервер «'.($validated['name'] ?: $server['name']).'» обновлён');
    }

    public function activate(int $id)
    {
        $server = $this->servers->get($id);
        if (! $server || ! (int) $server['enabled']) {
            return back()->with('error', 'Сервер не найден или отключен');
        }
        $this->activateServer($id);

        return back()->with('success', 'Сервер «'.$server['name'].'» теперь активен');
    }

    public function enter(int $id)
    {
        $server = $this->servers->get($id);
        if (! $server) {
            return redirect()->route('servers.index')->with('error', 'Сервер не найден');
        }
        $this->activateServer($id);

        // Авто-подключение к RCON (выполнено в activateServer)
        $rcon = $this->servers->toRcon($server);
        if (empty($rcon['host'])) {
            return redirect()->route('players.index')->with('info', 'Панель сервера «'.$server['name'].'» — RCON адрес не указан');
        }
        if ($this->rcon->status()['connected'] ?? false) {
            return redirect()->route('players.index')->with('success', 'Панель сервера «'.$server['name'].'» — RCON подключен');
        }

        return redirect()->route('players.index')->with('error', 'Не удалось подключиться к RCON ('.$rcon['host'].':'.$rcon['port'].')');
    }

    public function destroy(int $id)
    {
        $server = $this->servers->get($id);
        $this->servers->delete($id);
        if ((int) session('active_server_id') === $id) {
            session(['active_server_id' => null]);
            $this->settings->saveAppConfig([
                'hostFtp' => ['enabled' => false, 'host' => '', 'port' => 21, 'username' => '', 'password' => '', 'protocol' => 'ftp'],
                'rcon' => ['enabled' => false, 'host' => '', 'port' => 28015, 'password' => ''],
            ]);
            $this->watcher->disconnect();
            $this->rcon->disconnect();
        }

        return back()->with('success', 'Сервер «'.($server['name'] ?? '').'» удалён');
    }

    /**
     * Сохраняет глобальные настройки из формы сервера: Lolka и вкл/выкл плагинов.
     */
    private function savePanelExtras(ServerRequest $request): void
    {
        $webhooksJson = $request->input('lolka_webhooks');
        $webhooks = [];
        if (is_string($webhooksJson) && trim($webhooksJson) !== '') {
            $decoded = json_decode($webhooksJson, true);
            if (is_array($decoded)) {
                $webhooks = $decoded;
            }
        } elseif (is_array($webhooksJson)) {
            $webhooks = $webhooksJson;
        }

        $lolkaData = [
            'enabled' => true,
            'webhooks' => $webhooks,
        ];

        // Сохраняем ключи и в корне lolka для полной совместимости
        foreach ($webhooks as $wh) {
            if (! empty($wh['key']) && ! empty($wh['url'])) {
                $lolkaData[$wh['key']] = (string) $wh['url'];
            }
        }

        $this->settings->saveAppConfig([
            'lolka' => $lolkaData,
        ]);

        $plugins = $this->settings->getAppConfig()['plugins'] ?? [];
        foreach (['teleport', 'vip', 'saveHome', 'airdrop', 'rewards', 'packs', 'insurance'] as $key) {
            $plugins[$key]['enabled'] = $request->has('plugin_'.$key) && $request->input('plugin_'.$key) === '1';
        }
        $this->settings->saveAppConfig(['plugins' => $plugins]);
    }

    private function activateServer(int $id): void
    {
        $server = $this->servers->get($id);
        if (! $server) {
            return;
        }
        session(['active_server_id' => $id]);
        $this->settings->saveAppConfig([
            'hostFtp' => $this->servers->toHostFtp($server),
            'rcon' => $this->servers->toRcon($server),
        ]);

        // Авто-подключение FTP Watcher по конфигу активированного сервера
        $ftp = $this->servers->toHostFtp($server);
        if (! empty($ftp['host'])) {
            $this->watcher->reloadConfig();
            $this->watcher->connect();
        } else {
            $this->watcher->disconnect();
        }

        // Авто-подключение RCON (только если реально нет активного соединения —
        // единственный слот обычно держит демон, и попытка второго приводила к Auth timeout)
        $rconCfg = $this->servers->toRcon($server);
        if (! empty($rconCfg['host'])) {
            if (! $this->rcon->isConnected()) {
                $this->rcon->connect($rconCfg['host'], (int) $rconCfg['port'], $rconCfg['password'] ?? '');
            }
        }
    }

    /**
     * Быстрая AJAX-проверка параметров FTP из модального окна настроек сервера.
     */
    public function testFtp(Request $request)
    {
        $validated = $request->validate([
            'ftp_protocol' => 'nullable|string|in:ftp,sftp',
            'ftp_host' => 'required|string|max:255',
            'ftp_port' => 'nullable|integer|min:1|max:65535',
            'ftp_username' => 'nullable|string|max:255',
            'ftp_password' => 'nullable|string|max:255',
            'server_id' => 'nullable|integer',
        ]);

        if (empty($validated['ftp_password']) && ! empty($validated['server_id'])) {
            $existing = $this->servers->get((int) $validated['server_id']);
            if ($existing && ! empty($existing['ftp_password'])) {
                $validated['ftp_password'] = $existing['ftp_password'];
            }
        }

        $config = [
            'protocol' => $validated['ftp_protocol'] ?? 'ftp',
            'host' => $validated['ftp_host'],
            'port' => (int) ($validated['ftp_port'] ?: 21),
            'username' => $validated['ftp_username'] ?? '',
            'password' => $validated['ftp_password'] ?? '',
        ];

        $client = new RemoteClient;
        $ok = $client->connect($config);
        $error = $ok ? null : $client->lastError();
        if ($ok) {
            $client->disconnect();
        }

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'FTP соединение успешно установлено!' : ($error ?: 'Не удалось подключиться к FTP'),
            'error' => $error,
        ]);
    }

    /**
     * Быстрая AJAX-проверка параметров RCON из модального окна настроек сервера.
     */
    public function testRcon(Request $request)
    {
        $validated = $request->validate([
            'rcon_host' => 'required|string|max:255',
            'rcon_port' => 'required|integer|min:1|max:65535',
            'rcon_password' => 'nullable|string|max:255',
            'server_id' => 'nullable|integer',
        ]);

        if (empty($validated['rcon_password']) && ! empty($validated['server_id'])) {
            $existing = $this->servers->get((int) $validated['server_id']);
            if ($existing && ! empty($existing['rcon_password'])) {
                $validated['rcon_password'] = $existing['rcon_password'];
            }
        }

        // Если хост и порт совпадают с текущим активным RCON и сокет уже удерживает демон
        $appRcon = $this->settings->getAppConfig()['rcon'] ?? [];
        if (! empty($appRcon['host']) && $appRcon['host'] === $validated['rcon_host'] && (int) ($appRcon['port'] ?? 0) === (int) $validated['rcon_port']) {
            $status = $this->rcon->status();
            if (! empty($status['connected'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'RCON соединение активно (подключено через постоянный сокет демона)!',
                ]);
            }
        }

        $result = $this->rcon->probe(
            $validated['rcon_host'],
            (int) $validated['rcon_port'],
            $validated['rcon_password'] ?? ''
        );

        return response()->json([
            'success' => ! empty($result['success']),
            'message' => ! empty($result['success']) ? 'RCON соединение успешно проверено!' : ($result['error'] ?? 'Не удалось подключиться к RCON'),
            'error' => $result['error'] ?? null,
        ]);
    }
}
