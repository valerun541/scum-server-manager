<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Services\Scum\ChatCommandProcessor;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\ScumDbService;
use App\Services\Scum\WargmService;
use App\Support\ScumSkills;

class PlayerActionService
{
    private const array SKILL_RCON_NAMES = ScumSkills::RCON_NAMES;

    public function __construct(
        private readonly PluginService $plugins,
        private readonly RconService $rcon,
        private readonly ChatCommandProcessor $chat,
        private readonly WargmService $wargm,
        private readonly ScumDbService $scumDb,
    ) {
        $local = function_exists('storage_path') ? storage_path('app/scum/SCUM.db') : null;
        if ($local && file_exists($local) && ! $this->scumDb->isAvailable()) {
            $this->scumDb->setDbPath($local);
        }
    }

    /**
     * Выполняет действие над игроком и возвращает стандартизированный результат.
     * Полная поддержка всех 11 секций официального протокола SCUM-RCON.
     *
     * @param  string  $action  Кодовое имя действия
     * @param  string  $steamId  SteamID64 игрока
     * @param  array<string, mixed>  $payload  Входные параметры
     * @return array{success: bool, message: string, extra?: array<string, mixed>}
     */
    public function execute(string $action, string $steamId, array $payload): array
    {
        if (empty($steamId)) {
            return ['success' => false, 'message' => 'Не указан SteamID', 'extra' => []];
        }

        return match ($action) {
            // Экономика и слава
            'give_money', 'give_gold', 'give_fame' => $this->handleGiveCurrency($action, $steamId, $payload),
            'set_money', 'set_gold', 'set_fame' => $this->handleSetCurrency($action, $steamId, $payload),

            // Спавн предметов и техники
            'spawn_item' => $this->handleSpawnItem($steamId, $payload),
            'chest_full' => $this->handleChestFull($steamId, $payload),
            'spawn_vehicle' => $this->handleSpawnVehicle($steamId, $payload),
            'spawn_npc' => $this->handleSpawnNpc($steamId, $payload),
            'bring_vehicle' => $this->handleBringVehicle($steamId, $payload),
            'destroy_all_vehicles' => $this->rawCommand('DestroyAllVehicles'),

            // Улучшение баз (Base Upgrade Suite)
            'upgrade_base' => $this->handleUpgradeBase($steamId, $payload),
            'upgrade_base_radius' => $this->handleUpgradeBaseRadius($steamId, $payload),
            'upgrade_base_status' => $this->rawCommand('UpgradeBaseStatus'),
            'upgrade_base_stop', 'cancel_upgrade_base' => $this->rawCommand('UpgradeBaseStop'),

            // Телепортация и спасение
            'unstuck' => $this->rawCommand("Unstuck {$steamId}"),
            'teleport_to' => $this->handleTeleportTo($steamId, $payload),
            'teleport_to_player' => $this->handleTeleportToPlayer($steamId, $payload),
            'teleport_to_me' => ['success' => false, 'message' => 'Телепорт «ко мне» недоступен удалённо — используйте «Телепорт по координатам»', 'extra' => []],
            'teleport_to_vehicle' => $this->handleTeleportToVehicle($steamId, $payload),

            // Модерация и управление состоянием
            'kick' => $this->handleKick($steamId, $payload),
            'ban' => $this->handleBan($steamId, $payload),
            'unban' => $this->rawCommand("Unban {$steamId}"),
            'silence' => $this->handleSilence($steamId, $payload),
            'unsilence' => $this->rawCommand("Unsilence {$steamId}"),
            'god_mode' => $this->toggleCommand($steamId, 'SetGodMode', $this->boolValue($payload, 'enabled', true)),
            'immortality' => $this->toggleCommand($steamId, 'SetImmortality', $this->boolValue($payload, 'enabled', true)),
            'knockout' => $this->handleKnockout($steamId, $payload),
            'suicide' => $this->rawCommand("Suicide {$steamId}"),

            // Квесты (Quest Softlock Toolkit)
            'delete_active_quests' => $this->rawCommand("DeleteActiveQuestsForUser {$steamId}"),
            'find_quest_lockouts' => $this->rawCommand('FindQuestLockouts'),
            'run_quest_unstick' => $this->rawCommand('RunQuestUnstick'),

            // Роботы и зомби
            'list_sentries' => $this->rawCommand('ListSentries'),
            'destroy_sentries_radius' => $this->handleDestroySentriesRadius($payload),
            'destroy_zombies_radius' => $this->handleDestroyZombiesRadius($payload),
            'suppress_sentry_respawn' => $this->handleSuppressSentryRespawn($payload),
            'set_sector_scan' => $this->toggleCommand($steamId, 'SetSectorScanEnabled', $this->boolValue($payload, 'enabled', true)),

            // Сервер и окружение
            'set_time' => $this->handleSetTime($payload),
            'set_weather' => $this->handleSetWeather($payload),
            'restart_server' => $this->handleRestartServer($payload),
            'shutdown_server' => $this->handleShutdownServer($payload),
            'list_commands' => $this->rawCommand('ListCommands'),

            // Чат, оповещения и досье
            'whois', 'player_info', 'dossier' => $this->rawCommand("Whois {$steamId}"),
            'show_nameplates' => $this->rawCommand("#ShowNamePlates true {$steamId}"),
            'show_player_info' => $this->rawCommand("#ShowOtherPlayerInfo true {$steamId}"),
            'announce' => $this->handleAnnounce($payload),
            'notify' => $this->handleNotify($steamId, $payload),
            'chat' => $this->handleChat($steamId, $payload),

            // Специальные плагины
            'starter_pack' => $this->handleStarterPack($steamId),
            'wargm_check' => $this->handleWargmCheck($steamId),
            'set_all_skills' => $this->handleSetAllSkills($steamId),
            'airdrop_at' => $this->handleAirdropAt($steamId),
            'cargo_drop' => $this->handleCargoDrop($steamId),

            default => ['success' => false, 'message' => 'Неизвестное действие', 'extra' => []],
        };
    }

