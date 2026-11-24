<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerActionRequest;
use App\Services\Player\PlayerActionService;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbService;
use App\Support\VehicleParser;
use App\Support\WhoisParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlayersController extends Controller
{
    public function __construct(
        private PluginService $plugins,
        private RconService $rcon,
        private ScumDbService $scumDb,
    ) {}

    public function index(Request $request)
    {
        $online = $this->plugins->listOnlinePlayers();

        // ListPlayers (v0.4.6) не содержит fame — дозаполняем из Whois с кешем 30 сек
        foreach ($online as &$p) {
            $sid = (string) $p['steamId'];
            $p['fame'] = Cache::remember('player_fame_'.$sid, 30, function () use ($sid) {
                $r = $this->rcon->sendCommand("Whois {$sid}");
                if (! $r['success'] || empty($r['response'])) {
                    return 0.0;
                }

                return WhoisParser::extractFame($r['response']);
            });
        }
        unset($p);

        $isConnected = $this->rcon->isConnected();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'onlineCount' => count($online),
                'rconConnected' => $isConnected,
                'players' => array_values(array_map(function ($p) {
                    return [
                        'steamId' => (string) ($p['steamId'] ?? ''),
                        'name' => (string) ($p['name'] ?? 'Unknown'),
                        'money' => (float) ($p['money'] ?? 0),
                        'gold' => (float) ($p['gold'] ?? 0),
                        'fame' => (float) ($p['fame'] ?? 0),
                        'location' => $p['location'] ?? null,
                        'x' => isset($p['location']['x']) ? round((float) $p['location']['x']) : null,
                        'y' => isset($p['location']['y']) ? round((float) $p['location']['y']) : null,
                        'z' => isset($p['location']['z']) ? round((float) $p['location']['z']) : null,
                        'profileUrl' => route('scumdb.player', $p['steamId'] ?? ''),
                        'whoisUrl' => route('players.whois', $p['steamId'] ?? ''),
                    ];
                }, $online)),
            ]);
        }

        return view('players.index', [
            'online' => $online,
            'rconConnected' => $isConnected,
        ]);
    }

    public function whois(Request $request, string $steamId)
    {
        $result = $this->rcon->sendCommand("Whois {$steamId}");
        $data = [
            'steamId' => $steamId,
            'response' => $result['success'] ? ($result['response'] ?? '') : '',
            'error' => $result['success'] ? null : ($result['error'] ?? ''),
            'whois' => [],
            'rconConnected' => $this->rcon->isConnected(),
        ];

        if ($result['success'] && ! empty($result['response'])) {
            $data['whois'] = WhoisParser::parse($result['response']);
        }

        if ($request->query('fragment') === '1') {
            return view('players.whois-fragment', $data);
        }

        return view('players.whois', $data);
    }

    /**
     * Транспорт игрока: парсит ListSpawnedVehicles и фильтрует по владельцу.
     */
    public function vehicles(string $steamId)
    {
        $r = $this->rcon->sendCommand('ListSpawnedVehicles');
        $vehicles = (! empty($r['success']) && ! empty($r['response']))
            ? VehicleParser::parseList($r['response'])
            : [];

        $profileId = null;
        $prisonerId = null;
        $dbName = null;

        try {
            $this->scumDb->open();
            $player = $this->scumDb->getPlayerBySteamId($steamId);
            if ($player) {
                $profileId = $player['profileId'];
                $prisonerId = $player['prisonerId'];
                $dbName = $player['name'];
            }
        } catch (\Throwable) {
            // SCUM.db недоступна — фильтруем только по имени из RCON
        }

        if (! $dbName) {
            $w = $this->rcon->sendCommand("Whois {$steamId}");
            if ($w['success'] && ! empty($w['response'])) {
                $dbName = WhoisParser::extractName($w['response']);
            }
        }

        $nameLower = $dbName ? mb_strtolower($dbName) : null;

        $owned = array_values(array_filter($vehicles, function ($v) use ($profileId, $prisonerId, $nameLower) {
            if ($v['ownerDbId'] !== null && ($v['ownerDbId'] === $profileId || $v['ownerDbId'] === $prisonerId)) {
                return true;
            }
            if ($v['ownerName'] && $nameLower && mb_strtolower($v['ownerName']) === $nameLower) {
                return true;
            }

            return false;
        }));

        return response()->json([
            'vehicles' => array_map(fn ($v) => [
                'entityId' => $v['entityId'],
                'asset' => $v['asset'],
                'name' => ($v['customName'] && $v['customName'] !== '-') ? $v['customName'] : ($v['asset'] ?: ('ID '.$v['entityId'])),
                'x' => $v['x'],
                'y' => $v['y'],
                'z' => $v['z'],
            ], $owned),
        ]);
    }

    /**
     * Действия над игроком (делегируются в PlayerActionService).
     * Работает как с обычной формой, так и с AJAX/JSON.
     */
    public function action(PlayerActionRequest $request, PlayerActionService $playerActions)
    {
        $steamId = (string) $request->input('steam_id', '');
        if (! $steamId) {
            return $this->respondJson($request, false, 'Не указан SteamID');
        }

        $result = $playerActions->execute(
            action: (string) $request->input('action', ''),
            steamId: $steamId,
            payload: $request->all()
        );

        return $this->respondJson(
            $request,
            $result['success'],
            $result['message'],
            $result['extra'] ?? []
        );
    }
}
