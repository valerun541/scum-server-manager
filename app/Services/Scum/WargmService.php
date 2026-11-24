<?php

namespace App\Services\Scum;

use App\Support\ScumSkills;
use App\Support\SecureValues;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WARGM магазин + автовыдача — порт wargmManager.ts
 */
class WargmService
{
    private RconService $rcon;

    private SettingsService $settings;

    private array $lastCommandTime = [];

    public function __construct(RconService $rcon, SettingsService $settings)
    {
        $this->rcon = $rcon;
        $this->settings = $settings;
    }

    public function getSettings(): array
    {
        $rows = DB::table('wargm_settings')->pluck('value', 'key')->toArray();

        return [
            'apiUrl' => $rows['apiUrl'] ?? 'https://api.wargm.ru/v1/',
            'shopId' => $rows['shopId'] ?? '',
            'apiKey' => SecureValues::decrypt($rows['apiKey'] ?? '') ?? '',
            'timeout' => (int) ($rows['timeout'] ?? config('panel.http.wargm_timeout', 30)),
            'duplicateCheckMinutes' => (int) ($rows['duplicateCheckMinutes'] ?? 5),
            'commandCooldownSeconds' => (int) ($rows['commandCooldownSeconds'] ?? 30),
            'maxItemsPerCard' => (int) ($rows['maxItemsPerCard'] ?? 50),
        ];
    }

