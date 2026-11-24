<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;

/**
 * Обработчик всех чат-команд — порт ftpLogWatcher.processChatCommand()
 * !help !balance !location !online !startpack !dailypack !teleport
 * !vip !savedom !home !homes !deletedom !rating
 */
class ChatCommandProcessor
{
    public const COMMAND_ALIASES = [
        '!баланс' => '!balance',
        '!помощь' => '!help',
        '!онлайн' => '!online',
        '!локация' => '!location',
        '!координаты' => '!location',
        '!стартпак' => '!startpack',
        '!дейлипак' => '!dailypack',
        '!телепорт' => '!teleport',
        '!вип' => '!vip',
        '!варгм' => '!wargm',
        '!сохранитьдом' => '!savedom',
        '!дом' => '!home',
        '!дома' => '!homes',
        '!удалитьдом' => '!deletedom',
        '!рейтинг' => '!rating',
        '!страховка' => '!insurance',
    ];

    /** @var array{trigger:string,rconCommand:string,description:string,isHelp?:bool,hideFromHelp?:bool,helpTrigger?:string}[] */
    private array $chatCommands = [
        ['trigger' => '!balance', 'rconCommand' => 'ListPlayers', 'description' => 'Check your balance', 'hideFromHelp' => true],
        ['trigger' => '!location', 'rconCommand' => 'ListPlayers', 'description' => 'Show your location', 'hideFromHelp' => true],
        ['trigger' => '!online', 'rconCommand' => 'ListPlayers', 'description' => 'Show online players', 'hideFromHelp' => true],
        ['trigger' => '!startpack', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!стартпак'],
        ['trigger' => '!dailypack', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!дейлипак'],
        ['trigger' => '!teleport', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!телепорт'],
        ['trigger' => '!vip', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!вип'],
        ['trigger' => '!savedom', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!сохранитьдом'],
        ['trigger' => '!home', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!дом'],
        ['trigger' => '!homes', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!дома'],
        ['trigger' => '!deletedom', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!удалитьдом'],
        ['trigger' => '!rating', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!рейтинг'],
        ['trigger' => '!wargm', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!варгм'],
        ['trigger' => '!insurance', 'rconCommand' => '', 'description' => '', 'helpTrigger' => '!страховка'],
        ['trigger' => '!help', 'rconCommand' => '', 'description' => '', 'isHelp' => true],
    ];

    public function getCommandDefinitions(): array
    {
        return $this->chatCommands;
    }

    private RconService $rcon;

    private SettingsService $settings;

    private AutomationService $automation;

    private WargmService $wargm;

    public function __construct(
        RconService $rcon,
        SettingsService $settings,
        AutomationService $automation,
        public LolkaService $lolka,
        WargmService $wargm,
    ) {
        $this->rcon = $rcon;
        $this->settings = $settings;
        $this->automation = $automation;
        $this->wargm = $wargm;
    }

    public function getPacksConfig(): array
    {
        return $this->settings->getBotData()['packs'] ?? $this->settings->defaultBotData()['packs'];
    }

    public function getCooldowns(): array
    {
        return $this->settings->getBotData()['packCooldowns'] ?? [];
    }

    public function resetPlayerCooldown(string $steamId, ?string $packType = null): void
    {
        $cd = $this->getCooldowns();
        if ($packType) {
            $key = $packType === 'starter' ? "!startpack_{$steamId}" : "!dailypack_{$steamId}";
            unset($cd[$key]);
        } else {
            unset($cd["!startpack_{$steamId}"], $cd["!dailypack_{$steamId}"]);
        }
        $this->settings->saveBotData(['packCooldowns' => $cd]);
    }

    public function getHomeLocations(): array
    {
        return $this->settings->getBotData()['homeLocations'] ?? [];
    }

    private function isVip(string $steamId, array $vipConfig): bool
    {
        if (empty($vipConfig['enabled'])) {
            return false;
        }
        foreach ($vipConfig['players'] ?? [] as $p) {
            if ($p['steamId'] === $steamId) {
                if ((int) ($p['expiresAt'] ?? 0) > 0 && time() > (int) $p['expiresAt']) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    private function privateMsg(string $text, string $steamId): void
    {
        $text = str_replace(["\r", "\n", '"'], ['', '', "'"], $text);
        $cleanSteamId = preg_replace('/[^0-9]/', '', $steamId);
        if ($cleanSteamId !== '') {
            $this->rcon->sendCommand("SendChat 2 \"{$text}\" {$cleanSteamId}");
        }
    }

    public function process(string $steamId, string $playerName, string $message): void
    {
        if (! $this->rcon->isConnected()) {
            Log::channel('scum')->info('[ChatCommand] RCON not connected, skipping command');

            return;
        }
        $trimmed = trim($message);
        Log::channel('scum')->info('[ChatCommand] process:', ['steamId' => $steamId, 'playerName' => $playerName, 'trimmed' => $trimmed]);
        if (! str_starts_with($trimmed, '!')) {
            return;
        }

        $lower = mb_strtolower($trimmed);
        $parts = preg_split('/\s+/', $lower) ?: [];
        $baseCmd = $parts[0];
        $cmdKey = self::COMMAND_ALIASES[$baseCmd] ?? $baseCmd;

        $this->handle($cmdKey, $steamId, $playerName, $trimmed, $parts);
    }

    public function handle(string $cmdKey, string $steamId, string $playerName, string $trimmed, array $parts): void
    {
        // Help
        if ($cmdKey === '!help' || $cmdKey === '!commands') {
            $cmds = array_map(fn ($c) => $c['helpTrigger'] ?? $c['trigger'], array_filter($this->chatCommands, fn ($c) => empty($c['isHelp']) && empty($c['hideFromHelp'])));
            $helpMsg = 'Команды: '.implode(', ', $cmds);
            $this->privateMsg($helpMsg, $steamId);

            return;
        }

        // Packs
        if ($cmdKey === '!startpack' || $cmdKey === '!dailypack') {
            $this->handlePack($cmdKey, $steamId, $trimmed);

            return;
        }

        // Teleport
        if ($cmdKey === '!teleport') {
            $this->handleTeleport($cmdKey, $steamId, $trimmed, $parts);

            return;
        }

        // VIP
        if ($cmdKey === '!vip') {
            $this->handleVip($cmdKey, $steamId, $playerName);

            return;
        }

        // SaveHome
        if ($cmdKey === '!savedom') {
            $this->handleSaveDom($cmdKey, $steamId, $trimmed, $parts);

            return;
        }
        if ($cmdKey === '!homes') {
            $this->handleHomes($cmdKey, $steamId);

            return;
        }
        if ($cmdKey === '!deletedom') {
            $this->handleDeleteDom($cmdKey, $steamId, $parts);

            return;
        }
        if ($cmdKey === '!home') {
            $this->handleHome($cmdKey, $steamId, $parts);

            return;
        }

        // Rating
        if ($cmdKey === '!rating' || $cmdKey === '!рейтинг') {
            $this->privateMsg('Рейтинг: используйте веб-панель для просмотра таблицы лидеров', $steamId);

            return;
        }

        // WARGM
        if ($cmdKey === '!wargm') {
            $this->handleWargm($cmdKey, $steamId, $playerName);

            return;
        }

        // Insurance
        if ($cmdKey === '!insurance') {
            $this->handleInsurance($cmdKey, $steamId, $playerName, $trimmed, $parts);

            return;
        }

        // Automation workflows (custom triggers)
        $args = array_values(array_filter(explode(' ', mb_substr($trimmed, mb_strlen($cmdKey)))));
        $this->automation->processChatCommand($cmdKey, [
            'steamId' => $steamId,
            'playerName' => $playerName,
            'args' => $args,
            'channel' => 'global',
        ]);

        // Generic commands (balance, location, online)
        foreach ($this->chatCommands as $cmd) {
            if ($cmdKey !== $cmd['trigger']) {
                continue;
            }
            if (! empty($cmd['isHelp']) || empty($cmd['rconCommand'])) {
                continue;
            }
            $this->runGenericCommand($cmd, $cmdKey, $steamId, $playerName, $trimmed);

            return;
        }
    }

    // ===== WARGM handler =====

    private function handleWargm(string $cmdKey, string $steamId, string $playerName): void
    {
        try {
            $result = $this->wargm->processPlayer($steamId, $playerName);
            $cards = $result['cards'] ?? [];
            if (empty($result['ok']) || empty($cards)) {
                $messages = $result['results'] ?? [];
                $msg = $messages[0] ?? 'Новых покупок для выдачи нет';
                $this->privateMsg("WARGM: {$msg}", $steamId);
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[ChatCommand] handleWargm error: {$e->getMessage()}");
            $this->privateMsg('WARGM: ошибка выдачи — '.$e->getMessage(), $steamId);
        }
    }

    // ===== Pack handlers =====

    private function handlePack(string $cmdKey, string $steamId, string $trimmed): void
    {
        $isDaily = $cmdKey === '!dailypack';
        $bot = $this->settings->getBotData();
        $pack = $isDaily ? $bot['packs']['daily'] : $bot['packs']['starter'];
        if (empty($pack['enabled']) || empty($pack['items'])) {
            $this->privateMsg(($isDaily ? 'Ежедневный' : 'Стартовый').' набор недоступен', $steamId);

            return;
        }
        $cooldowns = $bot['packCooldowns'] ?? [];
        $label = $isDaily ? 'Ежедневный' : 'Стартовый';
        $cdHours = (float) ($pack['cooldownHours'] ?? 0);

        if ($cdHours > 0) {
            $lastClaim = (float) ($cooldowns[$cmdKey.'_'.$steamId] ?? 0);
            $elapsed = (time() * 1000 - $lastClaim) / 3600000;
            if ($elapsed < $cdHours) {
                $remaining = (int) ceil($cdHours - $elapsed);
                $this->privateMsg("{$label} набор: кулдаун {$remaining}ч", $steamId);

                return;
            }
        } elseif ($cdHours < 0) {
            $key = $cmdKey.'_'.$steamId;
            if (! empty($cooldowns[$key])) {
                $this->privateMsg("{$label} набор уже получен", $steamId);

                return;
            }
        }

        $loc = $this->getListPlayerData($steamId);
        $succeeded = [];
        foreach ($pack['items'] as $item) {
            if ($loc && ! empty($loc['x'])) {
                $cmd = sprintf('SpawnItem %s %d Location "%.1f %.1f %.1f"', $item['itemId'], (int) $item['amount'], (float) ($loc['x'] + 100.0), (float) $loc['y'], (float) ($loc['z'] + 30.0));
            } else {
                $cmd = "SpawnItem {$item['itemId']} {$item['amount']} {$steamId}";
            }
            $r = $this->rcon->sendCommand($cmd);
            if ($r['success']) {
                $succeeded[] = "{$item['itemId']}x{$item['amount']}";
            }
        }

        // VIP бонусы
        $vip = $bot['vip'] ?? [];
        if ($this->isVip($steamId, $vip)) {
            $bonus = $isDaily ? ($vip['dailyBonus'] ?? []) : ($vip['starterBonus'] ?? []);
            foreach ($bonus['items'] ?? [] as $item) {
                if ($loc && ! empty($loc['x'])) {
                    $cmd = sprintf('SpawnItem %s %d Location "%.1f %.1f %.1f"', $item['itemId'], (int) $item['amount'], (float) ($loc['x'] + 100.0), (float) $loc['y'], (float) ($loc['z'] + 30.0));
                } else {
                    $cmd = "SpawnItem {$item['itemId']} {$item['amount']} {$steamId}";
                }
                $r = $this->rcon->sendCommand($cmd);
                if ($r['success']) {
                    $succeeded[] = "{$item['itemId']}x{$item['amount']} (VIP)";
                }
            }
            if (($bonus['money'] ?? 0) > 0 || ($bonus['gold'] ?? 0) > 0 || ($bonus['fame'] ?? 0) > 0) {
                $d = $this->getListPlayerData($steamId);
                $norm = $d['money'] ?? 0;
                $gold = $d['gold'] ?? 0;
                $fame = $this->getPlayerFameFromWhois($steamId);
                if (($bonus['money'] ?? 0) > 0) {
                    $this->rcon->sendCommand('#SetCurrencyBalance Normal '.(int) round($norm + $bonus['money'])." {$steamId}");
                    $succeeded[] = "Деньги +{$bonus['money']} (VIP)";
                }
                if (($bonus['gold'] ?? 0) > 0) {
                    $this->rcon->sendCommand('#SetCurrencyBalance Gold '.(int) round($gold + $bonus['gold'])." {$steamId}");
                    $succeeded[] = "Золото +{$bonus['gold']} (VIP)";
                }
                if (($bonus['fame'] ?? 0) > 0) {
                    $this->rcon->sendCommand('#SetFamePoints '.(int) round($fame + $bonus['fame'])." {$steamId}");
                    $succeeded[] = "Слава +{$bonus['fame']} (VIP)";
                }
            }
        }

        $cooldowns[$cmdKey.'_'.$steamId] = time() * 1000;
        $this->settings->saveBotData(['packCooldowns' => $cooldowns]);
        $reply = $succeeded ? 'Получено: '.implode(', ', $succeeded) : 'Не удалось выдать предметы';
        $this->privateMsg($reply, $steamId);
    }

    // ===== Teleport handler =====

    private function handleTeleport(string $cmdKey, string $steamId, string $trimmed, array $parts): void
    {
        $locations = $this->settings->getBotData()['teleportLocations'] ?? [];
        if (empty($locations)) {
            $this->privateMsg('Телепортация недоступна', $steamId);

            return;
        }
        $idx = (int) ($parts[1] ?? 0);
        if ($idx < 1 || $idx > count($locations)) {
            $list = array_map(fn ($loc, $i) => ($i + 1).'. '.$loc['name'].' ($'.$loc['price'].')', $locations, array_keys($locations));
            $this->privateMsg('Список мест телепорта: '.implode(', ', $list), $steamId);
            $this->privateMsg('Для телепорта укажите команду !телепорт с нужным номером локации', $steamId);

            return;
        }
        $loc = $locations[$idx - 1];
        if (($loc['price'] ?? 0) > 0) {
            $d = $this->getListPlayerData($steamId);
            $balance = $d['money'] ?? 0;
            if ($balance < $loc['price']) {
                $this->privateMsg("Недостаточно средств: нужно \${$loc['price']}, у вас \$".round($balance), $steamId);

                return;
            }
        }
        $this->privateMsg("⏳ Не двигайтесь! Телепорт в {$loc['name']} через 15 секунд...", $steamId);
        sleep(15);
        if (($loc['price'] ?? 0) > 0) {
            $d2 = $this->getListPlayerData($steamId);
            $balance2 = $d2['money'] ?? 0;
            if ($balance2 < $loc['price']) {
                $this->privateMsg("Недостаточно средств: нужно \${$loc['price']}, у вас \$".round($balance2), $steamId);

                return;
            }
            $newBalance = (int) round($balance2 - $loc['price']);
            $this->rcon->sendCommand("#SetCurrencyBalance Normal {$newBalance} {$steamId}");
        }
        $cmd = sprintf('Teleport %d %d %d %s', (int) $loc['x'], (int) $loc['y'], (int) $loc['z'], $steamId);
        $r = $this->rcon->sendCommand($cmd);
        if ($r['success']) {
            $this->privateMsg("✅ Телепортация в {$loc['name']} выполнена", $steamId);
        } else {
            $this->privateMsg('❌ Ошибка телепортации', $steamId);
        }
    }

    // ===== VIP handler =====

    private function handleVip(string $cmdKey, string $steamId, string $playerName): void
    {
        $vip = $this->settings->getBotData()['vip'] ?? [];
        if (empty($vip['enabled'])) {
            $this->privateMsg('VIP система отключена', $steamId);

            return;
        }
        $p = null;
        foreach ($vip['players'] ?? [] as $x) {
            if ($x['steamId'] === $steamId) {
                $p = $x;
                break;
            }
        }
        if (! $p) {
            $this->privateMsg('У вас нет VIP статуса', $steamId);

            return;
        }
        if ((int) ($p['expiresAt'] ?? 0) > 0 && time() > (int) $p['expiresAt']) {
            $this->privateMsg('Срок VIP истёк', $steamId);

            return;
        }
        $msg = '✅ У вас есть VIP!';
        if ((int) ($p['expiresAt'] ?? 0) > 0) {
            $remaining = (int) ceil(((int) $p['expiresAt'] - time()) / 86400);
            $msg .= " Осталось: {$remaining} дн.";
        } else {
            $msg .= ' Бессрочно';
        }
        if (! empty($p['note'])) {
            $msg .= " ({$p['note']})";
        }
        $this->privateMsg($msg, $steamId);
    }

    // ===== SaveHome handlers =====

    private function handleSaveDom(string $cmdKey, string $steamId, string $trimmed, array $parts): void
    {
        $cfg = $this->settings->getBotData()['saveHome'] ?? [];
        if (empty($cfg['enabled'])) {
            $this->privateMsg('Сохранение дома отключено', $steamId);

            return;
        }
        $homes = $this->getHomeLocations();
        if (! isset($homes[$steamId])) {
            $homes[$steamId] = [];
        }
        $vip = $this->settings->getBotData()['vip'] ?? [];
        $maxLocs = $this->isVip($steamId, $vip) ? (int) ($cfg['vipMaxLocations'] ?? 3) : (int) ($cfg['maxLocations'] ?? 1);
        if (count($homes[$steamId]) >= $maxLocs) {
            $this->privateMsg("Достигнут лимит сохранений ({$maxLocs}). Используйте !дома для просмотра", $steamId);

            return;
        }
        $d = $this->getListPlayerData($steamId);
        if (! $d || ((int) $d['x'] === 0 && (int) $d['y'] === 0 && (int) $d['z'] === 0)) {
            $this->privateMsg('Не удалось определить вашу локацию', $steamId);

            return;
        }
        $homeName = count($parts) > 1 ? trim(substr($trimmed, strlen($parts[0]))) : 'Дом '.(count($homes[$steamId]) + 1);
        $homes[$steamId][] = ['name' => $homeName, 'x' => $d['x'], 'y' => $d['y'], 'z' => $d['z']];
        $this->settings->saveBotData(['homeLocations' => $homes]);
        $priceNote = ((int) ($cfg['teleportPrice'] ?? 0)) > 0 ? " | Стоимость телепорта: \${$cfg['teleportPrice']}" : '';
        $this->privateMsg(sprintf('✅ Локация сохранена: %s (X=%d Y=%d Z=%d)%s', $homeName, (int) $d['x'], (int) $d['y'], (int) $d['z'], $priceNote), $steamId);
    }

    private function handleHomes(string $cmdKey, string $steamId): void
    {
        $cfg = $this->settings->getBotData()['saveHome'] ?? [];
        if (empty($cfg['enabled'])) {
            $this->privateMsg('Сохранение дома отключено', $steamId);

            return;
        }
        $locs = $this->getHomeLocations()[$steamId] ?? [];
        if (empty($locs)) {
            $this->privateMsg('У вас нет сохранённых локаций. Используйте !сохранитьдом', $steamId);

            return;
        }
        $list = implode(', ', array_map(fn ($l, $i) => ($i + 1).'. '.$l['name'], $locs, array_keys($locs)));
        $priceNote = ((int) ($cfg['teleportPrice'] ?? 0)) > 0 ? " | Стоимость телепорта: \${$cfg['teleportPrice']}" : '';
        $this->privateMsg("Ваши сохранения: {$list}. Телепорт: !дом N{$priceNote}", $steamId);
    }

    private function handleDeleteDom(string $cmdKey, string $steamId, array $parts): void
    {
        $cfg = $this->settings->getBotData()['saveHome'] ?? [];
        if (empty($cfg['enabled'])) {
            $this->privateMsg('Сохранение дома отключено', $steamId);

            return;
        }
        $homes = $this->getHomeLocations();
        $locs = $homes[$steamId] ?? [];
        if (empty($locs)) {
            $this->privateMsg('У вас нет сохранённых локаций', $steamId);

            return;
        }
        $idx = (int) ($parts[1] ?? 0);
        if ($idx < 1 || $idx > count($locs)) {
            $list = implode(', ', array_map(fn ($l, $i) => ($i + 1).'. '.$l['name'], $locs, array_keys($locs)));
            $this->privateMsg("Укажите номер для удаления: {$list}", $steamId);

            return;
        }
        $removed = array_splice($locs, $idx - 1, 1);
        if (empty($locs)) {
            unset($homes[$steamId]);
        } else {
            $homes[$steamId] = $locs;
        }
        $this->settings->saveBotData(['homeLocations' => $homes]);
        $list = $locs ? ' Осталось: '.implode(', ', array_map(fn ($l, $i) => ($i + 1).'. '.$l['name'], $locs, array_keys($locs))) : '';
        $this->privateMsg("✅ Локация «{$removed[0]['name']}» удалена{$list}", $steamId);
    }

    private function handleHome(string $cmdKey, string $steamId, array $parts): void
    {
        $cfg = $this->settings->getBotData()['saveHome'] ?? [];
        if (empty($cfg['enabled'])) {
            $this->privateMsg('Сохранение дома отключено', $steamId);

            return;
        }
        $locs = $this->getHomeLocations()[$steamId] ?? [];
        if (empty($locs)) {
            $this->privateMsg('У вас нет сохранённых локаций. Используйте !сохранитьдом', $steamId);

            return;
        }
        $idx = (int) ($parts[1] ?? 0);
        $homeIdx = (! empty($parts[1]) && $idx >= 1 && $idx <= count($locs)) ? $idx - 1 : 0;
        $home = $locs[$homeIdx];
        $price = (int) ($cfg['teleportPrice'] ?? 0);
        if ($price > 0) {
            $this->privateMsg("💰 Стоимость телепорта: \${$price}", $steamId);
            $d1 = $this->getListPlayerData($steamId);
            $balance = $d1['money'] ?? 0;
            if ($balance < $price) {
                $this->privateMsg("Недостаточно средств: нужно \${$price}, у вас \$".round($balance), $steamId);

                return;
            }
            $this->privateMsg("⏳ Телепорт домой ({$home['name']}) через 15 секунд...", $steamId);
            sleep(15);
            $d2 = $this->getListPlayerData($steamId);
            $balance2 = $d2['money'] ?? 0;
            if ($balance2 < $price) {
                $this->privateMsg("Недостаточно средств: нужно \${$price}, у вас \$".round($balance2), $steamId);

                return;
            }
            $newBalance = (int) round($balance2 - $price);
            $this->rcon->sendCommand("#SetCurrencyBalance Normal {$newBalance} {$steamId}");
        } else {
            $this->privateMsg("⏳ Телепорт домой ({$home['name']})...", $steamId);
        }
        $cmd = sprintf('Teleport %d %d %d %s', (int) $home['x'], (int) $home['y'], (int) $home['z'], $steamId);
        $r = $this->rcon->sendCommand($cmd);
        if ($r['success']) {
            $this->privateMsg("✅ Телепортация домой ({$home['name']}) выполнена", $steamId);
        } else {
            $this->privateMsg('❌ Ошибка телепортации', $steamId);
        }
    }

    // ===== Generic ListPlayers-based commands =====

    private function runGenericCommand(array $cmd, string $cmdKey, string $steamId, string $playerName, string $trimmed): void
    {
        $fullCommand = $cmd['rconCommand'];
        if (! empty($cmd['requiresArgs']) && strlen($trimmed) > strlen($cmd['trigger'])) {
            $fullCommand .= ' '.trim(substr($trimmed, strlen($cmd['trigger'])));
        }
        try {
            $result = $this->rcon->sendCommand($fullCommand);
            if (! $result['success'] || empty($result['response'])) {
                return;
            }
            $lines = array_values(array_filter(explode("\n", $result['response']), fn (string $l): bool => trim($l) !== ''));
            $reply = '';
            if ($cmdKey === '!balance') {
                $reply = $this->parseBalance($lines, $steamId, $playerName);
            } elseif ($cmdKey === '!location') {
                $reply = $this->parseLocation($lines, $steamId, $playerName);
            } elseif ($cmdKey === '!online') {
                $reply = $this->parseOnline($lines);
            } else {
                $reply = mb_substr($result['response'], 0, 200);
            }
            if (mb_strlen($reply) > 300) {
                $reply = mb_substr($reply, 0, 300).'...';
            }
            if ($reply) {
                $this->privateMsg($reply, $steamId);
            }
        } catch (\Throwable $e) {
            Log::channel('scum')->error('[ChatCommand] generic command error: '.$e->getMessage());
        }
    }

    private function parseBalance(array $lines, string $steamId, string $playerName): string
    {
        foreach ($lines as $line) {
            if (str_contains($line, "steam={$steamId}")) {
                preg_match('/\bmoney=([\d.+-]+)/', $line, $mm);
                preg_match('/\bgold=([\d.+-]+)/', $line, $gm);
                $money = isset($mm[1]) ? (float) $mm[1] : null;
                $gold = isset($gm[1]) ? (float) $gm[1] : null;

                return sprintf('%s: Account balance: %s | Gold balance: %s', $playerName, $money !== null ? number_format($money, 0) : 'N/A', $gold !== null ? number_format($gold, 0) : 'N/A');
            }
        }
        $section = $this->extractPlayerSection($lines, $steamId, $playerName);
        $bal = '';
        $gold = '';
        foreach ($section as $l) {
            if (stripos($l, 'account balance') !== false) {
                $bal = $l;
            }
            if (stripos($l, 'gold balance') !== false) {
                $gold = $l;
            }
        }

        return $playerName.': '.($bal ?: 'N/A').' | '.($gold ?: 'N/A');
    }

    private function parseLocation(array $lines, string $steamId, string $playerName): string
    {
        foreach ($lines as $line) {
            if (str_contains($line, "steam={$steamId}")) {
                preg_match('/\(([\d.+-]+),\s*([\d.+-]+),\s*([\d.+-]+)\)/', $line, $pm);

                return $pm
                    ? sprintf('%s: Location: X=%d Y=%d Z=%d', $playerName, $pm[1], $pm[2], $pm[3])
                    : $playerName.': Location: N/A';
            }
        }
        $section = $this->extractPlayerSection($lines, $steamId, $playerName);
        $loc = 'N/A';
        foreach ($section as $l) {
            if (stripos($l, 'location') !== false) {
                $loc = $l;
                break;
            }
        }

        return $playerName.': '.$loc;
    }

    private function parseOnline(array $lines): string
    {
        $old = array_filter($lines, fn ($l) => preg_match('/^\d+\.\s+\S/', $l));
        $new = array_filter($lines, fn ($l) => preg_match('/^PLAYER\s*\|/i', $l));
        $count = max(count($old), count($new));
        if ($count === 0) {
            return 'Нет игроков онлайн';
        }
        $names = [];
        foreach ($new as $l) {
            if (preg_match('/^PLAYER\s*\|\s*(.+?)\s*\|/i', $l, $m)) {
                $names[] = trim($m[1]);
            }
        }
        $list = $names ? implode(', ', $names) : implode(', ', $old);

        return "Онлайн ({$count}): {$list}";
    }

    private function extractPlayerSection(array $lines, string $steamId, string $playerName): array
    {
        $idx = -1;
        foreach ($lines as $i => $l) {
            if (str_contains($l, $steamId) || str_contains($l, '. '.$playerName)) {
                $idx = $i;
                break;
            }
        }
        if ($idx === -1) {
            return $lines;
        }
        $section = [$lines[$idx]];
        for ($i = $idx + 1; $i < count($lines); $i++) {
            if (preg_match('/^\d+\.\s+\S/', $lines[$i])) {
                break;
            }
            $section[] = $lines[$i];
        }

        return $section;
    }

    public function getListPlayerData(string $steamId): ?array
    {
        $x = 0;
        $y = 0;
        $z = 0;
        $foundLoc = false;
        $rLoc = $this->rcon->sendCommand("Location {$steamId}");
        if ($rLoc['success'] && ! empty($rLoc['response'])) {
            if (preg_match('/X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $rLoc['response'], $pm)) {
                $x = (float) $pm[1];
                $y = (float) $pm[2];
                $z = (float) $pm[3];
                $foundLoc = true;
            }
        }

        $r = $this->rcon->sendCommand('ListPlayers');
        if (! $r['success'] || empty($r['response'])) {
            return $foundLoc ? ['money' => 0, 'gold' => 0, 'x' => $x, 'y' => $y, 'z' => $z] : null;
        }

        $lines = explode("\n", $r['response']);
        $matched = false;
        $money = 0;
        $gold = 0;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, $steamId)) {
                $matched = true;
                if (! $foundLoc) {
                    if (preg_match('/(?:Location:\s*)?X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $pm)) {
                        $x = (float) $pm[1];
                        $y = (float) $pm[2];
                        $z = (float) $pm[3];
                        $foundLoc = true;
                    } elseif (preg_match('/\(([\d.+-]+),\s*([\d.+-]+),\s*([\d.+-]+)\)/', $trimmed, $pm)) {
                        $x = (float) $pm[1];
                        $y = (float) $pm[2];
                        $z = (float) $pm[3];
                        $foundLoc = true;
                    }
                }
                if (preg_match('/Account balance:\s*([\d.+-]+)/i', $trimmed, $m1) || preg_match('/\bmoney=([\d.+-]+)/i', $trimmed, $m1)) {
                    $money = (float) $m1[1];
                }
                if (preg_match('/Gold balance:\s*([\d.+-]+)/i', $trimmed, $g1) || preg_match('/\bgold=([\d.+-]+)/i', $trimmed, $g1)) {
                    $gold = (float) $g1[1];
                }
            } elseif ($matched) {
                if (! $foundLoc) {
                    if (preg_match('/^(?:Location:\s*)?X=([\d.+-]+)\s+Y=([\d.+-]+)\s+Z=([\d.+-]+)/i', $trimmed, $pm)) {
                        $x = (float) $pm[1];
                        $y = (float) $pm[2];
                        $z = (float) $pm[3];
                        $foundLoc = true;
                    }
                }
                if (preg_match('/^Account balance:\s*([\d.+-]+)/i', $trimmed, $m1) || preg_match('/^money=([\d.+-]+)/i', $trimmed, $m1)) {
                    $money = (float) $m1[1];
                }
                if (preg_match('/^Gold balance:\s*([\d.+-]+)/i', $trimmed, $g1) || preg_match('/^gold=([\d.+-]+)/i', $trimmed, $g1)) {
                    $gold = (float) $g1[1];
                }
                if (preg_match('/^\s*\d+\.\s+/i', $trimmed)) {
                    break;
                }
            }
        }

        if ($matched || $foundLoc) {
            return [
                'money' => $money,
                'gold' => $gold,
                'x' => $x,
                'y' => $y,
                'z' => $z,
            ];
        }

        return null;
    }

    public function getPlayerFameFromWhois(string $steamId): float
    {
        $r = $this->rcon->sendCommand("Whois {$steamId}");
        if (! $r['success'] || empty($r['response'])) {
            return 0;
        }
        if (preg_match('/\bfame[:\s]\s*([\d.]+)/i', $r['response'], $fm)) {
            return (float) $fm[1];
        }

        return 0;
    }

    private function handleInsurance(string $cmdKey, string $steamId, string $playerName, string $trimmed, array $parts): void
    {
        $insurance = app(InsuranceService::class);
        $sub = $parts[1] ?? 'инфо';

        if (in_array($sub, ['привязать', 'bind', 'save', 'привязка'])) {
            $res = $insurance->bindVehicle($steamId, $playerName);
            $this->privateMsg($res['message'], $steamId);

            return;
        }

        if (in_array($sub, ['вернуть', 'claim', 'возврат', 'восстановить', 'спавн', 'spawn'])) {
            $res = $insurance->claimVehicle($steamId, $playerName);
            $this->privateMsg($res['message'], $steamId);

            return;
        }

        if (in_array($sub, ['отвязать', 'unbind', 'удалить', 'del', 'clear'])) {
            $res = $insurance->unbindVehicle($steamId);
            $this->privateMsg($res['message'], $steamId);

            return;
        }

        // Инфо о страховке по умолчанию
        $policy = $insurance->getPlayerPolicy($steamId);
        if (! $policy) {
            $this->privateMsg('У вас нет активной страховки. Купите страховку в магазине Wargm!', $steamId);

            return;
        }

        $rem = max(0, (int) $policy['claims_total'] - (int) $policy['claims_used']);
        $statusLabels = [
            'pending' => 'Ожидает привязки авто',
            'active' => 'Действует',
            'destroyed' => 'Транспорт уничтожен (доступен возврат)',
            'claimed' => 'Использована',
            'expired' => 'Истёк срок',
        ];
        $st = $statusLabels[$policy['status']] ?? $policy['status'];
        $veh = ! empty($policy['vehicle_asset']) ? str_replace(['BP_', '_ES', '_C'], '', $policy['vehicle_asset']) : 'Не привязан';
        if (! empty($policy['vehicle_id'])) {
            $veh .= " (#{$policy['vehicle_id']})";
        }

        $msg = "Страховка #{$policy['id']}: [{$st}]. Авто: {$veh}. Осталось возвратов: {$rem}/{$policy['claims_total']}. Команды: !страховка привязать, !страховка вернуть, !страховка отвязать";
        $this->privateMsg($msg, $steamId);
    }
}
