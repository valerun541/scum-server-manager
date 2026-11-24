<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Плагины: Airdrop (калибровка 125 точек, авто-дроп) и Rewards (часовые + топ)
 * Порт логики из webPanel.ts
 */
class PluginService
{
    public function __construct(
        private SettingsService $settings,
        private RconService $rcon,
        private RatingService $rating,
        private ScumMapService $map,
    ) {}

    // ===== Конфигурация плагинов (агрегат) =====

    public function getPluginsConfig(): array
    {
        return $this->settings->getAppConfig()['plugins'] ?? [];
    }

    public function savePluginsConfig(array $partial): void
    {
        $this->settings->saveAppConfig(['plugins' => $partial]);
    }

    // ===== Airdrop =====

    public function getAirdropConfig(): array
    {
        $plugins = $this->getPluginsConfig();

        return $plugins['airdrop'] ?? [];
    }

    public function getCalibration(): array
    {
        return $this->settings->getBotData()['calibration'] ?? [];
    }

    public function resetCalibration(): array
    {
        $points = $this->map->generateCalibrationPoints();
        $this->settings->saveBotData(['calibration' => $points]);

        return $points;
    }

    public function getCalibrationStatus(): array
    {
        $points = $this->getCalibration();
        if (empty($points)) {
            $points = $this->resetCalibration();
        }
        $current = 0;
        foreach ($points as $p) {
            if (isset($p['z']) && (float) $p['z'] !== 0.0) {
                $current++;
            }
        }

        return [
            'total' => count($points),
            'recorded' => $current,
            'remaining' => count($points) - $current,
            'points' => $points,
        ];
    }

    public function recordCalibrationPoint(int $index, float $z): void
    {
        $points = $this->getCalibration();
        if (empty($points)) {
            $points = $this->resetCalibration();
        }
        if (isset($points[$index])) {
            $points[$index]['z'] = $z;
            $this->settings->saveBotData(['calibration' => $points]);
        }
    }