    public function saveSettings(array $s): void
    {
        $entries = [
            'apiUrl' => $s['apiUrl'] ?? 'https://api.wargm.ru/v1/',
            'shopId' => $s['shopId'] ?? '',
            'apiKey' => SecureValues::encrypt($s['apiKey'] ?? '') ?? '',
            'timeout' => (string) ($s['timeout'] ?? config('panel.http.wargm_timeout', 30)),
            'duplicateCheckMinutes' => (string) ($s['duplicateCheckMinutes'] ?? 5),
            'commandCooldownSeconds' => (string) ($s['commandCooldownSeconds'] ?? 30),
            'maxItemsPerCard' => (string) ($s['maxItemsPerCard'] ?? 50),
        ];
        foreach ($entries as $k => $v) {
            DB::table('wargm_settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now()]);
        }
    }

    public function getCards(): array
    {
        $cards = DB::table('wargm_cards')->orderByDesc('id')->get();
        if ($cards->isEmpty()) {
            return [];
        }

        // Одним запросом тянем все предметы всех карточек и группируем по card_id
        $itemsByCard = DB::table('wargm_card_items')
            ->whereIn('card_id', $cards->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('card_id');

        return $cards->map(function ($c) use ($itemsByCard) {
            $items = $itemsByCard->get($c->id, collect());

            return [
                'id' => $c->id,
                'name' => $c->name,
                'shopItemId' => $c->shop_item_id,
                'enabled' => (bool) $c->enabled,
                'items' => $items->map(fn ($i) => [
                    'id' => $i->id,
                    'type' => $i->type,
                    'data' => json_decode($i->data ?? '{}', true) ?: [],
                    'sortOrder' => $i->sort_order,
                ])->values()->toArray(),
                'createdAt' => $c->created_at,
                'updatedAt' => $c->updated_at,
            ];
        })->toArray();
    }

    public function getCard(int $id): ?array
    {
        $c = DB::table('wargm_cards')->where('id', $id)->first();
        if (! $c) {
            return null;
        }
        $items = DB::table('wargm_card_items')->where('card_id', $id)->orderBy('sort_order')->orderBy('id')->get();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'shopItemId' => $c->shop_item_id,
            'enabled' => (bool) $c->enabled,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'type' => $i->type,
                'data' => json_decode($i->data ?? '{}', true) ?: [],
                'sortOrder' => $i->sort_order,
            ])->toArray(),
            'createdAt' => $c->created_at,
            'updatedAt' => $c->updated_at,
        ];
    }

    public function saveCard(array $card): ?int
    {
        try {
            DB::transaction(function () use ($card, &$cardId) {
                if (! empty($card['id']) && DB::table('wargm_cards')->where('id', $card['id'])->exists()) {
                    DB::table('wargm_cards')->where('id', $card['id'])->update([
                        'name' => $card['name'],
                        'shop_item_id' => $card['shopItemId'],
                        'enabled' => ! empty($card['enabled']),
                        'updated_at' => now(),
                    ]);
                    $cardId = (int) $card['id'];
                    DB::table('wargm_card_items')->where('card_id', $cardId)->delete();
                } else {
                    $cardId = DB::table('wargm_cards')->insertGetId([
                        'name' => $card['name'],
                        'shop_item_id' => $card['shopItemId'],
                        'enabled' => ! empty($card['enabled']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $rows = [];
                foreach ($card['items'] ?? [] as $i => $item) {
                    $rows[] = [
                        'card_id' => $cardId,
                        'type' => $item['type'],
                        'data' => json_encode($item['data'] ?? [], JSON_UNESCAPED_UNICODE),
                        'sort_order' => $i,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($rows) {
                    DB::table('wargm_card_items')->insert($rows);
                }
            }, attempts: 5);

            return $cardId ?? null;
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[Wargm] saveCard error: '.$e->getMessage());

            return null;
        }
    }

    public function deleteCard(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table('wargm_card_items')->where('card_id', $id)->delete();
            DB::table('wargm_deliveries')->where('card_id', $id)->delete();

            return (bool) DB::table('wargm_cards')->where('id', $id)->delete();
        }, attempts: 5);
    }

    public function duplicateCard(int $id): ?int
    {
        $card = $this->getCard($id);
        if (! $card) {
            return null;
        }
        $card['id'] = null;
        $card['name'] = $card['name'].' (copy)';
        $card['enabled'] = false;
        foreach ($card['items'] as &$item) {
            unset($item['id']);
        }

        return $this->saveCard($card);
    }

    public function resolvePlayerName(string $steamId, ?string $fallback = null): string
    {
        if (! empty($fallback) && $fallback !== '—' && $fallback !== 'Игрок') {
            return $fallback;
        }

        if (empty($steamId)) {
            return $fallback ?? '—';
        }

        $cached = Cache::get('player_name_'.$steamId);
        if (! empty($cached) && $cached !== '—' && $cached !== 'Игрок') {
            return (string) $cached;
        }

        $resolved = '';

        // 1. SCUM.db SQLite
        $dbPath = storage_path('app/scum/SCUM.db');
        if (file_exists($dbPath)) {
            try {
                $pdo = new \PDO('sqlite:'.$dbPath);
                $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 2);
                $stmt = $pdo->prepare('SELECT name FROM user_profile WHERE user_id = ? AND name IS NOT NULL AND name != "" LIMIT 1');
                $stmt->execute([$steamId]);
                $name = $stmt->fetchColumn();
                if ($name && strlen(trim($name)) > 0) {
                    $resolved = (string) $name;
                } else {
                    $stmt2 = $pdo->prepare('SELECT name FROM user WHERE id = ? AND name IS NOT NULL AND name != "" LIMIT 1');
                    $stmt2->execute([$steamId]);
                    $name2 = $stmt2->fetchColumn();
                    if ($name2 && strlen(trim($name2)) > 0) {
                        $resolved = (string) $name2;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2. Chat Events (наиболее часто встречающийся никнейм длиной более 1 символа)
        if (empty($resolved)) {
            try {
                $chatName = DB::table('chat_events')
                    ->where('steam_id', $steamId)
                    ->whereNotNull('player_name')
                    ->where('player_name', '!=', '')
                    ->whereRaw('length(player_name) > 1')
                    ->select('player_name', DB::raw('count(*) as count'))
                    ->groupBy('player_name')
                    ->orderByDesc('count')
                    ->value('player_name');

                if ($chatName) {
                    $resolved = (string) $chatName;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 3. Live RCON Whois / ListPlayers (если подключён)
        if (empty($resolved) && $this->rcon->isConnected()) {
            try {
                $w = $this->rcon->sendCommand("Whois {$steamId}");
                if ($w['success'] && ! empty($w['response'])) {
                    if (preg_match('/(?:Character\s*name|Name):\s*([^\r\n]+)/i', $w['response'], $m)) {
                        $resolved = trim($m[1]);
                    }
                }
                if (empty($resolved)) {
                    $lp = $this->rcon->sendCommand('ListPlayers');
                    if ($lp['success'] && ! empty($lp['response'])) {
                        foreach (explode("\n", $lp['response']) as $line) {
                            if (str_contains($line, "steam={$steamId}")) {
                                if (preg_match('/^PLAYER\s*\|\s*(.+?)\s*\|/i', $line, $lm)) {
                                    $resolved = trim($lm[1]);
                                    break;
                                } elseif (preg_match('/^\d+\.\s+([^(\[]+)/i', $line, $lm)) {
                                    $resolved = trim($lm[1]);
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (! empty($resolved) && $resolved !== '—' && $resolved !== 'Игрок') {
            Cache::put('player_name_'.$steamId, $resolved, now()->addDay());
            // Автоматически обновляем существующие записи выдач без ника
            try {
                DB::table('wargm_deliveries')
                    ->where('steam_id', $steamId)
                    ->where(function ($q) {
                        $q->whereNull('player_name')->orWhere('player_name', '')->orWhere('player_name', '—')->orWhere('player_name', 'Игрок');
                    })
                    ->update(['player_name' => $resolved, 'updated_at' => now()]);
            } catch (\Throwable $e) {
                // ignore
            }

            return $resolved;
        }

        return $fallback ?? '—';
    }

    /**
     * Пакетное разрешение ников для массива steam_ids
     *
     * @param  string[]  $steamIds
     * @return array<string, string>
     */
    public function batchResolvePlayerNames(array $steamIds): array
    {
        $steamIds = array_values(array_unique(array_filter($steamIds)));
        if (empty($steamIds)) {
            return [];
        }

        $result = [];
        $unresolved = [];

        // 1. Проверяем кэш
        foreach ($steamIds as $sid) {
            $cached = Cache::get('player_name_'.$sid);
            if (! empty($cached) && $cached !== '—' && $cached !== 'Игрок') {
                $result[$sid] = (string) $cached;
            } else {
                $unresolved[] = $sid;
            }
        }

        if (empty($unresolved)) {
            return $result;
        }

        // 2. SCUM.db SQLite
        $dbPath = storage_path('app/scum/SCUM.db');
        if (file_exists($dbPath)) {
            try {
                $pdo = new \PDO('sqlite:'.$dbPath);
                $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 2);
                $placeholders = implode(',', array_fill(0, count($unresolved), '?'));
                $stmt = $pdo->prepare("SELECT user_id, name FROM user_profile WHERE user_id IN ({$placeholders}) AND name IS NOT NULL AND name != ''");
                $stmt->execute($unresolved);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $sid = (string) $row['user_id'];
                    $name = trim((string) $row['name']);
                    if (! empty($name)) {
                        $result[$sid] = $name;
                    }
                }

                // Для оставшихся пробуем user таблицу
                $stillUnresolved = array_values(array_diff($unresolved, array_keys($result)));
                if (! empty($stillUnresolved)) {
                    $placeholders2 = implode(',', array_fill(0, count($stillUnresolved), '?'));
                    $stmt2 = $pdo->prepare("SELECT id, name FROM user WHERE id IN ({$placeholders2}) AND name IS NOT NULL AND name != ''");
                    $stmt2->execute($stillUnresolved);
                    while ($row = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
                        $sid = (string) $row['id'];
                        $name = trim((string) $row['name']);
                        if (! empty($name)) {
                            $result[$sid] = $name;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 3. chat_events для оставшихся
        $stillUnresolved = array_values(array_diff($unresolved, array_keys($result)));
        if (! empty($stillUnresolved)) {
            try {
                $chatRows = DB::table('chat_events')
                    ->whereIn('steam_id', $stillUnresolved)
                    ->whereNotNull('player_name')
                    ->where('player_name', '!=', '')
                    ->whereRaw('length(player_name) > 1')
                    ->select('steam_id', 'player_name', DB::raw('count(*) as count'))
                    ->groupBy('steam_id', 'player_name')
                    ->orderByDesc('count')
                    ->get();

                foreach ($chatRows as $crow) {
                    $sid = (string) $crow->steam_id;
                    if (! isset($result[$sid])) {
                        $result[$sid] = trim((string) $crow->player_name);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 4. Кэшируем и бэкфиллим базу
        foreach ($result as $sid => $name) {
            if (! empty($name) && $name !== '—' && $name !== 'Игрок') {
                Cache::put('player_name_'.$sid, $name, now()->addDay());
                try {
                    DB::table('wargm_deliveries')
                        ->where('steam_id', $sid)
                        ->where(function ($q) {
                            $q->whereNull('player_name')->orWhere('player_name', '')->orWhere('player_name', '—')->orWhere('player_name', 'Игрок');
                        })
                        ->update(['player_name' => $name, 'updated_at' => now()]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        return $result;
    }

    public function isDelivered(string $purchaseId): bool
    {
        return DB::table('wargm_deliveries')->where('purchase_id', $purchaseId)->exists();
    }

    public function addDelivery(
        string $purchaseId,
        string $steamId,
        int $cardId,
        ?string $playerName = null,
        ?string $location = null,
        ?string $itemsSummary = null
    ): void {
        if (empty($playerName) || $playerName === '—' || $playerName === 'Игрок') {
            $playerName = $this->resolvePlayerName($steamId);
        }

        DB::table('wargm_deliveries')->updateOrInsert(
            ['purchase_id' => $purchaseId],
            [
                'steam_id' => $steamId,
                'player_name' => (! empty($playerName) && $playerName !== '—') ? $playerName : null,
                'card_id' => $cardId,
                'location' => $location,
                'items_summary' => $itemsSummary,
                'delivered_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function getAllDeliveries(int $limit = 50, ?string $search = null): array
    {
        $query = DB::table('wargm_deliveries as d')
            ->leftJoin('wargm_cards as c', 'd.card_id', '=', 'c.id')
            ->orderByDesc('d.delivered_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('d.steam_id', 'like', "%{$search}%")
                    ->orWhere('d.player_name', 'like', "%{$search}%")
                    ->orWhere('d.purchase_id', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%");
            });
        }

        $deliveries = $query->limit($limit)->get([
            'd.id', 'd.purchase_id', 'd.steam_id', 'd.player_name', 'd.card_id',
            'c.name as card_name', 'd.location', 'd.items_summary', 'd.delivered_at',
        ]);

        $missingSteamIds = [];
        foreach ($deliveries as $r) {
            if (empty($r->player_name) || $r->player_name === '—' || $r->player_name === 'Игрок') {
                $missingSteamIds[] = (string) $r->steam_id;
            }
        }

        $resolvedMap = ! empty($missingSteamIds) ? $this->batchResolvePlayerNames($missingSteamIds) : [];

        return $deliveries->map(function ($r) use ($resolvedMap) {
            $name = $r->player_name;
            if (empty($name) || $name === '—' || $name === 'Игрок') {
                $name = $resolvedMap[$r->steam_id] ?? null;
            }

            return [
                'id' => $r->id,
                'purchaseId' => $r->purchase_id,
                'steamId' => $r->steam_id,
                'playerName' => ! empty($name) && $name !== '—' ? $name : '—',
                'cardId' => $r->card_id,
                'cardName' => $r->card_name ?? 'Карточка удалена',
                'location' => $r->location,
                'itemsSummary' => $r->items_summary,
                'deliveredAt' => $r->delivered_at,
            ];
        })->toArray();
    }

    public function getDeliveriesBySteam(string $steamId, int $limit = 50): array
    {
        $deliveries = DB::table('wargm_deliveries as d')
            ->leftJoin('wargm_cards as c', 'd.card_id', '=', 'c.id')
            ->where('d.steam_id', $steamId)
            ->orderByDesc('d.delivered_at')
            ->limit($limit)
            ->get([
                'd.id', 'd.purchase_id', 'd.steam_id', 'd.player_name', 'd.card_id',
                'c.name as card_name', 'd.location', 'd.items_summary', 'd.delivered_at',
            ]);

        $resolvedName = null;
        $needsResolution = $deliveries->contains(fn ($r) => empty($r->player_name) || $r->player_name === '—' || $r->player_name === 'Игрок');
        if ($needsResolution) {
            $resolvedName = $this->resolvePlayerName($steamId);
        }

        return $deliveries->map(function ($r) use ($resolvedName) {
            $name = $r->player_name;
            if (empty($name) || $name === '—' || $name === 'Игрок') {
                $name = $resolvedName ?: '—';
            }

            return [
                'id' => $r->id,
                'purchaseId' => $r->purchase_id,
                'steamId' => $r->steam_id,
                'playerName' => ! empty($name) && $name !== '—' ? $name : '—',
                'cardId' => $r->card_id,
                'cardName' => $r->card_name ?? '',
                'location' => $r->location,
                'itemsSummary' => $r->items_summary,
                'deliveredAt' => $r->delivered_at,
            ];
        })->toArray();
    }

    public function checkCommandCooldown(string $steamId, int $seconds): int
    {
        $last = $this->lastCommandTime[$steamId] ?? 0;
        $elapsed = time() - $last;
        if ($elapsed < $seconds) {
            return (int) ceil($seconds - $elapsed);
        }

        return 0;
    }

    public function setCommandCooldown(string $steamId): void
    {
        $this->lastCommandTime[$steamId] = time();
    }

    // ===== WARGM API =====

    private function apiRequest(array $settings, string $endpoint, string $method = 'GET'): array
    {
        try {
            $timeout = (int) ($settings['timeout'] ?? config('panel.http.wargm_timeout', 30));
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->send($method, $this->apiUrl($settings, $endpoint));
            $text = $response->body();
            $this->logApiCall($endpoint, $method, $response->status(), $text);
            if (! $response->successful()) {
                return ['success' => false, 'error' => 'HTTP '.$response->status().': '.mb_substr($text, 0, 200)];
            }

            $json = $response->json();
            $apiStatus = $json['response']['status'] ?? $json['responce']['status'] ?? null;
            if ($apiStatus === 'error') {
                $msg = $json['response']['msg'] ?? $json['responce']['msg'] ?? 'API error';

                return ['success' => false, 'error' => $msg];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $this->logApiCall($endpoint, $method, 0, $msg);

            return ['success' => false, 'error' => $msg];
        }
    }

    private function apiUrl(array $settings, string $endpoint): string
    {
        $base = rtrim($settings['apiUrl'], '/');
        $auth = 'client='.urlencode($settings['shopId']).':'.urlencode($settings['apiKey']);
        $sep = str_contains($endpoint, '?') ? '&' : '?';

        return $base.'/'.ltrim($endpoint, '/').$sep.$auth;
    }

    public function testConnection(array $settings): array
    {
        if (empty($settings['apiUrl']) || empty($settings['shopId']) || empty($settings['apiKey'])) {
            return ['ok' => false, 'message' => 'Fill API URL, Shop ID and API Key'];
        }
        $result = $this->apiRequest($settings, 'shop/info');
        if (! $result['success']) {
            return ['ok' => false, 'message' => $result['error'] ?? 'Connection failed'];
        }
        $json = $result['data'];
        $apiStatus = $json['response']['status'] ?? $json['responce']['status'] ?? null;
        if ($apiStatus === 'error') {
            $msg = $json['response']['msg'] ?? $json['responce']['msg'] ?? 'API error';

            return ['ok' => false, 'message' => $msg];
        }

        return ['ok' => true, 'message' => 'Connection successful'];
    }

    /** @return array{operations:array,raw:mixed} */
    public function fetchPendingOperations(array $settings, string $steamId): array
    {
        $attempts = [
            'pending' => 'shop/operations?status=pending&steam_id='.urlencode($steamId),
            'all' => 'shop/operations?steam_id='.urlencode($steamId),
        ];

        foreach ($attempts as $key => $endpoint) {
            $result = $this->apiRequest($settings, $endpoint);
            $this->logApiCall($endpoint, 'GET', $result['success'] ? 200 : 0, json_encode($result));
            if ($result['success']) {
                $ops = $this->extractOperations($result['data']);
                if (count($ops) > 0) {
                    return ['operations' => $ops, 'raw' => $result['data']];
                }
            }
        }

        return ['operations' => [], 'raw' => null];
    }

    private function extractOperations(mixed $d): array
    {
        if (! $d) {
            return [];
        }
        // v1: responce (typo), v1.1: response
        foreach (['responce', 'response'] as $rKey) {
            if (isset($d[$rKey]['data'])) {
                $rd = $d[$rKey]['data'];
                if (is_array($rd)) {
                    return array_values($rd);
                }
            }
        }
        if (is_array($d) && array_is_list($d)) {
            return $d;
        }
        foreach (['data', 'operations', 'result', 'items', 'list'] as $key) {
            if (isset($d[$key]) && is_array($d[$key])) {
                return array_values($d[$key]);
            }
        }
        foreach ($d as $key => $val) {
            if (is_array($val) && array_is_list($val)) {
                return $val;
            }
        }

        return [];
    }

    public function fetchRawOperations(array $settings, string $steamId): array
    {
        return $this->apiRequest($settings, 'shop/operations?status=pending&steam_id='.urlencode($steamId));
    }

    public function claimOperation(array $settings, int|string $operationId): bool
    {
        $result = $this->apiRequest($settings, 'shop/operation_success?operation_id='.$operationId);
        if ($result['success']) {
            return true;
        }
        $err = $result['error'] ?? '';
        if (str_contains($err, 'already')) {
            return true;
        }
        Log::channel('scum')->error("[Wargm] claimOperation failed for {$operationId}: {$err}");

        return false;
    }

    // ===== Delivery =====

    public function processPlayer(string $steamId, ?string $playerName = null): array
    {
        if (empty($playerName) || $playerName === '—' || $playerName === 'Игрок') {
            $playerName = $this->resolvePlayerName($steamId);
        }

        $settings = $this->getSettings();
        $cd = $this->checkCommandCooldown($steamId, (int) $settings['commandCooldownSeconds']);
        if ($cd > 0) {
            return ['ok' => false, 'results' => ["Подождите {$cd}с до следующей проверки"], 'cards' => []];
        }
        $this->setCommandCooldown($steamId);

        $cards = array_values(array_filter($this->getCards(), fn ($c) => $c['enabled']));
        if (empty($cards)) {
            return ['ok' => false, 'results' => ['Нет активных карточек'], 'cards' => []];
        }

        $fetched = $this->fetchPendingOperations($settings, $steamId);
        $operations = $fetched['operations'];
        if (empty($operations)) {
            return ['ok' => false, 'results' => ['Покупок не найдено'], 'cards' => []];
        }

        // Получаем координаты игрока для записи в лог доставок
        $loc = $this->getPlayerLocation($steamId);
        $locStr = $loc ? sprintf('X=%.1f Y=%.1f Z=%.1f', $loc['x'], $loc['y'], $loc['z']) : null;

        $processed = [];
        $allResults = [];
        foreach ($operations as $i => $op) {
            $opId = $op['id'] ?? $op['operation_id'] ?? null;
            if (! $opId || $this->isDelivered((string) $opId)) {
                continue;
            }
            $offerId = (string) ($op['offer_id'] ?? $op['offerId'] ?? '');
            $matchCard = null;
            foreach ($cards as $c) {
                if ((string) $c['shopItemId'] === $offerId) {
                    $matchCard = $c;
                    break;
                }
            }
            if (! $matchCard) {
                Log::channel('scum')->warning("[WARGM] Operation {$opId} has unmatched offer_id: {$offerId}");

                continue;
            }
            // Проверяем онлайн игрока для предметов, требующих присутствия в игре
            $needsOnline = false;
            foreach ($matchCard['items'] as $item) {
                if (! in_array($item['type'] ?? '', ['vip', 'insurance'])) {
                    $needsOnline = true;
                    break;
                }
            }
            if ($needsOnline && ! $this->isPlayerOnline($steamId)) {
                $allResults[] = $matchCard['name'].': игрок не в сети (выдача ожидает входа в игру)';

                continue;
            }

            if ($i > 0) {
                sleep(1);
            }
            $execResult = $this->executeCard($matchCard, $steamId);

            // Если выдача не удалась — НЕ подтверждаем операцию на Wargm!
            if (! $execResult['success']) {
                $errSummary = implode(', ', $execResult['results']);
                Log::channel('scum')->warning("[WARGM] Delivery failed for {$steamId}, card {$matchCard['id']}: {$errSummary}");
                $allResults[] = $matchCard['name'].' (ошибка): '.$errSummary;

                continue;
            }

            $claimed = $this->claimOperation($settings, $opId);
            if (! $claimed) {
                continue;
            }
            $summary = implode(', ', $execResult['results']);
            $this->addDelivery((string) $opId, $steamId, (int) $matchCard['id'], $playerName, $locStr, $summary);
            $processed[] = ['card' => $matchCard, 'results' => $execResult['results']];
            $allResults[] = $matchCard['name'].': '.$summary;

            // Оповещаем игрока в приватный игровой чат
            $cardName = $matchCard['name'] ?? 'Товар';
            $this->rcon->sendCommand("SendChat 4 \"[Wargm] Вы получили {$cardName}. Спасибо за покупку!\" {$steamId}");
        }
        // Сбрасываем кулдаун при неудаче или если игрок был не в сети
        if (empty($processed)) {
            unset($this->lastCommandTime[$steamId]);
        }

        return [
            'ok' => count($processed) > 0,
            'results' => $allResults ?: ['Новых покупок для выдачи нет'],
            'cards' => $processed,
        ];
    }

    public function executeCard(array $card, string $steamId): array
    {
        if (! $this->rcon->isConnected()) {
            return ['success' => false, 'results' => ['RCON не подключён']];
        }
        $results = [];
        $errors = [];
        $balances = null;
        $hasCurrencies = false;
        foreach ($card['items'] as $item) {
            if (in_array($item['type'], ['money', 'gold', 'fame'])) {
                $hasCurrencies = true;
                break;
            }
        }
        if ($hasCurrencies) {
            $balances = $this->getPlayerBalance($steamId);
        }
        foreach ($card['items'] as $i => $item) {
            if ($i > 0) {
                usleep(1000000);
            } // 1 сек задержка между выдачей предметов для физики и очереди
            try {
                $r = $this->executeItem($item, $steamId, $balances);
                if ($r['success']) {
                    $results[] = $r['message'];
                    if ($balances && $item['type'] === 'money') {
                        $balances['normal'] += $this->getIncrementAmount($item);
                    } elseif ($balances && $item['type'] === 'gold') {
                        $balances['gold'] += $this->getIncrementAmount($item);
                    } elseif ($balances && $item['type'] === 'fame') {
                        $balances['fame'] += $this->getIncrementAmount($item);
                    }
                } else {
                    $errors[] = $r['message'];
                }
            } catch (\Throwable $e) {
                $errors[] = $item['type'].': '.$e->getMessage();
            }
        }
        $this->logDelivery($steamId, $card['name'] ?? 'ID:'.$card['id'], $results, $errors);

        return [
            'success' => empty($errors),
            'results' => $results ?: $errors,
        ];
    }

    private function getIncrementAmount(array $item): float
    {
        return max(0, (float) ($item['data']['amount'] ?? 0));
    }

    public function isPlayerOnline(string $steamId): bool
    {
        $loc = $this->getPlayerLocation($steamId);
        if ($loc !== null) {
            return true;
        }
        $r = $this->rcon->sendCommand('ListPlayers');
        if ($r['success'] && ! empty($r['response'])) {
            return str_contains($r['response'], $steamId);
        }

        return false;
    }

    private function getPlayerLocation(string $steamId): ?array
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

    private function getPlayerBalance(string $steamId): array
    {
        $r = $this->rcon->sendCommand('ListPlayers');
        if (! $r['success'] || empty($r['response'])) {
            return ['normal' => 0, 'gold' => 0, 'fame' => 0, 'found' => false];
        }
        $normal = 0;
        $gold = 0;
        $found = false;
        $matched = false;
        foreach (explode("\n", $r['response']) as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, $steamId)) {
                $found = true;
                $matched = true;
                if (preg_match('/Account balance:\s*([\d.+-]+)/i', $trimmed, $m1) || preg_match('/\bmoney=([\d.+-]+)/i', $trimmed, $m1)) {
                    $normal = (float) $m1[1];
                }
                if (preg_match('/Gold balance:\s*([\d.+-]+)/i', $trimmed, $g1) || preg_match('/\bgold=([\d.+-]+)/i', $trimmed, $g1)) {
                    $gold = (float) $g1[1];
                }
            } elseif ($matched) {
                if (preg_match('/^Account balance:\s*([\d.+-]+)/i', $trimmed, $m1) || preg_match('/^money=([\d.+-]+)/i', $trimmed, $m1)) {
                    $normal = (float) $m1[1];
                }
                if (preg_match('/^Gold balance:\s*([\d.+-]+)/i', $trimmed, $g1) || preg_match('/^gold=([\d.+-]+)/i', $trimmed, $g1)) {
                    $gold = (float) $g1[1];
                }
                if (preg_match('/^\s*\d+\.\s+/i', $trimmed)) {
                    $matched = false;
                }
            }
        }
        $fame = 0;
        if ($found) {
            $w = $this->rcon->sendCommand("Whois {$steamId}");
            if ($w['success'] && ! empty($w['response']) && preg_match('/\bfame[:\s]\s*([\d.]+)/i', $w['response'], $fm)) {
                $fame = (float) $fm[1];
            }
        }

        return ['normal' => $normal, 'gold' => $gold, 'fame' => $fame, 'found' => $found];
    }

    private function executeItem(array $item, string $steamId, ?array $preRead = null): array
    {
        $d = $item['data'] ?? [];
        switch ($item['type']) {
            case 'item':
                $name = $d['itemName'] ?? $d['shortname'] ?? '';
                $amount = (int) ($d['amount'] ?? 1);
                if ($amount <= 0) {
                    $amount = 1;
                }
                if (! $name) {
                    return ['success' => false, 'message' => 'Предмет: не указано название'];
                }
                // Синтаксис SCUM-RCON: SpawnItem <ItemClass> [Count] [Health] [Ammo] [Location "<X> <Y> <Z>" | TargetSteamID]
                // При спавне игроку слово Location НЕ используется!
                $cmd = "SpawnItem {$name} {$amount} {$steamId}";
                $r = $this->rcon->sendCommand($cmd);
                $resp = trim($r['response'] ?? '');
                $respLower = strtolower($resp);
                $isOk = $r['success'] && ! empty($resp)
                    && (str_contains($respLower, 'spawned') || str_contains($respLower, 'loading'))
                    && ! str_contains($respLower, 'not online')
                    && ! str_contains($respLower, 'failed')
                    && ! str_contains($respLower, 'err:');

                return [
                    'success' => $isOk,
                    'message' => $isOk ? "Предмет {$name}x{$amount}" : "Предмет {$name}: ".($resp ?: 'ОШИБКА'),
                ];

            case 'vehicle':
                $name = $d['vehicleName'] ?? $d['shortname'] ?? '';
                if (! $name) {
                    return ['success' => false, 'message' => 'ТС: не указано название'];
                }
                // Синтаксис SCUM-RCON: SpawnVehicle <VehicleClass> <SteamID | PlayerName>
                // При спавне игроку слово Location НЕ используется!
                $cmd = "SpawnVehicle {$name} {$steamId}";
                $r = $this->rcon->sendCommand($cmd);
                $resp = trim($r['response'] ?? '');
                $respLower = strtolower($resp);
                $isOk = $r['success'] && ! empty($resp)
                    && (str_contains($respLower, 'spawned') || str_contains($respLower, 'loading') || str_contains($respLower, 'created'))
                    && ! str_contains($respLower, 'not online')
                    && ! str_contains($respLower, 'failed')
                    && ! str_contains($respLower, 'err:');

                return [
                    'success' => $isOk,
                    'message' => $isOk ? "ТС {$name}" : "ТС {$name}: ".($resp ?: 'ОШИБКА'),
                ];

            case 'skill':
                $skill = $d['skillName'] ?? '';
                $level = $d['level'] ?? 1;
                if ($skill === 'ALL') {
                    $allSkills = ScumSkills::RCON_NAMES;
                    $ok = 0;
                    $fail = 0;
                    foreach ($allSkills as $s) {
                        $r = $this->rcon->sendCommand("SetSkillLevel {$s} {$level} {$steamId}");
                        if ($r['success']) {
                            $ok++;
                        } else {
                            $fail++;
                        }
                    }

                    return ['success' => $fail === 0, 'message' => "Все навыки установлены на {$level} ({$ok} успешно, {$fail} ошибок)"];
                }
                if (! $skill) {
                    return ['success' => false, 'message' => 'Навык: не указано название'];
                }
                $r = $this->rcon->sendCommand("SetSkillLevel {$skill} {$level} {$steamId}");

                return ['success' => $r['success'], 'message' => $r['success'] ? "Навык {$skill}→{$level}" : "Навык {$skill}: ".($r['response'] ?? 'ОШИБКА')];

            case 'attributes':
                $str = (int) ($d['strength'] ?? 5);
                $sta = (int) ($d['stamina'] ?? 5);
                $dex = (int) ($d['dexterity'] ?? 5);
                $intl = (int) ($d['intellect'] ?? 5);
                $r = $this->rcon->sendCommand("SetAttributes {$str} {$sta} {$dex} {$intl} {$steamId}");

                return ['success' => $r['success'], 'message' => $r['success'] ? "Атрибуты {$str}/{$sta}/{$dex}/{$intl}" : 'Атрибуты: '.($r['response'] ?? 'ОШИБКА')];

            case 'money':
                $amount = (int) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    return ['success' => false, 'message' => 'Деньги: неверная сумма'];
                }
                // Синтаксис SCUM-RCON: ChangeCurrencyBalance Cash <+Amount|-Amount> [SteamID]
                $r = $this->rcon->sendCommand("ChangeCurrencyBalance Cash +{$amount} {$steamId}");
                $resp = strtolower(trim($r['response'] ?? ''));
                $isOk = $r['success'] && ! str_contains($resp, 'failed') && ! str_contains($resp, 'err:') && ! str_contains($resp, 'not online');

                return ['success' => $isOk, 'message' => $isOk ? "Деньги +{$amount}" : 'Деньги: '.($r['response'] ?? 'ОШИБКА')];

            case 'gold':
                $amount = (int) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    return ['success' => false, 'message' => 'Золото: неверное количество'];
                }
                $r = $this->rcon->sendCommand("ChangeCurrencyBalance Gold +{$amount} {$steamId}");
                $resp = strtolower(trim($r['response'] ?? ''));
                $isOk = $r['success'] && ! str_contains($resp, 'failed') && ! str_contains($resp, 'err:') && ! str_contains($resp, 'not online');

                return ['success' => $isOk, 'message' => $isOk ? "Золото +{$amount}" : 'Золото: '.($r['response'] ?? 'ОШИБКА')];

            case 'fame':
                $amount = (int) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    return ['success' => false, 'message' => 'Слава: неверное количество'];
                }
                // Синтаксис SCUM-RCON: ChangeFamePoints <+Amount|-Amount> [SteamID]
                $r = $this->rcon->sendCommand("ChangeFamePoints +{$amount} {$steamId}");
                $resp = strtolower(trim($r['response'] ?? ''));
                $isOk = $r['success'] && ! str_contains($resp, 'failed') && ! str_contains($resp, 'err:') && ! str_contains($resp, 'not online');

                return ['success' => $isOk, 'message' => $isOk ? "Слава +{$amount}" : 'Слава: '.($r['response'] ?? 'ОШИБКА')];

            case 'cargo_drop':
                $loc = $this->getPlayerLocation($steamId);
                if (! $loc) {
                    return ['success' => false, 'message' => 'Дроп: не удалось определить координаты игрока'];
                }
                $cmd = sprintf('ScheduleWorldEvent BP_CargoDropEvent %d %d %d', $loc['x'], $loc['y'], $loc['z']);
                $r = $this->rcon->sendCommand($cmd);
                $resp = strtolower(trim($r['response'] ?? ''));
                $isOk = $r['success'] && ! str_contains($resp, 'failed') && ! str_contains($resp, 'err:');

                return ['success' => $isOk, 'message' => $isOk ? "Дроп в {$loc['x']},{$loc['y']},{$loc['z']}" : 'Дроп: '.($r['response'] ?? 'ОШИБКА')];

            case 'vip':
                $days = (int) ($d['days'] ?? 30);
                $this->grantVip($steamId, $days);

                return ['success' => true, 'message' => "VIP на {$days} дн."];

            case 'insurance':
                $claims = (int) ($d['claims'] ?? $d['amount'] ?? 1);
                $days = isset($d['days']) ? (int) $d['days'] : null;
                $insuranceService = app(InsuranceService::class);
                $res = $insuranceService->grantInsurance($steamId, null, (string) ($item['id'] ?? null), $claims, $days);

                return ['success' => $res['success'], 'message' => $res['message']];

            default:
                return ['success' => false, 'message' => 'Неизвестный тип: '.$item['type']];
        }
    }

    public function grantVip(string $steamId, int $days): void
    {
        $bot = $this->settings->getBotData();
        $vip = $bot['vip'] ?? ['enabled' => true, 'players' => [], 'starterBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0], 'dailyBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0]];
        $players = $vip['players'] ?? [];
        $found = false;
        foreach ($players as &$p) {
            if ($p['steamId'] === $steamId) {
                $base = (int) ($p['expiresAt'] ?? 0);
                $p['expiresAt'] = $base === 0 || $base < time() ? time() + $days * 86400 : $base + $days * 86400;
                $found = true;
                break;
            }
        }
        unset($p);
        if (! $found) {
            $players[] = ['steamId' => $steamId, 'expiresAt' => time() + $days * 86400, 'note' => 'WARGM'];
        }
        $vip['players'] = $players;
        $bot['vip'] = $vip;
        $this->settings->saveBotData(['vip' => $vip]);
    }

    public function exportCards(): string
    {
        $cards = array_map(fn ($c) => [
            'name' => $c['name'],
            'shopItemId' => $c['shopItemId'],
            'enabled' => $c['enabled'],
            'items' => $c['items'],
        ], $this->getCards());

        return json_encode($cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function importCards(string $json): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['imported' => 0, 'errors' => ['Invalid JSON']];
        }
        if (! is_array($data)) {
            return ['imported' => 0, 'errors' => ['Expected array']];
        }
        $imported = 0;
        $errors = [];
        foreach ($data as $item) {
            if (empty($item['name']) || empty($item['shopItemId'])) {
                $errors[] = 'Skipped: missing name or shopItemId';

                continue;
            }
            $id = $this->saveCard([
                'name' => $item['name'],
                'shopItemId' => $item['shopItemId'],
                'enabled' => $item['enabled'] ?? true,
                'items' => is_array($item['items'] ?? null) ? $item['items'] : [],
            ]);
            if ($id) {
                $imported++;
            } else {
                $errors[] = 'Failed to save: '.$item['name'];
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    private function logApiCall(string $endpoint, string $method, int $status, string $response): void
    {
        $path = storage_path('logs/wargm_api_'.now()->format('Y-m-d').'.log');
        $line = '['.now()->toISOString()."] [{$method}] {$endpoint} → {$status}: ".mb_substr($response, 0, 500)."\n";
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    private function logDelivery(string $steamId, string $cardName, array $results, array $errors): void
    {
        $path = storage_path('logs/wargm_deliveries_'.now()->format('Y-m-d').'.log');
        $ok = $results ? 'OK: '.implode(', ', $results) : '';
        $err = $errors ? ' ERR: '.implode(', ', $errors) : '';
        $line = '['.now()->toISOString()."] [{$steamId}] Card: {$cardName} — {$ok}{$err}\n";
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
