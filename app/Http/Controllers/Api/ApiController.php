<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    public function __construct(
        private PluginService $plugins,
        private RconService $rcon,
        private ScumDbService $scumDb,
    ) {
        $local = storage_path('app/scum/SCUM.db');
        if (file_exists($local)) {
            $this->scumDb->setDbPath($local);
        }
    }

    /**
     * 1. Статус API и сервера
     * GET /api/v1/status
     */
    public function status(): JsonResponse
    {
        $online = $this->getOnlineListCached();
        $isRconConnected = $this->rcon->isConnected();
        $isDbAvailable = $this->scumDb->isAvailable();

        return response()->json([
            'success' => true,
            'data' => [
                'server' => [
                    'online_count' => count($online),
                    'rcon_connected' => $isRconConnected,
                    'scumdb_available' => $isDbAvailable,
                    'server_time' => now()->toIso8601String(),
                ],
                'endpoints' => [
                    'online_count' => 'GET /api/v1/server/online',
                    'online_players' => 'GET /api/v1/players/online',
                    'player_info' => 'GET /api/v1/players/{steamId_or_name}',
                    'players_list' => 'GET /api/v1/players?q={search}&limit={limit}',
                ],
            ],
        ]);
    }

    /**
     * 1. Определение количества игроков на сервере
     * GET /api/v1/server/online
     */
    public function onlineCount(): JsonResponse
    {
        $online = $this->getOnlineListCached();

        return response()->json([
            'success' => true,
            'data' => [
                'online_count' => count($online),
                'rcon_connected' => $this->rcon->isConnected(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * 2. Получение списка онлайн игроков
     * GET /api/v1/players/online
     */
    public function onlinePlayers(): JsonResponse
    {
        $online = $this->getOnlineListCached();
        $squadMap = $this->getSquadMap();
        $fameMap = $this->getFameMap();

        $result = [];
        foreach ($online as $p) {
            $sid = (string) ($p['steamId'] ?? '');
            $squad = $sid !== '' ? ($squadMap[$sid] ?? null) : null;
            $fame = $sid !== '' ? ($fameMap[$sid] ?? (float) ($p['fame'] ?? 0)) : (float) ($p['fame'] ?? 0);

            $result[] = [
                'steamId' => $sid,
                'name' => (string) ($p['name'] ?? 'Unknown'),
                'money' => (float) ($p['money'] ?? 0),
                'gold' => (float) ($p['gold'] ?? 0),
                'fame' => (float) $fame,
                'squad' => $squad ? [
                    'id' => $squad['id'],
                    'name' => $squad['name'],
                    'rank' => $squad['rank'],
                    'rankTitle' => $this->formatRankTitle($squad['rank']),
                    'score' => $squad['score'],
                ] : null,
                'location' => isset($p['location']['x']) ? [
                    'x' => round((float) $p['location']['x']),
                    'y' => round((float) $p['location']['y']),
                    'z' => round((float) ($p['location']['z'] ?? 0)),
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($result),
            'data' => $result,
        ]);
    }

    /**
     * 3. Получение информации игрока (Деньги, слава, золото, отряд, статистика)
     * GET /api/v1/players/{identifier}
     * где {identifier} — SteamID64, либо никнейм, либо profileId
     */
    public function player(string $identifier): JsonResponse
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return response()->json(['success' => false, 'error' => 'Identifier is required'], 400);
        }

        // Поиск SteamID если передан ник или profileId
        $steamId = $this->resolveSteamId($identifier);
        if (! $steamId) {
            return response()->json([
                'success' => false,
                'error' => "Player '{$identifier}' not found in database.",
            ], 404);
        }

        $detail = null;
        try {
            $detail = $this->scumDb->getPlayerFullDetail($steamId);
        } catch (\Throwable $e) {
            Log::warning('ApiController::player detail error: '.$e->getMessage());
        }

        if (! $detail) {
            return response()->json([
                'success' => false,
                'error' => "Player details not found for Steam ID: {$steamId}",
            ], 404);
        }

        // Проверяем онлайн статус и живые координаты/балансы
        $online = $this->getOnlineListCached();
        $isOnline = false;
        $liveLocation = null;

        foreach ($online as $on) {
            if (($on['steamId'] ?? '') === $steamId) {
                $isOnline = true;
                $liveLocation = isset($on['location']['x']) ? [
                    'x' => round((float) $on['location']['x']),
                    'y' => round((float) $on['location']['y']),
                    'z' => round((float) ($on['location']['z'] ?? 0)),
                ] : null;

                if (isset($on['money']) && $on['money'] > 0) {
                    $detail['money'] = (float) $on['money'];
                }
                if (isset($on['gold']) && $on['gold'] > 0) {
                    $detail['gold'] = (float) $on['gold'];
                }
                break;
            }
        }

        // Отряд
        $squadMap = $this->getSquadMap();
        $squad = $squadMap[$steamId] ?? null;

        $playTimeSeconds = (int) ($detail['playTime'] ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'steamId' => (string) $detail['steamId'],
                'charName' => (string) ($detail['charName'] ?? $detail['steamName'] ?? 'Unknown'),
                'steamName' => (string) ($detail['steamName'] ?? ''),
                'profileId' => (int) ($detail['profileId'] ?? 0),
                'isOnline' => $isOnline,
                'isBanned' => (bool) ($detail['isBanned'] ?? false),

                // Финансы и слава
                'money' => (float) ($detail['money'] ?? 0),
                'gold' => (float) ($detail['gold'] ?? 0),
                'fame' => (float) ($detail['fame'] ?? 0),

                // Отряд
                'squad' => $squad ? [
                    'id' => $squad['id'],
                    'name' => $squad['name'],
                    'rank' => $squad['rank'],
                    'rankTitle' => $this->formatRankTitle($squad['rank']),
                    'score' => $squad['score'],
                ] : null,

                // Местоположение (если онлайн)
                'location' => $liveLocation,

                // Статистика
                'stats' => [
                    'kills' => (int) ($detail['kills'] ?? 0),
                    'deaths' => (int) ($detail['deaths'] ?? 0),
                    'kdRatio' => ($detail['deaths'] ?? 0) > 0
                        ? round(($detail['kills'] ?? 0) / $detail['deaths'], 2)
                        : (float) ($detail['kills'] ?? 0),
                    'prisonerKills' => (int) ($detail['prisonerKills'] ?? 0),
                    'prisonerDeaths' => (int) ($detail['prisonerDeaths'] ?? 0),
                    'puppetsKilled' => (int) ($detail['puppetsKilled'] ?? 0),
                    'playTimeSeconds' => $playTimeSeconds,
                    'playTimeHours' => round($playTimeSeconds / 3600, 1),
                    'lastLogin' => $detail['lastLogin'] ?? null,
                    'lastLogout' => $detail['lastLogout'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * Поиск игроков по базе
     * GET /api/v1/players?q=search&limit=50
     */
    public function players(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        try {
            $players = $this->scumDb->listPlayers($limit);
            if ($q !== '') {
                $qLower = mb_strtolower($q);
                $players = array_values(array_filter($players, function ($p) use ($qLower) {
                    return str_contains(mb_strtolower($p['charName'] ?? ''), $qLower)
                        || str_contains(mb_strtolower($p['steamName'] ?? ''), $qLower)
                        || str_contains((string) ($p['steamId'] ?? ''), $qLower);
                }));
            }

            $squadMap = $this->getSquadMap();
            $online = $this->getOnlineListCached();
            $onlineSet = array_flip(array_column($online, 'steamId'));

            $result = array_map(function ($p) use ($squadMap, $onlineSet) {
                $sid = (string) $p['steamId'];
                $squad = $squadMap[$sid] ?? null;

                return [
                    'steamId' => $sid,
                    'charName' => $p['charName'] ?? $p['steamName'] ?? '',
                    'steamName' => $p['steamName'] ?? '',
                    'isOnline' => isset($onlineSet[$sid]),
                    'money' => (float) ($p['money'] ?? 0),
                    'gold' => (float) ($p['gold'] ?? 0),
                    'fame' => (float) ($p['fame'] ?? 0),
                    'squad' => $squad ? [
                        'id' => $squad['id'],
                        'name' => $squad['name'],
                        'rank' => $squad['rank'],
                        'rankTitle' => $this->formatRankTitle($squad['rank']),
                    ] : null,
                ];
            }, $players);

            return response()->json([
                'success' => true,
                'count' => count($result),
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch players: '.$e->getMessage(),
            ], 500);
        }
    }

    // ===== Helpers =====

    private function getOnlineListCached(): array
    {
        try {
            return $this->plugins->listOnlinePlayers();
        } catch (\Throwable $e) {
            Log::warning('ApiController getOnlineListCached error: '.$e->getMessage());

            return [];
        }
    }

    private static ?array $squadMapMemory = null;

    private static ?array $fameMapMemory = null;

    private function getSquadMap(): array
    {
        if (self::$squadMapMemory !== null) {
            return self::$squadMapMemory;
        }

        $map = [];
        try {
            $dbPath = storage_path('app/scum/SCUM.db');
            if (file_exists($dbPath)) {
                $pdo = new \PDO('sqlite:'.$dbPath);
                $rows = $pdo->query('SELECT sm.rank, s.id as squad_id, s.name as squad_name, s.score as squad_score, up.user_id as steam_id 
                    FROM squad_member sm 
                    JOIN squad s ON s.id = sm.squad_id 
                    JOIN user_profile up ON up.id = sm.user_profile_id')->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $map[(string) $r['steam_id']] = [
                        'id' => (int) $r['squad_id'],
                        'name' => $r['squad_name'],
                        'rank' => (int) $r['rank'],
                        'score' => (float) $r['squad_score'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ApiController getSquadMap error: '.$e->getMessage());
        }

        return self::$squadMapMemory = $map;
    }

    private function getFameMap(): array
    {
        if (self::$fameMapMemory !== null) {
            return self::$fameMapMemory;
        }

        $map = [];
        try {
            $dbPath = storage_path('app/scum/SCUM.db');
            if (file_exists($dbPath)) {
                $pdo = new \PDO('sqlite:'.$dbPath);
                $rows = $pdo->query('SELECT user_id as steam_id, fame_points FROM user_profile')->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $map[(string) $r['steam_id']] = (float) ($r['fame_points'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ApiController getFameMap error: '.$e->getMessage());
        }

        return self::$fameMapMemory = $map;
    }

    private function resolveSteamId(string $identifier): ?string
    {
        // 1. Уже SteamID (17 цифр)
        if (preg_match('/^7656119\d{10}$/', $identifier)) {
            return $identifier;
        }

        try {
            $dbPath = storage_path('app/scum/SCUM.db');
            if (! file_exists($dbPath)) {
                return null;
            }
            $pdo = new \PDO('sqlite:'.$dbPath);

            // 2. По profileId (если целое число)
            if (ctype_digit($identifier)) {
                $stmt = $pdo->prepare('SELECT user_id FROM user_profile WHERE id = ? LIMIT 1');
                $stmt->execute([(int) $identifier]);
                $sid = $stmt->fetchColumn();
                if ($sid) {
                    return (string) $sid;
                }
            }

            // 3. По никнейму игрока (char name или steam name)
            $stmt = $pdo->prepare('SELECT user_id FROM user_profile WHERE lower(name) = lower(?) LIMIT 1');
            $stmt->execute([$identifier]);
            $sid = $stmt->fetchColumn();
            if ($sid) {
                return (string) $sid;
            }

            $stmt = $pdo->prepare('SELECT id FROM user WHERE lower(name) = lower(?) LIMIT 1');
            $stmt->execute([$identifier]);
            $sid = $stmt->fetchColumn();
            if ($sid) {
                return (string) $sid;
            }
        } catch (\Throwable $e) {
            Log::warning('ApiController resolveSteamId error: '.$e->getMessage());
        }

        return null;
    }

    private function formatRankTitle(int $rank): string
    {
        return match ($rank) {
            4 => 'Командир',
            3 => 'Зам. командира',
            2 => 'Офицер',
            1 => 'Боец',
            default => 'Рекрут',
        };
    }
}