    /** Ручной сброс аирдропа */
    public function dropAt(?float $x = null, ?float $y = null, ?float $z = null): array
    {
        $cfg = $this->getAirdropConfig();
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'error' => 'Airdrop отключён'];
        }
        $chest = $cfg['chestItem'] ?? 'Improvised_Metal_Chest';
        $minItems = (int) ($cfg['minItems'] ?? 3);
        $maxItems = (int) ($cfg['maxItems'] ?? 8);
        $maxItems = max($minItems, $maxItems);

        // Случайная точка на карте, если координаты не заданы
        if ($x === null || $y === null) {
            $point = $this->randomWorldPoint();
            $x = $point['x'];
            $y = $point['y'];
        }
        // Z не задана — берём из калибровки ближайшей точки, иначе высота падения
        if ($z === null) {
            $z = $this->findCalibrationZ($x, $y) ?? 20000;
        }

        $items = $this->loadItemList();
        if (empty($items)) {
            return ['ok' => false, 'error' => 'Список предметов пуст (iditem.txt)'];
        }
        $selected = [];
        $count = random_int($minItems, $maxItems);
        $pool = $items;
        shuffle($pool);
        foreach (array_slice($pool, 0, $count) as $item) {
            $selected[] = $item['id'];
        }

        $cmd = sprintf('SpawnInventoryFullOf %s %s x=%d y=%d z=%d', $chest, implode(' ', $selected), (int) $x, (int) $y, (int) $z);
        $result = $this->rcon->sendCommand($cmd);
        if ($result['success']) {
            $this->announceDrop((int) $x, (int) $y, $selected);
        }

        return [
            'ok' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? 'RCON error'),
            'command' => $cmd,
            'coords' => ['x' => $x, 'y' => $y, 'z' => $z],
        ];
    }

    public function dropAtPlayer(string $steamId): array
    {
        $loc = $this->rconListPlayer($steamId);
        if (! $loc) {
            return ['ok' => false, 'error' => 'Не удалось определить координаты игрока'];
        }

        return $this->dropAt($loc['x'], $loc['y'], $loc['z']);
    }

    private function randomWorldPoint(): array
    {
        // Предпочитаем откалиброванные точки (с записанной Z-высотой)
        $calib = $this->getCalibration();
        $calibrated = array_values(array_filter($calib, fn ($p) => ! empty($p['z']) && (float) $p['z'] !== 0.0));
        if (! empty($calibrated)) {
            $p = $calibrated[array_rand($calibrated)];

            return ['x' => (float) $p['x'], 'y' => (float) $p['y']];
        }
        $span = $this->map->span();

        return [
            'x' => (float) ($this->map::MIN_X + random_int(0, (int) $span)),
            'y' => (float) ($this->map::MIN_Y + random_int(0, (int) $span)),
        ];
    }

    /** Z из ближайшей откалиброванной точки (в радиусе 100000) */
    public function findCalibrationZ(float $x, float $y): ?float
    {
        $points = $this->getCalibration();
        $best = null;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($points as $p) {
            if (empty($p['z']) || (float) $p['z'] === 0.0) {
                continue;
            }
            $d = ((float) $p['x'] - $x) ** 2 + ((float) $p['y'] - $y) ** 2;
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $p;
            }
        }
        $maxDist = 100000 ** 2;

        return ($best !== null && $bestDist <= $maxDist) ? (float) $best['z'] : null;
    }

    /** Оповещение в игровой чат о месте сброса */
    private function announceDrop(int $x, int $y, array $items): void
    {
        try {
            $pixel = $this->map->worldToPixel($x, $y);
            $sector = $this->map->sectorFor($pixel['x'], $pixel['y']);
            $randItem = ! empty($items) ? str_replace('_', ' ', $items[array_rand($items)]) : 'лутом';
            $msgs = [
                "🎁| ВНИМАНИЕ! В секторе {$sector} сброшен тайник с лутом! Бегом на поиски!",
                "🎁| ТРЕВОГА! В секторе {$sector} приземлился тайник с припасами! Кто первый найдёт?",
                "🎁| В секторе {$sector} сброшен тайник! Говорят, там есть {$randItem}!",
                "🎁| СЛУХ! В секторе {$sector} замечен тайник с ценным лутом! Проверьте свои карты!",
            ];
            $this->rcon->sendCommand('SendChat 2 "'.$msgs[array_rand($msgs)].'"');
        } catch (\Throwable $e) {
            Log::channel('scum')->warning('[airdrop] announce: '.$e->getMessage());
        }
    }

    /** Авто-дроп по таймеру — вызывается из крона */
    public function autoDropTick(): array
    {
        $cfg = $this->getAirdropConfig();
        if (empty($cfg['enabled']) || empty($cfg['autoDropEnabled'])) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'disabled'];
        }
        $lastKey = 'airdrop_last_drop';
        $interval = (int) ($cfg['autoDropIntervalMinutes'] ?? config('panel.plugins.auto_drop_interval_minutes', 30)) * 60;
        $last = Cache::get($lastKey, 0);
        if (time() - $last < $interval) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'cooldown'];
        }

        $minPlayers = (int) ($cfg['autoDropMinPlayers'] ?? 2);
        $players = $this->listOnlinePlayers();
        if (count($players) < $minPlayers) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'not enough players'];
        }

        Cache::forever($lastKey, time());
        $result = $this->dropAt();

        return $result;
    }

    // ===== Rewards =====

    public function getRewardsConfig(): array
    {
        $plugins = $this->getPluginsConfig();

        return $plugins['rewards'] ?? [];
    }

    public function getRewardsData(): array
    {
        return $this->settings->getBotData()['rewards'] ?? ['lastHourlyReward' => [], 'lastTopRewardTime' => 0, 'playerTimeData' => []];
    }

    /** Проверка часовых наград — вызывается из крона */
    public function hourlyRewardsTick(): array
    {
        $cfg = $this->getRewardsConfig();
        if (empty($cfg['enabled']) || empty($cfg['hourlyEnabled'])) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'disabled'];
        }
        $rewards = $this->getRewardsData();
        $granted = [];
        $players = $this->listOnlinePlayers();
        foreach ($players as $p) {
            $sid = $p['steamId'];
            $last = (int) ($rewards['lastHourlyReward'][$sid] ?? 0);
            if (time() - $last < 3600) {
                continue;
            }
            $rewards['lastHourlyReward'][$sid] = time();
            $msgs = [];
            if ((float) $cfg['hourlyMoney'] > 0) {
                $this->rcon->sendCommand('#SetCurrencyBalance Normal '.(int) round(($p['money'] ?? 0) + (float) $cfg['hourlyMoney'])." {$sid}");
                $msgs[] = 'Деньги +'.$cfg['hourlyMoney'];
            }
            if ((float) $cfg['hourlyGold'] > 0) {
                $this->rcon->sendCommand('#SetCurrencyBalance Gold '.(int) round(($p['gold'] ?? 0) + (float) $cfg['hourlyGold'])." {$sid}");
                $msgs[] = 'Золото +'.$cfg['hourlyGold'];
            }
            if ((float) $cfg['hourlyFame'] > 0) {
                $this->rcon->sendCommand('#SetFamePoints '.(int) round(($p['fame'] ?? 0) + (float) $cfg['hourlyFame'])." {$sid}");
                $msgs[] = 'Слава +'.$cfg['hourlyFame'];
            }
            if ($msgs) {
                $this->rcon->sendCommand('SendChat 2 "Награда за час онлайн: '.implode(', ', $msgs).'"');
                $granted[] = $sid;
            }
        }
        $this->settings->saveBotData(['rewards' => $rewards]);

        return ['ok' => count($granted) > 0, 'granted' => count($granted)];
    }

    /** Топ-награды за период */
    public function topRewardsTick(): array
    {
        $cfg = $this->getRewardsConfig();
        if (empty($cfg['enabled']) || empty($cfg['topEnabled'])) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'disabled'];
        }
        $rewards = $this->getRewardsData();
        $lastTop = (int) ($rewards['lastTopRewardTime'] ?? 0);
        $interval = (int) ($cfg['topIntervalDays'] ?? config('panel.plugins.top_interval_days', 1)) * 86400;
        if ($lastTop && (time() - $lastTop) < $interval) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'not time'];
        }

        $blacklist = $this->getPluginsConfig()['ratingBlacklist'] ?? [];
        $leaderboard = array_values(array_filter($this->rating->getLeaderboard(), fn ($r) => ! in_array($r['steamId'], $blacklist)));
        $topCount = (int) ($cfg['topCount'] ?? 3);
        $top = array_slice($leaderboard, 0, $topCount);

        $rewards['lastTopRewardTime'] = time();
        $this->settings->saveBotData(['rewards' => $rewards]);

        $granted = [];
        foreach ($top as $i => $p) {
            $sid = $p['steamId'];
            $place = $i + 1;
            $msgs = [];
            if ((float) $cfg['topMoney'] > 0) {
                $this->rcon->sendCommand('#SetCurrencyBalance Normal '.(int) round(($p['money'] ?? 0) + (float) $cfg['topMoney'])." {$sid}");
                $msgs[] = 'Деньги +'.$cfg['topMoney'];
            }
            if ((float) $cfg['topGold'] > 0) {
                $this->rcon->sendCommand('#SetCurrencyBalance Gold '.(int) round(($p['gold'] ?? 0) + (float) $cfg['topGold'])." {$sid}");
                $msgs[] = 'Золото +'.$cfg['topGold'];
            }
            if ((float) $cfg['topFame'] > 0) {
                $this->rcon->sendCommand('#SetFamePoints '.(int) round(($p['fame'] ?? 0) + (float) $cfg['topFame'])." {$sid}");
                $msgs[] = 'Слава +'.$cfg['topFame'];
            }
            $this->rcon->sendCommand('SendChat 2 "🏆 Топ-'.$place.' игрок недели: '.$p['playerName'].' — '.implode(', ', $msgs).'"');
            $granted[] = $sid;
        }

        return ['ok' => count($granted) > 0, 'granted' => $granted];
    }

    // ===== Chat sender =====

    public function getChatSender(): string
    {
        return $this->getPluginsConfig()['chatSender'] ?? 'AMUR bot';
    }

    // ===== Утилиты RCON =====

    public function rconListPlayer(string $steamId): ?array
    {
        $r = $this->rcon->sendCommand("Location {$steamId}");
        if ($r['success'] && ! empty($r['response'])) {
            if (preg_match('/X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $r['response'], $pm)) {
                return ['x' => (float) $pm[1], 'y' => (float) $pm[2], 'z' => (float) $pm[3]];
            }
        }

        $r = $this->rcon->sendCommand('ListPlayers');
        if (! $r['success'] || empty($r['response'])) {
            return null;
        }

        $lines = explode("\n", $r['response']);
        $matched = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, $steamId)) {
                $matched = true;
                if (preg_match('/(?:Location:\s*)?X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $pm)) {
                    return ['x' => (float) $pm[1], 'y' => (float) $pm[2], 'z' => (float) $pm[3]];
                }
                if (preg_match('/\(([\d.+-]+),\s*([\d.+-]+),\s*([\d.+-]+)\)/', $trimmed, $pm)) {
                    return ['x' => (float) $pm[1], 'y' => (float) $pm[2], 'z' => (float) $pm[3]];
                }
            } elseif ($matched) {
                if (preg_match('/^(?:Location:\s*)?X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $pm)) {
                    return ['x' => (float) $pm[1], 'y' => (float) $pm[2], 'z' => (float) $pm[3]];
                }
                if (preg_match('/^\s*\d+\.\s+/i', $trimmed)) {
                    $matched = false;
                }
            }
        }

        return null;
    }

    /**
     * Парсит ответ ListPlayers. Поддерживает форматы:
     *  - многострочный: " 1. Name", "Steam: ... (SteamID)", "Fame: ...", "Account balance: ...", "Location: X=... Y=... Z=..."
     *  - однострочный: "1. Steam: SteamID (Name). Fame: ... Account balance: ... Location: X=... Y=... Z=..."
     *  - v0.4.6: "PLAYER | Name | steam=... | upid=N | money=N | gold=N | (x, y, z)"
     */
    public function listOnlinePlayers(): array
    {
        $host = $this->rcon->status()['host'] ?? '';

        return Cache::remember('online_players_'.md5($host), (int) config('panel.cache.online_players_ttl', 10), function () {
            return $this->fetchOnlinePlayers();
        });
    }

    /**
     * Демон scum:rcon подогревает кэш онлайн-игроков каждые 3 секунды на своём
     * единственном RCON-соединении. Веб-страницы (/map, /players) тогда читают
     * кэш и не коннектятся к RCON — иначе каждый запрос спорит с демоном за слот
     * и реконнект занимает ~4 секунды. TTL 10 > интервала прогрева (3с), чтобы
     * веб-запросы всегда попадали в свежий кэш. Команда выполняется без логирования.
     */
    public function warmOnlinePlayers(): void
    {
        $host = $this->rcon->status()['host'] ?? '';
        if ($host === '') {
            return;
        }
        $r = $this->rcon->sendCommand('ListPlayers', false);
        $players = $r['success'] && ! empty($r['response']) ? $this->parseOnlinePlayers($r['response']) : [];
        Cache::put('online_players_'.md5($host), $players, (int) config('panel.cache.online_players_ttl', 10));
    }

    private function fetchOnlinePlayers(): array
    {
        $r = $this->rcon->sendCommand('ListPlayers');
        if (! $r['success'] || empty($r['response'])) {
            return [];
        }

        return $this->parseOnlinePlayers($r['response']);
    }

    private function parseOnlinePlayers(string $response): array
    {
        $players = [];
        $lastIndex = -1;
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed === 'No players online.') {
                continue;
            }

            // Новый формат: PLAYER | Name | steam=... | upid=... | money=... | gold=... | (x, y, z)
            if (preg_match('/^PLAYER\s*\|\s*(.+?)\s*\|\s*steam=(\d+)\s*\|/i', $trimmed, $pm)) {
                $money = preg_match('/money=([\d.+-]+)/i', $trimmed, $m1) ? (float) $m1[1] : 0;
                $gold = preg_match('/gold=([\d.+-]+)/i', $trimmed, $g1) ? (float) $g1[1] : 0;
                $players[] = [
                    'steamId' => $pm[2],
                    'name' => trim($pm[1]),
                    'money' => $money,
                    'gold' => $gold,
                    'fame' => 0,
                    'location' => $this->parseLocation($trimmed),
                ];
                $lastIndex = count($players) - 1;

                continue;
            }

            // Однострочный формат: 1. Steam: 76561198... (Name). Fame: ... Account balance: ...
            if (preg_match('/^\s*\d+\.\s+Steam:\s*(\d{17})\s*\((.+?)\)/i', $trimmed, $osm)) {
                $money = preg_match('/Account balance:\s*([\d.+-]+)/i', $trimmed, $m1) ? (float) $m1[1] : 0;
                $gold = preg_match('/Gold balance:\s*([\d.+-]+)/i', $trimmed, $g1) ? (float) $g1[1] : 0;
                $fame = preg_match('/Fame:\s*([\d.+-]+)/i', $trimmed, $f1) ? (float) $f1[1] : 0;
                $loc = null;
                if (preg_match('/Location:\s*X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $lm)) {
                    $loc = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];
                }
                $players[] = [
                    'steamId' => $osm[1],
                    'name' => trim($osm[2]),
                    'money' => $money,
                    'gold' => $gold,
                    'fame' => $fame,
                    'location' => $loc,
                ];
                $lastIndex = count($players) - 1;

                continue;
            }

            // Многострочный формат: " 1. Name"
            if (preg_match('/^\s*\d+\.\s+([^:]+)$/', $trimmed, $nm)) {
                $players[] = [
                    'steamId' => '',
                    'name' => trim($nm[1]),
                    'money' => 0,
                    'gold' => 0,
                    'fame' => 0,
                    'location' => null,
                ];
                $lastIndex = count($players) - 1;

                continue;
            }

            // Строки-дополнения многострочного формата для текущего игрока
            if ($lastIndex >= 0) {
                if (preg_match('/^Steam:\s*(?:.+?\s*)?\((\d+)\)/i', $trimmed, $sm)) {
                    $players[$lastIndex]['steamId'] = $sm[1];

                    continue;
                }
                if (preg_match('/^(?:Location:\s*)?X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $lm)) {
                    $players[$lastIndex]['location'] = ['x' => (float) $lm[1], 'y' => (float) $lm[2], 'z' => (float) $lm[3]];

                    continue;
                }
                if (preg_match('/^Fame:\s*([\d.+-]+)/i', $trimmed, $fm)) {
                    $players[$lastIndex]['fame'] = (float) $fm[1];

                    continue;
                }
                if (preg_match('/^Account balance:\s*([\d.+-]+)/i', $trimmed, $bm)) {
                    $players[$lastIndex]['money'] = (float) $bm[1];

                    continue;
                }
                if (preg_match('/^Gold balance:\s*([\d.+-]+)/i', $trimmed, $gm)) {
                    $players[$lastIndex]['gold'] = (float) $gm[1];

                    continue;
                }
            }
        }

        // Убираем записи без SteamID
        return array_values(array_filter($players, fn ($p) => ! empty($p['steamId'])));
    }

    private function parseLocation(string $line): ?array
    {
        if (preg_match('/\(([\d.+-]+),\s*([\d.+-]+),\s*([\d.+-]+)\)/', $line, $pm)) {
            return ['x' => (float) $pm[1], 'y' => (float) $pm[2], 'z' => (float) $pm[3]];
        }

        return null;
    }

    public function loadItemList(): array
    {
        if ($items = Cache::get('scum_item_list')) {
            return $items;
        }
        $catalogPath = storage_path('app/scum/catalog_items.json');
        if (file_exists($catalogPath)) {
            $raw = file_get_contents($catalogPath);
            $decoded = json_decode($raw ?: '[]', true);
            if (is_array($decoded) && ! empty($decoded)) {
                $items = array_map(fn ($i) => [
                    'id' => $i['id'],
                    'name' => $i['name_ru'] ?? $i['name'] ?? $i['id'],
                    'category' => $i['category'] ?? 'other',
                    'icon' => $i['icon'] ?? null,
                ], $decoded);
                Cache::put('scum_item_list', $items, 3600);

                return $items;
            }
        }
        $path = storage_path('app/scum/iditem.txt');
        if (! file_exists($path)) {
            return [];
        }
        $items = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (! $line) {
                continue;
            }
            // формат: "id" "название" | id:название | число название | просто id
            if (preg_match('/^"([^"]+)"\s+"([^"]+)"$/', $line, $m)) {
                $items[] = ['id' => $m[1], 'name' => $m[2]];
            } elseif (preg_match('/^([^:\s]+)\s*[:=]\s*(.+)$/', $line, $m)) {
                $items[] = ['id' => $m[1], 'name' => $m[2]];
            } elseif (preg_match('/^\d+\s+(.+)$/', $line, $m)) {
                $items[] = ['id' => trim($m[1]), 'name' => trim($m[1])];
            } else {
                $items[] = ['id' => $line, 'name' => $line];
            }
        }
        if (! empty($items)) {
            Cache::put('scum_item_list', $items, 3600);
        }

        return $items;
    }
}
