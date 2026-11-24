<?php

namespace App\Http\Controllers;

use App\Services\Scum\CatalogService;
use App\Services\Scum\DaemonService;
use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbService;
use App\Services\Scum\ScumMapService;
use App\Services\Scum\SettingsService;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /** Доступные темы карты: имя файла без расширения → подпись */
    private const MAP_THEMES = [
        'islandmap' => 'Цветная',
        'islandmapgrey' => 'Серая',
        'islandmaporange' => 'Оранжевая',
    ];

    private function mapImagePath(): ?string
    {
        foreach (array_keys(self::MAP_THEMES) as $base) {
            $path = storage_path('app/scum/images/map/'.$base.'.jpg');
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Отдаёт файл карты (используется как фон SVG).
     */
    public function image(Request $request)
    {
        $theme = (string) $request->query('theme', 'islandmap');
        if (! isset(self::MAP_THEMES[$theme])) {
            $theme = 'islandmap';
        }
        $path = storage_path('app/scum/images/map/'.$theme.'.jpg');
        if (! is_file($path)) {
            $path = $this->mapImagePath();
        }
        if (! $path || ! is_file($path)) {
            abort(404, 'Карта не найдена');
        }

        return response()->file($path, ['Cache-Control' => 'public, max-age=3600']);
    }

    public function __construct(
        private ScumDbService $scumDb,
        private PluginService $plugins,
        private RconService $rcon,
        private SettingsService $settings,
        private FtpLogWatcherService $ftp,
    ) {}

    /**
     * Карта: игроки (онлайн), транспорт и флаги.
     */
    public function index()
    {
        $local = storage_path('app/scum/SCUM.db');
        $config = $this->settings->getAppConfig();
        if (file_exists($local)) {
            $this->scumDb->setDbPath($local);
            // Если FTP-копия устарела — попробуем обновить (как на странице SCUM.db).
            // При запущенном демоне scum:watch копию держит он — FTP в запросе не трогаем.
            if (! empty($config['hostFtp']['enabled']) && ! empty($config['hostFtp']['host'])
                && ! app(DaemonService::class)->isRunning(DaemonService::WATCH)
                && (time() - (int) @filemtime($local)) >= 300) {
                try {
                    $this->ftp->reloadConfig();
                    $this->ftp->syncScumDb(); // при успехе сам переоткроет PDO
                } catch (\Throwable $e) {
                    // тихо используем локальную копию
                }
            }
        }
        $available = $this->scumDb->isAvailable();

        // Игроки онлайн с координатами
        $players = $this->plugins->listOnlinePlayers();
        $players = array_values(array_filter($players, fn ($p) => ! empty($p['location']) && (float) $p['location']['x'] !== 0.0));

        // Уточняем славу/деньги/золото из SCUM.db (RCON даёт славу = 0 в новом формате)
        if ($available && ! empty($players)) {
            foreach ($players as &$p) {
                $steamId = (string) ($p['steamId'] ?? '');
                if ($steamId === '') {
                    continue;
                }
                try {
                    $wallet = $this->scumDb->getWallet($steamId);
                    if ($wallet) {
                        $p['money'] = (float) ($wallet['normalBalance'] ?? $p['money'] ?? 0);
                        $p['gold'] = (float) ($wallet['goldBalance'] ?? $p['gold'] ?? 0);
                        $p['fame'] = (float) ($wallet['famePoints'] ?? $p['fame'] ?? 0);
                        $p['lastLogin'] = (int) ($wallet['lastLogin'] ?? 0);
                    }
                } catch (\Throwable $e) {
                    // оставляем данные из RCON
                }
            }
            unset($p);
        }

        return view('map.index', [
            'players' => $players,
            'vehicles' => $available ? $this->scumDb->getVehicles() : [],
            'flags' => $available ? $this->scumDb->getFlags() : [],
            'zones' => $available ? $this->scumDb->getMapZones() : [],
            'chests' => $this->ftp->getChestData(),
            'markers' => app(CatalogService::class)->getMarkers(limit: 0),
            'available' => $available,
            'rconConnected' => $this->rcon->isConnected(),
            'mapSize' => 4096,
            'minX' => ScumMapService::MIN_X,
            'maxX' => ScumMapService::MAX_X,
            'minY' => ScumMapService::MIN_Y,
            'maxY' => ScumMapService::MAX_Y,
        ]);
    }

    public function stats()
    {
        $local = storage_path('app/scum/SCUM.db');
        if (is_file($local)) {
            $this->scumDb->setDbPath($local);
        }
        $available = $this->scumDb->isAvailable();
        $players = $this->plugins->listOnlinePlayers();
        $players = array_values(array_filter($players, fn ($p) => ! empty($p['location']) && (float) $p['location']['x'] !== 0.0));
        $vehicles = $available ? $this->scumDb->getVehicles() : [];
        $flags = $available ? $this->scumDb->getFlags() : [];
        $chests = $this->ftp->getChestData();
        $broken = 0;
        $forbidden = 0;
        foreach ($vehicles as $v) {
            if (($v['functional'] ?? true) === false) {
                $broken++;
            }
            if ((int) ($v['forbiddenZoneSeconds'] ?? 0) > 0) {
                $forbidden++;
            }
        }
        $contested = 0;
        foreach ($flags as $f) {
            $ot = (int) ($f['overtakeEndTime'] ?? 0);
            if ($ot > 0 && $ot > time()) {
                $contested++;
            }
        }

        return response()->json([
            'players' => count($players),
            'vehicles' => count($vehicles),
            'broken' => $broken,
            'forbidden' => $forbidden,
            'flags' => count($flags),
            'contested' => $contested,
            'chests' => count($chests['chests'] ?? []),
            'takeover' => count($chests['takeovers'] ?? []),
            'rcon' => $this->rcon->isConnected(),
            'available' => $available,
        ]);
    }

    public function chest(string $entityId)
    {
        $local = storage_path('app/scum/SCUM.db');
        if (! is_file($local)) {
            return response()->json(['ok' => false, 'error' => 'Локальная копия SCUM.db недоступна — обновите данные через FTP-демон']);
        }
        $this->scumDb->setDbPath($local);
        if (! $this->scumDb->isAvailable()) {
            return response()->json(['ok' => false, 'error' => 'SCUM.db недоступна']);
        }
        $data = $this->scumDb->getContainerContents($entityId);
        if (empty($data['found'])) {
            return response()->json(['ok' => false, 'error' => $data['error'] ?? 'Контейнер не найден'], 404);
        }

        return response()->json(['ok' => true] + $data);
    }
}