    private function handleGiveCurrency(string $action, string $steamId, array $payload): array
    {
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Укажите сумму больше 0', 'extra' => []];
        }

        $delta = (int) round($amount);
        if ($action === 'give_money') {
            $cmd = "ChangeCurrencyBalance Cash +{$delta} {$steamId}";
            $label = 'денег';
        } elseif ($action === 'give_gold') {
            $cmd = "ChangeCurrencyBalance Gold +{$delta} {$steamId}";
            $label = 'золота';
        } else {
            $cmd = "ChangeFamePoints +{$delta} {$steamId}";
            $label = 'славы';
        }

        $result = $this->rcon->sendCommand($cmd);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $ok = ! empty($result['success']) && ! $serverError;

        return [
            'success' => $ok,
            'message' => $ok ? "Выдано {$delta} {$label}" : ($serverError ?: ($result['error'] ?? 'Ошибка')),
            'extra' => ['command' => $cmd, 'response' => $resp],
        ];
    }

    private function handleSetCurrency(string $action, string $steamId, array $payload): array
    {
        $amount = (int) round((float) ($payload['amount'] ?? 0));
        if ($amount < 0) {
            return ['success' => false, 'message' => 'Сумма не может быть отрицательной', 'extra' => []];
        }

        if ($action === 'set_money') {
            $cmd = "SetCurrencyBalance Cash {$amount} {$steamId}";
            $label = 'денег';
        } elseif ($action === 'set_gold') {
            $cmd = "SetCurrencyBalance Gold {$amount} {$steamId}";
            $label = 'золота';
        } else {
            $cmd = "SetFamePoints {$amount} {$steamId}";
            $label = 'славы';
        }

        $result = $this->rcon->sendCommand($cmd);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $ok = ! empty($result['success']) && ! $serverError;

        return [
            'success' => $ok,
            'message' => $ok ? "Установлен баланс {$amount} {$label}" : ($serverError ?: ($result['error'] ?? 'Ошибка')),
            'extra' => ['command' => $cmd, 'response' => $resp],
        ];
    }

    private function handleUpgradeBase(string $steamId, array $payload): array
    {
        $delay = max(50, (int) ($payload['delay'] ?? $payload['delay_ms'] ?? 300));

        return $this->rawCommand("UpgradeBase {$steamId} {$delay}");
    }

    private function handleUpgradeBaseRadius(string $steamId, array $payload): array
    {
        $radius = max(1, (int) ($payload['radius'] ?? 30));
        $delay = max(50, (int) ($payload['delay'] ?? $payload['delay_ms'] ?? 300));

        return $this->rawCommand("UpgradeBaseRadius {$radius} {$steamId} {$delay}");
    }

    private function handleBringVehicle(string $steamId, array $payload): array
    {
        $vehicleId = trim((string) ($payload['vehicle_id'] ?? $payload['vehicleId'] ?? $payload['id'] ?? ''));
        if ($vehicleId === '') {
            return ['success' => false, 'message' => 'Не указан ID транспорта', 'extra' => []];
        }

        return $this->rawCommand("BringVehicle {$vehicleId} {$steamId}");
    }

    private function handleSpawnItem(string $steamId, array $payload): array
    {
        $item = trim((string) ($payload['item'] ?? ''));
        $amount = (int) ($payload['amount'] ?? 1);
        if (! $item || $amount <= 0) {
            return ['success' => false, 'message' => 'Укажите предмет и количество', 'extra' => []];
        }

        $loc = $this->plugins->rconListPlayer($steamId);
        if ($loc && ! empty($loc['x'])) {
            $cmd = sprintf('SpawnItem %s %d Location "%.1f %.1f %.1f"', $item, $amount, (float) ($loc['x'] + 100.0), (float) $loc['y'], (float) ($loc['z'] + 30.0));
        } else {
            $cmd = "SpawnItem {$item} {$amount} {$steamId}";
        }

        $result = $this->rcon->sendCommand($cmd);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $ok = ! empty($result['success']) && ! $serverError;

        return [
            'success' => $ok,
            'message' => $ok ? "Предмет {$item}x{$amount} выдан" : ($serverError ?: ($result['error'] ?? 'Ошибка')),
            'extra' => ['command' => $cmd],
        ];
    }

    private function handleChestFull(string $steamId, array $payload): array
    {
        $chestItem = trim((string) ($payload['chest_item'] ?? 'BP_MetalChest'));
        $item = trim((string) ($payload['item'] ?? ''));
        $count = (int) ($payload['count'] ?? 0);

        if (! $item) {
            return ['success' => false, 'message' => 'Ящик: не указан предмет', 'extra' => []];
        }
        if ($count <= 0) {
            return ['success' => false, 'message' => 'Ящик: неверное количество', 'extra' => []];
        }

        $cmd = "SpawnInventoryFullOf {$chestItem} {$count} {$item} {$steamId}";
        $result = $this->rcon->sendCommand($cmd);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $ok = ! empty($result['success']) && ! $serverError;

        return [
            'success' => $ok,
            'message' => $ok ? "Ящик {$chestItem} с {$item} x{$count}" : ($serverError ?: ($result['error'] ?? 'Ошибка RCON')),
            'extra' => ['command' => $cmd],
        ];
    }

    private function handleSpawnVehicle(string $steamId, array $payload): array
    {
        $vehicle = trim((string) ($payload['vehicle'] ?? ''));
        if (! $vehicle) {
            return ['success' => false, 'message' => 'Укажите название ТС', 'extra' => []];
        }

        $loc = $this->plugins->rconListPlayer($steamId);
        if ($loc && ! empty($loc['x'])) {
            $cmd = sprintf('SpawnVehicle %s Location "%.1f %.1f %.1f"', $vehicle, (float) ($loc['x'] + 300.0), (float) $loc['y'], (float) ($loc['z'] + 40.0));
        } else {
            $cmd = "SpawnVehicle {$vehicle} {$steamId}";
        }

        return $this->rawCommand($cmd);
    }

    private function handleTeleportToPlayer(string $steamId, array $payload): array
    {
        $target = trim((string) ($payload['target'] ?? $payload['target_steam_id'] ?? ''));
        if ($target === '') {
            return ['success' => false, 'message' => 'Укажите целевого игрока (SteamID)', 'extra' => []];
        }

        return $this->rawCommand("TeleportTo {$steamId} {$target}");
    }

    private function handleKick(string $steamId, array $payload): array
    {
        $reason = trim((string) ($payload['reason'] ?? 'Исключен администратором'));

        return $this->rawCommand("Kick {$steamId} \"".str_replace('"', "'", $reason).'"');
    }

    private function handleBan(string $steamId, array $payload): array
    {
        $reason = trim((string) ($payload['reason'] ?? 'Заблокирован администратором'));

        return $this->rawCommand("Ban {$steamId} \"".str_replace('"', "'", $reason).'"');
    }

    private function handleSilence(string $steamId, array $payload): array
    {
        $duration = (int) ($payload['duration'] ?? $payload['minutes'] ?? 0);
        $cmd = $duration > 0 ? "Silence {$steamId} {$duration}" : "Silence {$steamId}";

        return $this->rawCommand($cmd);
    }

    private function handleDestroySentriesRadius(array $payload): array
    {
        $radius = max(100, (int) ($payload['radius'] ?? 5000));
        $x = (float) ($payload['x'] ?? 0);
        $y = (float) ($payload['y'] ?? 0);
        $z = (float) ($payload['z'] ?? 0);

        return $this->rawCommand("DestroySentriesWithinRadius {$radius} {$x} {$y} {$z}");
    }

    private function handleDestroyZombiesRadius(array $payload): array
    {
        $radius = max(100, (int) ($payload['radius'] ?? 10000));

        return $this->rawCommand("DestroyZombiesWithinRadius {$radius}");
    }

    private function handleSuppressSentryRespawn(array $payload): array
    {
        $state = $this->boolValue($payload, 'state', true) ? 'on' : 'off';

        return $this->rawCommand("SuppressSentryRespawn {$state}");
    }

    private function handleSetTime(array $payload): array
    {
        $hour = max(0, min(23, (int) ($payload['hour'] ?? 12)));
        $minute = isset($payload['minute']) ? sprintf('%02d', max(0, min(59, (int) $payload['minute']))) : '00';

        return $this->rawCommand("SetTime {$hour} {$minute}");
    }

    private function handleSetWeather(array $payload): array
    {
        $weather = max(0.0, min(1.0, (float) ($payload['weather'] ?? $payload['value'] ?? 0.0)));

        return $this->rawCommand("SetWeather {$weather}");
    }

    private function handleRestartServer(array $payload): array
    {
        $seconds = max(5, (int) ($payload['seconds'] ?? 60));

        return $this->rawCommand("RestartServer {$seconds}");
    }

    private function handleShutdownServer(array $payload): array
    {
        $seconds = max(5, (int) ($payload['seconds'] ?? 60));

        return $this->rawCommand("ShutdownServer {$seconds}");
    }

    private function handleStarterPack(string $steamId): array
    {
        $this->chat->resetPlayerCooldown($steamId);
        $this->chat->process($steamId, 'Admin', '!startpack');

        return ['success' => true, 'message' => 'Стартовый набор выдан', 'extra' => []];
    }

    private function handleWargmCheck(string $steamId): array
    {
        $result = $this->wargm->processPlayer($steamId);
        $msg = $result['ok'] ? 'Выдано: '.implode('; ', $result['results']) : implode('; ', $result['results']);
        $success = ! empty($result['ok']) || $this->serverError($msg) === '';

        return ['success' => $success, 'message' => $msg, 'extra' => []];
    }

    private function handleKnockout(string $steamId, array $payload): array
    {
        $seconds = max(1, (int) ($payload['seconds'] ?? 10));

        return $this->rawCommand("Knockout {$seconds} {$steamId}");
    }

    private function handleAnnounce(array $payload): array
    {
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return ['success' => false, 'message' => 'Введите текст объявления', 'extra' => []];
        }

        return $this->rawCommand('#Announce '.str_replace('"', "'", $message));
    }

    private function handleNotify(string $steamId, array $payload): array
    {
        $message = trim((string) ($payload['message'] ?? ''));
        $type = (int) ($payload['type'] ?? 1);
        if ($message === '') {
            return ['success' => false, 'message' => 'Введите текст уведомления', 'extra' => []];
        }

        return $this->rawCommand("#SendNotification {$type} 0 \"".str_replace('"', "'", $message)."\" {$steamId}");
    }

    private function handleChat(string $steamId, array $payload): array
    {
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return ['success' => false, 'message' => 'Введите текст сообщения', 'extra' => []];
        }

        $colorMap = ['White' => '0', 'Red' => '7', 'Green' => '3', 'Blue' => '2', 'Yellow' => '4', 'Orange' => '6'];
        $type = $colorMap[(string) ($payload['color'] ?? 'Yellow')] ?? '4';

        return $this->rawCommand("SendChat {$type} \"".str_replace('"', "'", $message)."\" {$steamId}");
    }

    private function handleTeleportTo(string $steamId, array $payload): array
    {
        $x = (float) ($payload['x'] ?? 0);
        $y = (float) ($payload['y'] ?? 0);
        $z = (float) ($payload['z'] ?? 0);

        if ($x === 0.0 && $y === 0.0 && $z === 0.0) {
            return ['success' => false, 'message' => 'Укажите координаты', 'extra' => []];
        }

        return $this->rawCommand("Teleport {$x} {$y} {$z} {$steamId}");
    }

    private function handleTeleportToVehicle(string $steamId, array $payload): array
    {
        $x = (float) ($payload['x'] ?? 0);
        $y = (float) ($payload['y'] ?? 0);
        $z = (float) ($payload['z'] ?? 0);

        if ($x === 0.0 && $y === 0.0 && $z === 0.0) {
            return ['success' => false, 'message' => 'Выберите транспорт для телепорта', 'extra' => []];
        }

        return $this->rawCommand("Teleport {$x} {$y} {$z} {$steamId}");
    }

    private function handleSetAllSkills(string $steamId): array
    {
        $errors = 0;
        $ok = 0;
        foreach (self::SKILL_RCON_NAMES as $name) {
            $r = $this->rcon->sendCommand("SetSkillLevel {$name} 4 {$steamId}");
            if (! empty($r['success'])) {
                $ok++;
            } else {
                $errors++;
            }
        }

        if ($this->scumDb->isAvailable()) {
            foreach (self::SKILL_RCON_NAMES as $name) {
                $this->scumDb->updateSkill($steamId, $name, 4);
            }
        }

        return [
            'success' => $errors === 0,
            'message' => "Все навыки: {$ok} установлено на MAX (4)".($errors ? ", {$errors} не применилось" : ''),
            'extra' => [],
        ];
    }

    private function handleAirdropAt(string $steamId): array
    {
        $loc = $this->plugins->rconListPlayer($steamId);
        if (! $loc) {
            return ['success' => false, 'message' => 'Игрок офлайн — координаты неизвестны', 'extra' => []];
        }

        $result = $this->plugins->dropAt($loc['x'], $loc['y'], $loc['z']);

        return [
            'success' => ! empty($result['ok']),
            'message' => $result['error'] ?? 'Аирдроп сброшен',
            'extra' => [
                'command' => $result['command'] ?? null,
                'coords' => $result['coords'] ?? null,
            ],
        ];
    }

    private function handleCargoDrop(string $steamId): array
    {
        $loc = $this->plugins->rconListPlayer($steamId);
        if (! $loc) {
            return ['success' => false, 'message' => 'Игрок офлайн — координаты неизвестны', 'extra' => []];
        }

        $cmd = sprintf('ScheduleWorldEvent BP_CargoDropEvent %s %s %s', $loc['x'], $loc['y'], $loc['z']);
        $result = $this->rcon->sendCommand($cmd);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $ok = ! empty($result['success']) && ! $serverError;

        return [
            'success' => $ok,
            'message' => $ok ? 'Дроп в '.$loc['x'].','.$loc['y'].','.$loc['z'] : ($serverError ?: ($result['error'] ?? 'Ошибка RCON')),
            'extra' => [
                'command' => $cmd,
                'coords' => $loc,
            ],
        ];
    }

    private function toggleCommand(string $steamId, string $cmdName, bool $enabled): array
    {
        return $this->rawCommand("{$cmdName} ".($enabled ? 'true' : 'false')." {$steamId}");
    }

    private function rawCommand(string $command): array
    {
        $result = $this->rcon->sendCommand($command);
        $resp = (string) ($result['response'] ?? '');
        $serverError = $this->serverError($resp);
        $success = ! empty($result['success']) && ! $serverError;
        $msg = $success
            ? "Команда отправлена: {$command}"
            : ($serverError ?: ($result['error'] ?? 'Ошибка RCON'));

        return [
            'success' => $success,
            'message' => $msg,
            'extra' => ['command' => $command, 'response' => $resp],
        ];
    }

    private function serverError(string $response): string
    {
        $r = trim($response);
        if ($r === '' || $r === 'Command executed (no response)') {
            return '';
        }
        if (preg_match('/^(error|failed|unknown command|unknown player|player not)[:\s]/i', $r)) {
            return $r;
        }
        if (preg_match('/is not allowed|not allowed to be|invalid (item|command)|not found/i', $r)) {
            return $r;
        }

        return '';
    }

    private function boolValue(array $payload, string $key, bool $default = false): bool
    {
        $v = $payload[$key] ?? $default;

        return $v === true || $v === 'true' || $v === '1' || $v === 1;
    }

    private function handleSpawnNpc(string $steamId, array $payload): array
    {
        $npc = trim((string) ($payload['npc'] ?? $payload['item'] ?? ''));
        if ($npc === '') {
            return ['success' => false, 'message' => 'Выберите NPC или животное', 'extra' => []];
        }

        $amount = max(1, (int) ($payload['amount'] ?? $payload['count'] ?? 1));
        $cmd = str_starts_with($npc, 'BP_Guard') || str_starts_with($npc, 'BP_Drifter')
            ? "#SpawnArmedNPC {$npc} {$amount} Location \"{$steamId}\""
            : "#spawnanimal {$npc} {$amount} Location \"{$steamId}\"";

        return $this->rawCommand($cmd);
    }
}
