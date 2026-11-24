<?php

namespace App\Services\Scum;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Выполнение воркфлоу автоматизации — порт automation/ engine.ts + index.ts
 */
class AutomationService
{
    private RconService $rcon;

    private LolkaService $lolka;

    private WargmService $wargm;

    public function __construct(RconService $rcon, LolkaService $lolka, WargmService $wargm)
    {
        $this->rcon = $rcon;
        $this->lolka = $lolka;
        $this->wargm = $wargm;
    }

    // ===== CRUD =====

    public function getWorkflows(): array
    {
        return Cache::remember('automation_workflows', (int) config('panel.cache.automation_workflows_ttl', 5), function () {
            return DB::table('workflows')->orderBy('created_at', 'desc')->get()->map(fn ($w) => $this->hydrate($w))->toArray();
        });
    }

    public function getWorkflow(string $id): ?array
    {
        $row = DB::table('workflows')->where('id', $id)->first();

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(object $w): array
    {
        return [
            'id' => $w->id,
            'name' => $w->name,
            'enabled' => (bool) $w->enabled,
            'trigger' => json_decode($w->trigger, true) ?: [],
            'nodes' => json_decode($w->nodes, true) ?: [],
            'connections' => json_decode($w->connections, true) ?: [],
            'stats' => $w->stats ? json_decode($w->stats, true) : null,
            'createdAt' => $w->created_at,
            'updatedAt' => $w->updated_at,
        ];
    }

    public function saveWorkflow(array $wf): void
    {
        $now = now();
        $isUpdate = ! empty($wf['id']) && DB::table('workflows')->where('id', $wf['id'])->exists();
        DB::table('workflows')->updateOrInsert(
            ['id' => $wf['id']],
            [
                'name' => $wf['name'],
                'enabled' => ! empty($wf['enabled']),
                'trigger' => json_encode($wf['trigger'] ?? ['type' => 'chat_command', 'config' => ['trigger' => '']], JSON_UNESCAPED_UNICODE),
                'nodes' => json_encode($wf['nodes'] ?? [], JSON_UNESCAPED_UNICODE),
                'connections' => json_encode($wf['connections'] ?? [], JSON_UNESCAPED_UNICODE),
                'stats' => isset($wf['stats']) ? json_encode($wf['stats'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $isUpdate ? (DB::table('workflows')->where('id', $wf['id'])->value('created_at') ?? $now) : $now,
                'updated_at' => $now,
            ],
        );
        Cache::forget('automation_workflows');
    }

    public function deleteWorkflow(string $id): bool
    {
        $deleted = (bool) DB::table('workflows')->where('id', $id)->delete();
        Cache::forget('automation_workflows');

        return $deleted;
    }

    // ===== Triggers =====

    public function processChatCommand(string $trigger, array $ctx): void
    {
        $lower = mb_strtolower(ltrim($trigger, '!'));
        foreach ($this->getWorkflows() as $wf) {
            if (! $wf['enabled'] || ($wf['trigger']['type'] ?? '') !== 'chat_command') {
                continue;
            }
            $tc = $wf['trigger']['config'] ?? [];
            $match = mb_strtolower(ltrim($tc['trigger'] ?? '', '!')) === $lower;
            foreach ($tc['aliases'] ?? [] as $a) {
                if (mb_strtolower(ltrim((string) $a, '!')) === $lower) {
                    $match = true;
                    break;
                }
            }
            if (! $match) {
                continue;
            }
            if (! $this->checkCooldown($wf, $ctx['steamId'] ?? '')) {
                continue;
            }
            $fullCtx = array_merge($wf, $ctx, [
                'workflowId' => $wf['id'],
                'trigger' => $wf['trigger'],
                'message' => $trigger.(count($ctx['args'] ?? []) ? ' '.implode(' ', $ctx['args']) : ''),
            ]);
            $result = $this->execute($wf, $fullCtx);
            $this->updateStats($wf, $result['success']);
        }
    }

    public function processEvent(string $eventType, array $eventData): void
    {
        foreach ($this->getWorkflows() as $wf) {
            if (! $wf['enabled'] || ($wf['trigger']['type'] ?? '') !== 'event') {
                continue;
            }
            if (($wf['trigger']['config']['event'] ?? '') !== $eventType) {
                continue;
            }
            $fullCtx = array_merge($wf, [
                'workflowId' => $wf['id'],
                'trigger' => $wf['trigger'],
                'eventData' => $eventData,
                'steamId' => $eventData['steamId'] ?? '',
                'playerName' => $eventData['playerName'] ?? '',
                'vars' => [],
                'playerVars' => [],
                'args' => [],
                'message' => '',
                'channel' => 'event',
                'nodeResults' => [],
            ]);
            $result = $this->execute($wf, $fullCtx);
            $this->updateStats($wf, $result['success']);
        }
    }

    public function testWorkflow(array $wf, array $ctxOverride = []): array
    {
        $ctx = array_merge([
            'workflowId' => $wf['id'] ?? 'test',
            'trigger' => $wf['trigger'] ?? ['type' => 'chat_command', 'config' => ['trigger' => '']],
            'vars' => [],
            'playerVars' => [],
            'steamId' => $ctxOverride['steamId'] ?? '76561198000000000',
            'playerName' => $ctxOverride['playerName'] ?? 'TestPlayer',
            'args' => $ctxOverride['args'] ?? [],
            'message' => $ctxOverride['message'] ?? '',
            'channel' => $ctxOverride['channel'] ?? 'global',
            'eventData' => $ctxOverride['eventData'] ?? [],
            'nodeResults' => [],
            'rconCommands' => [],
        ], $ctxOverride);

        return $this->execute($wf, $ctx);
    }

    /** Обработка расписаний — вызывается из очереди/крона */
    public function processSchedules(): void
    {
        foreach ($this->getWorkflows() as $wf) {
            if (! $wf['enabled'] || ($wf['trigger']['type'] ?? '') !== 'schedule') {
                continue;
            }
            $cfg = $wf['trigger']['config'] ?? [];
            if (($cfg['type'] ?? '') !== 'interval') {
                continue;
            }
            if (empty($cfg['intervalMinutes']) || (int) $cfg['intervalMinutes'] <= 0) {
                continue;
            }
            $lastKey = 'schedule_last_'.$wf['id'];
            $last = cache()->get($lastKey, 0);
            $interval = (int) $cfg['intervalMinutes'] * 60;
            if (time() - $last < $interval) {
                continue;
            }
            cache()->forever($lastKey, time());
            $result = $this->execute($wf, [
                'workflowId' => $wf['id'],
                'trigger' => $wf['trigger'],
                'vars' => [],
                'playerVars' => [],
                'steamId' => '',
                'playerName' => '',
                'args' => [],
                'message' => '',
                'channel' => 'schedule',
                'eventData' => ['type' => 'schedule', 'time' => now()->toISOString()],
                'nodeResults' => [],
            ]);
            $this->updateStats($wf, $result['success']);
        }
    }

    protected function checkCooldown(array $wf, string $steamId): bool
    {
        if (($wf['trigger']['type'] ?? '') !== 'chat_command') {
            return true;
        }
        $cd = (int) ($wf['trigger']['config']['cooldownSeconds'] ?? 0);
        if ($cd <= 0) {
            return true;
        }
        // Кулдауны храним в кэше (database) с TTL вместо растущего in-memory массива —
        // это исключает утечку памяти в долгоживущем RCON-демоне и переживает рестарт.
        $key = 'automation_cd_'.$wf['id'].':'.$steamId;
        if (Cache::get($key)) {
            return false;
        }
        Cache::put($key, true, $cd);

        return true;
    }

    private function updateStats(array $wf, bool $success): void
    {
        $stats = $wf['stats'] ?? ['runs' => 0, 'lastRun' => '', 'errors' => 0];
        $stats['runs'] = ($stats['runs'] ?? 0) + 1;
        $stats['lastRun'] = now()->toISOString();
        if (! $success) {
            $stats['errors'] = ($stats['errors'] ?? 0) + 1;
        }
        DB::table('workflows')->where('id', $wf['id'])->update(['stats' => json_encode($stats, JSON_UNESCAPED_UNICODE)]);
    }

    // ===== Engine =====

    /** @param array $wf полный воркфлоу */
    public function execute(array $wf, array $ctx): array
    {
        $start = microtime(true);
        $nodes = $wf['nodes'] ?? [];
        $connections = $wf['connections'] ?? [];
        $nodeMap = [];
        foreach ($nodes as $n) {
            $nodeMap[$n['id']] = $n;
        }

        $ctx['nodeResults'] = $ctx['nodeResults'] ?? [];
        $ctx['vars'] = $ctx['vars'] ?? [];
        $ctx['playerVars'] = $ctx['playerVars'] ?? [];
        $ctx['startTime'] = (int) (microtime(true) * 1000);

        $startConns = array_values(array_filter($connections, fn ($c) => $c['fromNodeId'] === 'trigger'));
        if (empty($startConns)) {
            return ['success' => true, 'rconCommands' => $ctx['rconCommands'] ?? []];
        }

        foreach ($startConns as $conn) {
            $result = $this->executeNode($nodeMap, $connections, $conn['toNodeId'], $ctx, $start);
            if (! $result['success']) {
                return ['success' => false, 'error' => $result['error'] ?? 'Workflow failed', 'rconCommands' => $ctx['rconCommands'] ?? []];
            }
        }

        return ['success' => true, 'rconCommands' => $ctx['rconCommands'] ?? []];
    }

    private function executeNode(array $nodeMap, array $connections, string $nodeId, array &$ctx, float $start): array
    {
        if (! isset($nodeMap[$nodeId])) {
            return ['success' => false, 'error' => "Node {$nodeId} not found"];
        }
        $node = $nodeMap[$nodeId];

        // Таймаут workflow
        if ((microtime(true) - $start) > (int) config('panel.http.workflow_timeout', 30)) {
            return ['success' => false, 'error' => 'Workflow timeout ('.(int) config('panel.http.workflow_timeout', 30).'s)'];
        }

        // Comment - passthrough
        if ($node['type'] === 'comment') {
            return $this->followOutput($nodeMap, $connections, $node, $ctx, ['success' => true, 'output' => null], $start);
        }

        // ForEach
        if ($node['type'] === 'for_each') {
            return $this->executeForEach($nodeMap, $connections, $node, $ctx, $start);
        }

        $result = $this->dispatchNode($node, $ctx, $start);
        $ctx['nodeResults'][$node['id']] = $result['output'] ?? null;

        return $this->followOutput($nodeMap, $connections, $node, $ctx, $result, $start);
    }

    private function followOutput(array $nodeMap, array $connections, array $node, array &$ctx, array $result, float $start): array
    {
        if (empty($result['success'])) {
            $errorConn = null;
            foreach ($connections as $c) {
                if ($c['fromNodeId'] === $node['id'] && ($c['fromPort'] ?? '') === 'error') {
                    $errorConn = $c;
                    break;
                }
            }
            if ($errorConn) {
                return $this->executeNode($nodeMap, $connections, $errorConn['toNodeId'], $ctx, $start);
            }

            return $result;
        }

        $outputPort = $result['outputPort'] ?? 'main';
        $nextConns = array_values(array_filter($connections, fn ($c) => $c['fromNodeId'] === $node['id'] && ($c['fromPort'] ?? 'main') === $outputPort));
        foreach ($nextConns as $conn) {
            $subResult = $this->executeNode($nodeMap, $connections, $conn['toNodeId'], $ctx, $start);
            if (! $subResult['success']) {
                return $subResult;
            }
        }

        return ['success' => true];
    }

    private function executeForEach(array $nodeMap, array $connections, array $node, array &$ctx, float $start): array
    {
        $cfg = $node['config'] ?? [];
        $source = $cfg['source'] ?? '';
        $arr = [];
        if (str_starts_with($source, 'vars.')) {
            $key = substr($source, 5);
            if (isset($ctx['vars'][$key])) {
                $arr = is_array($ctx['vars'][$key]) ? $ctx['vars'][$key] : [$ctx['vars'][$key]];
            }
        } elseif (isset($ctx['vars'][$source])) {
            $arr = is_array($ctx['vars'][$source]) ? $ctx['vars'][$source] : [$ctx['vars'][$source]];
        } elseif (isset($ctx['nodeResults'][$source])) {
            $arr = is_array($ctx['nodeResults'][$source]) ? $ctx['nodeResults'][$source] : [$ctx['nodeResults'][$source]];
        }

        $limit = (int) ($cfg['limit'] ?? 0);
        $limit = $limit > 0 ? min($limit, count($arr)) : count($arr);
        $itemVar = $cfg['itemVar'] ?? 'item';
        $outputConns = array_values(array_filter($connections, fn ($c) => $c['fromNodeId'] === $node['id'] && ($c['fromPort'] ?? 'main') === 'main'));

        for ($i = 0; $i < $limit; $i++) {
            $ctx['vars'][$itemVar] = $arr[$i];
            $ctx['vars']['index'] = $i;
            foreach ($outputConns as $conn) {
                $sub = $this->executeNode($nodeMap, $connections, $conn['toNodeId'], $ctx, $start);
                if (! $sub['success']) {
                    return $sub;
                }
            }
        }

        return ['success' => true, 'output' => "Iterated {$limit} items"];
    }

    private function dispatchNode(array $node, array &$ctx, float $start): array
    {
        $cfg = $node['config'] ?? [];
        switch ($node['type']) {
            case 'rcon_command':
                return $this->rconNode($cfg, $ctx);
            case 'discord_webhook': // обратная совместимость
            case 'lolka_webhook':
                return $this->lolkaNode($cfg, $ctx);
            case 'condition':
                return $this->conditionNode($cfg, $ctx);
            case 'delay':
                return $this->delayNode($cfg);
            case 'variable':
                return $this->variableNode($cfg, $ctx);
            case 'log':
                return $this->logNode($cfg, $ctx);
            case 'http_request':
                return $this->httpNode($cfg, $ctx);
            case 'wargm_process':
                return $this->wargmNode($cfg, $ctx);
            default:
                return ['success' => false, 'error' => "No handler for node type: {$node['type']}"];
        }
    }

    // ===== Node handlers =====

    private function rconNode(array $cfg, array &$ctx): array
    {
        $resolved = $this->resolveTemplates($cfg['command'] ?? '', $ctx);
        $ctx['rconCommands'][] = $resolved;
        Log::channel('scum')->info('[automation] rconNode command: '.$resolved);
        $retries = (int) ($cfg['retryCount'] ?? 0);
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $result = $this->rcon->sendCommand($resolved);
                if (! $result['success']) {
                    Log::channel('scum')->error('[automation] rconNode FAILED: '.($result['error'] ?? 'unknown'));
                    if ($attempt < $retries) {
                        continue;
                    }

                    return ['success' => false, 'error' => $result['error'] ?? 'RCON command failed'];
                }
                Log::channel('scum')->info('[automation] rconNode OK: '.mb_substr($result['response'] ?? '', 0, 200));
                if (! empty($cfg['parseResponse']) && ! empty($cfg['responseVar'])) {
                    $ctx['vars'][$cfg['responseVar']] = $result['response'];
                }

                return ['success' => true, 'output' => $result['response']];
            } catch (\Throwable $e) {
                if ($attempt < $retries) {
                    continue;
                }

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => false, 'error' => 'RCON command failed after retries'];
    }

    private function lolkaNode(array $cfg, array &$ctx): array
    {
        $keyMap = ['admin' => 'adminLogWebhook', 'chat' => 'chatWebhook', 'command' => 'commandWebhook', 'vehicle' => 'vehicleWebhook', 'login' => 'loginWebhook', 'status' => 'serverStatusWebhook'];
        $key = $keyMap[$cfg['webhookKey'] ?? ''] ?? null;
        if (! $key) {
            return ['success' => false, 'error' => 'Unknown webhook key: '.($cfg['webhookKey'] ?? '')];
        }
        $url = $this->lolka->getConfig()[$key] ?? '';
        if (! $url) {
            return ['success' => false, 'error' => "Webhook '{$cfg['webhookKey']}' not configured"];
        }
        $content = $this->resolveTemplates($cfg['content'] ?? '', $ctx);
        $payload = ['content' => $content, 'allowed_mentions' => ['parse' => []]];
        if (! empty($cfg['username'])) {
            $payload['username'] = $cfg['username'];
        }
        if (! empty($cfg['avatarUrl'])) {
            $payload['avatar_url'] = $cfg['avatarUrl'];
        }
        try {
            $response = Http::timeout((int) config('panel.http.automation_timeout', 10))
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->post($url, $payload);
            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Lolka returned '.$response->status()];
            }

            return ['success' => true, 'output' => 'Sent to '.$cfg['webhookKey']];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function conditionNode(array $cfg, array &$ctx): array
    {
        $field = $this->resolveTemplates($cfg['field'] ?? '', $ctx);
        $value = $this->resolveTemplates($cfg['value'] ?? '', $ctx);
        $op = $cfg['operator'] ?? 'eq';
        $result = false;
        switch ($op) {
            case 'eq': $result = $field === $value;
                break;
            case 'ne': $result = $field !== $value;
                break;
            case 'gt': $result = (float) $field > (float) $value;
                break;
            case 'gte': $result = (float) $field >= (float) $value;
                break;
            case 'lt': $result = (float) $field < (float) $value;
                break;
            case 'lte': $result = (float) $field <= (float) $value;
                break;
            case 'contains': $result = str_contains((string) $field, (string) $value);
                break;
            case 'regex':
                $result = @preg_match('/'.str_replace('/', '\/', $value).'/', $field) === 1;
                break;
            case 'exists': $result = strlen((string) $field) > 0;
                break;
            case 'in': $result = in_array($field, array_map('trim', explode(',', $value)));
                break;
        }

        return ['success' => true, 'output' => $result, 'outputPort' => $result ? 'true' : 'false'];
    }

    private function delayNode(array $cfg): array
    {
        $units = ['seconds' => 1, 'minutes' => 60, 'hours' => 3600];
        $secs = (int) ($cfg['duration'] ?? 0) * ($units[$cfg['unit'] ?? 'seconds'] ?? 1);
        if ($secs > 0) {
            sleep($secs);
        }

        return ['success' => true, 'output' => "Delayed {$cfg['duration']} {$cfg['unit']}"];
    }

    private function variableNode(array $cfg, array &$ctx): array
    {
        $value = $this->resolveTemplates($cfg['value'] ?? '', $ctx);
        $name = $cfg['name'] ?? 'var';
        $scope = $cfg['scope'] ?? 'workflow';
        if ($scope === 'player') {
            $ctx['playerVars'][$ctx['steamId']] = $ctx['playerVars'][$ctx['steamId']] ?? [];
            $target = &$ctx['playerVars'][$ctx['steamId']];
        } elseif ($scope === 'persistent') {
            $persisted = $this->readPersistent();
            switch ($cfg['action'] ?? 'set') {
                case 'set': $persisted[$name] = $value;
                    break;
                case 'clear': unset($persisted[$name]);
                    $persisted[$name] = null;
                    break;
            }
            $this->writePersistent($persisted);

            return ['success' => true, 'output' => $persisted[$name] ?? null];
        } else {
            $target = &$ctx['vars'];
        }
        switch ($cfg['action'] ?? 'set') {
            case 'set': $target[$name] = $value;
                break;
            case 'increment': $target[$name] = (float) ($target[$name] ?? 0) + (float) ($value ?: 1);
                break;
            case 'append':
                $arr = isset($target[$name]) ? (is_array($target[$name]) ? $target[$name] : [$target[$name]]) : [];
                $arr[] = $value;
                $target[$name] = $arr;
                break;
            case 'clear': unset($target[$name]);
                $target[$name] = null;
                break;
        }

        return ['success' => true, 'output' => $target[$name] ?? null];
    }

    private function logNode(array $cfg, array &$ctx): array
    {
        $resolved = $this->resolveTemplates($cfg['message'] ?? '', $ctx);
        $prefix = '[Automation:'.($ctx['workflowId'] ?? '').']';
        $level = $cfg['level'] ?? 'info';
        if ($level === 'warn') {
            Log::channel('scum')->warning($prefix.' '.$resolved);
        } elseif ($level === 'error') {
            Log::channel('scum')->error($prefix.' '.$resolved);
        } else {
            Log::channel('scum')->info($prefix.' '.$resolved);
        }

        return ['success' => true, 'output' => 'Logged ['.$level.']: '.$resolved];
    }

    private function httpNode(array $cfg, array &$ctx): array
    {
        $url = $this->resolveTemplates($cfg['url'] ?? '', $ctx);
        $body = $this->resolveTemplates($cfg['body'] ?? '', $ctx);
        $method = strtoupper($cfg['method'] ?? 'GET');
        $headers = $cfg['headers'] ?? [];

        if (! $this->isSafeUrl($url)) {
            return ['success' => false, 'error' => 'Заблокирован запрос к внутреннему или небезопасному адресу (SSRF protection)'];
        }

        try {
            $request = Http::timeout((int) config('panel.http.automation_pool_timeout', 15))
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->withHeaders($headers);
            if (in_array($method, ['POST', 'PUT'], true)) {
                $request = $request->withBody($body ?: '', 'application/json');
            }
            $response = match ($method) {
                'POST' => $request->post($url),
                'PUT' => $request->put($url),
                'DELETE' => $request->delete($url),
                default => $request->get($url),
            };
            $response->throw();
            $text = $response->body();
            if (! empty($cfg['responseVar'])) {
                $ctx['vars'][$cfg['responseVar']] = $text;
            }

            return ['success' => true, 'output' => $text, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function wargmNode(array $cfg, array &$ctx): array
    {
        $steamId = $ctx['steamId'] ?? '';
        if (! $steamId) {
            return ['success' => false, 'error' => 'No steamId in context'];
        }

        if (! $this->rcon->isConnected()) {
            $this->sendPlayerChat('WARGM: RCON не подключён, попробуйте позже', $steamId);

            return ['success' => false, 'error' => 'RCON not connected'];
        }

        try {
            $result = $this->wargm->processPlayer($steamId);
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[automation] wargmNode error: {$e->getMessage()}");
            $this->sendPlayerChat('WARGM: ошибка выдачи — '.$e->getMessage(), $steamId);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        $cards = $result['cards'] ?? [];
        $sent = 0;
        foreach (array_slice($cards, 0, 5) as $card) {
            $cardName = $card['card']['name'] ?? 'Товар';
            $this->sendPlayerChat("Вы получили {$cardName} с маркетплейса Wargm. Спасибо за покупку!", $steamId);
            $sent++;
        }
        if (count($cards) > 5) {
            $this->sendPlayerChat('...и ещё '.(count($cards) - 5).' предметов', $steamId);
            $sent++;
        }

        $messages = $result['results'] ?? [];

        $ok = $result['ok'] ?? false;
        Log::channel('scum')->info("[automation] wargmNode steam={$steamId} ok={$ok} delivered={$sent} messages=".implode(' | ', $messages));

        if ($sent === 0 && $messages) {
            $this->sendPlayerChat('WARGM: '.$messages[0], $steamId);
        }

        $ctx['vars']['wargm_ok'] = $ok ? '1' : '0';
        $ctx['vars']['wargm_delivered'] = (string) $sent;
        $ctx['vars']['wargm_results'] = implode('; ', $messages);

        return ['success' => true, 'output' => $ok ? "Delivered {$sent} items" : 'No items to deliver'];
    }

    private function sendPlayerChat(string $text, string $steamId): void
    {
        $text = str_replace('"', "'", $text);
        $result = $this->rcon->sendCommand("SendChat 4 \"{$text}\" {$steamId}");
        if (! $result['success']) {
            Log::channel('scum')->error("[automation] sendPlayerChat failed: steam={$steamId} msg={$text} error=".($result['error'] ?? 'unknown'));
        }
    }

    public function resolveTemplates(string $template, array $ctx): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($m) use ($ctx) {
            $key = trim($m[1]);
            if (preg_match('/^random\.int\((\d+),(\d+)\)$/', $key, $m2)) {
                return (string) random_int((int) $m2[1], (int) $m2[2]);
            }
            if ($key === 'random.uuid') {
                return (string) Str::uuid();
            }
            if ($key === 'now') {
                return now()->toISOString();
            }
            if (str_starts_with($key, 'now.')) {
                $fmt = substr($key, 4);
                if ($fmt === 'date') {
                    return now()->format('Y-m-d');
                }
                if ($fmt === 'time') {
                    return now()->format('H:i:s');
                }
                if ($fmt === 'unix') {
                    return (string) time();
                }
            }
            if (str_starts_with($key, 'player.')) {
                $k = substr($key, 7);

                return (string) ($ctx['playerVars'][$ctx['steamId']][$k] ?? '');
            }
            if (str_starts_with($key, 'vars.')) {
                $k = substr($key, 5);

                return (string) ($ctx['vars'][$k] ?? '');
            }
            if (str_starts_with($key, 'event.')) {
                $k = substr($key, 6);

                return (string) ($ctx['eventData'][$k] ?? '');
            }
            if (str_starts_with($key, 'result.')) {
                $k = substr($key, 7);
                if (array_key_exists($k, $ctx['nodeResults'] ?? [])) {
                    $v = $ctx['nodeResults'][$k];

                    return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }

                return '';
            }
            $direct = ['steamId' => $ctx['steamId'] ?? '', 'playerName' => $ctx['playerName'] ?? '', 'message' => $ctx['message'] ?? '', 'channel' => $ctx['channel'] ?? '', 'workflowId' => $ctx['workflowId'] ?? ''];
            if (array_key_exists($key, $direct)) {
                return str_replace(["\r", "\n"], [' ', ' '], (string) $direct[$key]);
            }
            if ($key === 'argsText') {
                return str_replace(["\r", "\n"], [' ', ' '], (string) implode(' ', $ctx['args'] ?? []));
            }
            if (preg_match('/^args\[(\d+)\]$/', $key, $m2)) {
                return str_replace(["\r", "\n"], [' ', ' '], (string) ($ctx['args'][(int) $m2[1]] ?? ''));
            }
            if (str_starts_with($key, 'persist.')) {
                $k = substr($key, 8);

                return (string) ($this->readPersistent()[$k] ?? '');
            }

            return '';
        }, $template) ?? $template;
    }

    // ===== Persistent storage (JSON file) =====

    private string $persistentFile = '';

    private function getPersistentFile(): string
    {
        if ($this->persistentFile === '') {
            $this->persistentFile = storage_path('app/persistent.json');
        }

        return $this->persistentFile;
    }

    private function readPersistent(): array
    {
        $file = $this->getPersistentFile();
        if (! file_exists($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true) ?: [];
    }

    public function writePersistent(array $data): void
    {
        $existing = $this->readPersistent();
        $merged = array_merge($existing, $data);
        file_put_contents($this->getPersistentFile(), json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
