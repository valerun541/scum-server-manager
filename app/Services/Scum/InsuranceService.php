<?php

namespace App\Services\Scum;

use App\Support\VehicleParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsuranceService
{
    public function __construct(
        private RconService $rcon,
        private SettingsService $settings,
    ) {}

    public function getSettings(): array
    {
        $appConfig = $this->settings->getAppConfig();
        $defaults = [
            'enabled' => true,
            'cooldown_minutes' => 60,
            'claims_per_policy' => 1,
            'duration_days' => 30,
            'destroy_old_if_alive' => true,
        ];

        $cfg = array_merge($defaults, $appConfig['plugins']['insurance'] ?? []);
        unset($cfg['bonus_items']);

        return $cfg;
    }

    public function saveSettings(array $data): void
    {
        $appConfig = $this->settings->getAppConfig();

        $current = $appConfig['plugins']['insurance'] ?? [];
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : (bool) ($current['enabled'] ?? true);

        $appConfig['plugins']['insurance'] = [
            'enabled' => $enabled,
            'cooldown_minutes' => max(0, (int) ($data['cooldown_minutes'] ?? 60)),
            'claims_per_policy' => max(1, (int) ($data['claims_per_policy'] ?? 1)),
            'duration_days' => max(1, (int) ($data['duration_days'] ?? 30)),
            'destroy_old_if_alive' => (bool) ($data['destroy_old_if_alive'] ?? true),
        ];

        $this->settings->saveAppConfig($appConfig);
    }

    /**
     * Выдать страховку игроку (после покупки на Wargm или вручную админом).
     */
    public function grantInsurance(
        string $steamId,
        ?string $playerName = null,
        ?string $purchaseId = null,
        int $claims = 1,
        ?int $durationDays = null
    ): array {
        $cfg = $this->getSettings();
        if (! $cfg['enabled']) {
            return ['success' => false, 'message' => 'Плагин страхования отключён в настройках'];
        }

        $days = $durationDays ?? (int) $cfg['duration_days'];
        $claimsCount = max(1, $claims);
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        $id = DB::table('vehicle_insurances')->insertGetId([
            'steam_id' => $steamId,
            'player_name' => $playerName,
            'purchase_id' => $purchaseId,
            'vehicle_id' => null,
            'vehicle_asset' => null,
            'vehicle_alias' => null,
            'status' => 'pending',
            'claims_total' => $claimsCount,
            'claims_used' => 0,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'insurance_id' => $id,
            'message' => "Страховой полис #{$id} успешно оформлен! Сядьте в свой транспорт и введите: !страховка привязать",
        ];
    }

    /**
     * Получить активную или ожидающую страховку игрока.
     */
    public function getPlayerPolicy(string $steamId): ?array
    {
        $row = DB::table('vehicle_insurances')
            ->where('steam_id', $steamId)
            ->whereIn('status', ['pending', 'active', 'destroyed'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereRaw('claims_used < claims_total')
            ->orderByDesc('id')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Привязать транспорт к страховому полису игрока.
     */
    public function bindVehicle(string $steamId, ?string $playerName = null): array
    {
        $cfg = $this->getSettings();
        if (! $cfg['enabled']) {
            return ['success' => false, 'message' => 'Плагин страхования отключён'];
        }

        $policy = $this->getPlayerPolicy($steamId);
        if (! $policy) {
            return [
                'success' => false,
                'message' => 'У вас нет активного страхового полиса. Приобретите страховку в магазине Wargm!',
            ];
        }

        $playerInfo = $this->getPlayerInfo($steamId);
        if (! $playerInfo || $playerInfo['x'] === null) {
            return [
                'success' => false,
                'message' => 'Не удалось определить ваше местоположение. Убедитесь, что вы онлайн на сервере.',
            ];
        }

        // Получаем весь транспорт через RCON ListSpawnedVehicles
        $vehicles = $this->fetchSpawnedVehicles();
        if (empty($vehicles)) {
            usleep(250000);
            $vehicles = $this->fetchSpawnedVehicles();
        }

        if (empty($vehicles)) {
            return [
                'success' => false,
                'message' => 'Не удалось получить список транспорта с сервера. Попробуйте через 10 секунд.',
            ];
        }

        $pName = mb_strtolower(trim((string) ($playerInfo['name'] ?? ($playerName ?? ($policy['player_name'] ?? '')))));
        $pUpid = $playerInfo['upid'] !== null ? (int) $playerInfo['upid'] : null;

        $targetVehicle = null;
        $bestScore = -1.0;

        foreach ($vehicles as $v) {
            if ($v['x'] === null || $v['y'] === null || $v['z'] === null) {
                continue;
            }

            $dx = (float) $v['x'] - (float) $playerInfo['x'];
            $dy = (float) $v['y'] - (float) $playerInfo['y'];
            $dz = (float) $v['z'] - (float) $playerInfo['z'];

            $dist2d = sqrt($dx * $dx + $dy * $dy);
            $dist3d = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            $absDz = abs($dz);

            // Проверяем, зарегистрирован ли транспорт на данного игрока
            $isOwner = false;
            if ($pUpid !== null && $v['ownerDbId'] !== null && (int) $v['ownerDbId'] === $pUpid) {
                $isOwner = true;
            } elseif ($pName !== '' && ! empty($v['ownerName']) && mb_strtolower($v['ownerName']) === $pName) {
                $isOwner = true;
            } elseif ($pName !== '' && ! empty($v['customName']) && $v['customName'] !== '-' && mb_strtolower($v['customName']) === $pName) {
                $isOwner = true;
            }

            // 1. Приоритет владельца (транспорт игрока в радиусе до 100 метров)
            if ($isOwner && $dist2d <= 10000.0 && $absDz <= 4000.0) {
                $score = 100000.0 - $dist3d;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $targetVehicle = $v;
                }

                continue;
            }

            // 2. Игрок сидит в транспорте или находится рядом (радиус 35 метров по горизонтали, до 20 м по высоте)
            if (($dist2d <= 3500.0 && $absDz <= 2000.0) || $dist3d <= 3500.0) {
                $score = 50000.0 - $dist3d;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $targetVehicle = $v;
                }
            }
        }

        if (! $targetVehicle) {
            return [
                'success' => false,
                'message' => 'Рядом с вами не обнаружен транспорт. Пожалуйста, сядьте в автомобиль или подойдите к нему вплотную.',
            ];
        }

        $assetClean = str_replace(['BP_', '_ES', '_C'], '', $targetVehicle['asset'] ?? 'Транспорт');

        DB::table('vehicle_insurances')->where('id', $policy['id'])->update([
            'vehicle_id' => $targetVehicle['entityId'],
            'vehicle_asset' => $targetVehicle['asset'],
            'vehicle_alias' => $targetVehicle['customName'] ?: null,
            'player_name' => $playerInfo['name'] ?: ($playerName ?: ($policy['player_name'] ?? null)),
            'status' => 'active',
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Транспорт {$assetClean} (#{$targetVehicle['entityId']}) успешно застрахован! В случае уничтожения введите !страховка вернуть",
            'vehicle' => $targetVehicle,
        ];
    }

    /**
     * Заявить страховой случай и получить возврат транспорта.
     */
    public function claimVehicle(string $steamId, ?string $playerName = null, bool $isAdmin = false): array
    {
        $cfg = $this->getSettings();
        if (! $cfg['enabled'] && ! $isAdmin) {
            return ['success' => false, 'message' => 'Плагин страхования отключён'];
        }

        $policy = $this->getPlayerPolicy($steamId);
        if (! $policy) {
            return [
                'success' => false,
                'message' => 'У вас нет активной страховки для выплаты.',
            ];
        }

        if (empty($policy['vehicle_asset'])) {
            return [
                'success' => false,
                'message' => 'Вы ещё не привязали автомобиль к страховке. Сядьте в свой транспорт и напишите: !страховка привязать',
            ];
        }

        // Проверка кулдауна
        $cooldownMinutes = (int) ($cfg['cooldown_minutes'] ?? 60);
        if (! $isAdmin && ! empty($policy['last_claim_at']) && $cooldownMinutes > 0) {
            $lastClaim = Carbon::parse($policy['last_claim_at']);
            $diffMinutes = $lastClaim->diffInMinutes(now());
            if ($diffMinutes < $cooldownMinutes) {
                $rem = $cooldownMinutes - $diffMinutes;

                return [
                    'success' => false,
                    'message' => "Кулдаун на получение страховки! Повторный возврат доступен через {$rem} мин.",
                ];
            }
        }

        // Игрок должен быть онлайн
        $playerLoc = $this->getPlayerLocation($steamId);
        if (! $playerLoc) {
            return [
                'success' => false,
                'message' => 'Вы должны находиться онлайн на сервере для получения транспорта!',
            ];
        }

        // Защита от дюпа: если старый транспорт ещё существует на карте, удаляем его
        if (! empty($policy['vehicle_id']) && ($cfg['destroy_old_if_alive'] ?? true)) {
            $vehicles = $this->fetchSpawnedVehicles();
            foreach ($vehicles as $v) {
                if ((int) ($v['entityId'] ?? 0) === (int) $policy['vehicle_id']) {
                    $this->rcon->sendCommand("DestroyVehicle {$policy['vehicle_id']}");
                    Log::channel('scum')->info("[Insurance] Despawned old vehicle #{$policy['vehicle_id']} before claim for {$steamId}");
                    break;
                }
            }
        }

        // Безопасный спавн нового авто со смещением ~3 метра вперёд и 40 см по высоте (без сдвига вправо)
        $spawnPos = [
            'x' => round($playerLoc['x'] + 300.0, 1),
            'y' => round($playerLoc['y'], 1),
            'z' => round($playerLoc['z'] + 40.0, 1),
        ];

        $spawnCmd = sprintf('SpawnVehicle %s Location "%.1f %.1f %.1f"', $policy['vehicle_asset'], $spawnPos['x'], $spawnPos['y'], $spawnPos['z']);
        $r = $this->rcon->sendCommand($spawnCmd);

        if (! $r['success']) {
            // Fallback если координаты не принялись
            $fallbackCmd = "SpawnVehicle {$policy['vehicle_asset']} {$steamId}";
            $r = $this->rcon->sendCommand($fallbackCmd);
        }

        if (! $r['success']) {
            return [
                'success' => false,
                'message' => 'Ошибка при спавне транспорта: '.($r['response'] ?? 'сервер не ответил'),
            ];
        }

        // Обновляем счётчики использования
        $newUsed = (int) $policy['claims_used'] + 1;
        $total = (int) $policy['claims_total'];
        $newStatus = $newUsed >= $total ? 'claimed' : 'pending';

        DB::table('vehicle_insurances')->where('id', $policy['id'])->update([
            'claims_used' => $newUsed,
            'last_claim_at' => now(),
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

        $assetClean = str_replace(['BP_', '_ES', '_C'], '', $policy['vehicle_asset']);
        $remClaims = max(0, $total - $newUsed);

        return [
            'success' => true,
            'message' => "Транспорт {$assetClean} успешно выдан! Оставшихся страховых случаев: {$remClaims}",
        ];
    }

    /**
     * Отвязать текущую машину от страховки (для продажи или смены авто).
     */
    public function unbindVehicle(string $steamId): array
    {
        $policy = $this->getPlayerPolicy($steamId);
        if (! $policy || $policy['status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'У вас нет привязанного автомобиля для отвязки.',
            ];
        }

        DB::table('vehicle_insurances')->where('id', $policy['id'])->update([
            'vehicle_id' => null,
            'vehicle_asset' => null,
            'vehicle_alias' => null,
            'status' => 'pending',
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Страховка отвязана от предыдущего автомобиля. Сядьте в новое авто и введите: !страховка привязать',
        ];
    }

    /**
     * Обработка события уничтожения транспорта из логов FtpLogWatcher.
     */
    public function onVehicleDestroyed(int $vehicleId): void
    {
        if ($vehicleId <= 0) {
            return;
        }

        $policy = DB::table('vehicle_insurances')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'active')
            ->first();

        if (! $policy) {
            return;
        }

        DB::table('vehicle_insurances')->where('id', $policy->id)->update([
            'status' => 'destroyed',
            'updated_at' => now(),
        ]);

        Log::channel('scum')->info("[Insurance] Insured vehicle #{$vehicleId} destroyed. Policy #{$policy->id} marked as destroyed.");

        $text = 'Ваш застрахованный транспорт был уничтожен! Используйте команду !страховка вернуть для восстановления.';
        $cleanSid = preg_replace('/[^0-9]/', '', $policy->steam_id);
        if ($cleanSid !== '') {
            $this->rcon->sendCommand("SendChat 4 \"{$text}\" {$cleanSid}");
        }
    }

    /**
     * Статистика для плагина.
     */
    public function getStats(): array
    {
        return [
            'total' => DB::table('vehicle_insurances')->count(),
            'active' => DB::table('vehicle_insurances')->where('status', 'active')->count(),
            'pending' => DB::table('vehicle_insurances')->where('status', 'pending')->count(),
            'claimed' => DB::table('vehicle_insurances')->where('status', 'claimed')->count(),
            'destroyed' => DB::table('vehicle_insurances')->where('status', 'destroyed')->count(),
        ];
    }

    /**
     * Список всех страховок для веб-панели.
     */
    public function listAll(int $limit = 100, ?string $search = null): array
    {
        $query = DB::table('vehicle_insurances')->orderByDesc('id');

        if (! empty($search)) {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('steam_id', 'like', "%{$s}%")
                    ->orWhere('player_name', 'like', "%{$s}%")
                    ->orWhere('vehicle_asset', 'like', "%{$s}%")
                    ->orWhere('vehicle_alias', 'like', "%{$s}%")
                    ->orWhere('vehicle_id', 'like', "%{$s}%");
            });
        }

        return $query->limit($limit)->get()->map(fn ($r) => (array) $r)->toArray();
    }

    public function deleteInsurance(int $id): bool
    {
        return DB::table('vehicle_insurances')->where('id', $id)->delete() > 0;
    }

    public function getInsurance(int $id): ?array
    {
        $row = DB::table('vehicle_insurances')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function updateInsurance(int $id, array $data): bool
    {
        $update = [];

        if (array_key_exists('steam_id', $data)) {
            $update['steam_id'] = trim((string) $data['steam_id']);
        }
        if (array_key_exists('player_name', $data)) {
            $update['player_name'] = trim((string) $data['player_name']) ?: null;
        }
        if (array_key_exists('vehicle_id', $data)) {
            $update['vehicle_id'] = ! empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        }
        if (array_key_exists('vehicle_asset', $data)) {
            $update['vehicle_asset'] = trim((string) $data['vehicle_asset']) ?: null;
        }
        if (array_key_exists('vehicle_alias', $data)) {
            $update['vehicle_alias'] = trim((string) $data['vehicle_alias']) ?: null;
        }
        if (array_key_exists('status', $data) && in_array($data['status'], ['pending', 'active', 'destroyed', 'claimed', 'expired'], true)) {
            $update['status'] = $data['status'];
        }
        if (array_key_exists('claims_total', $data)) {
            $update['claims_total'] = max(1, (int) $data['claims_total']);
        }
        if (array_key_exists('claims_used', $data)) {
            $update['claims_used'] = max(0, (int) $data['claims_used']);
        }
        if (array_key_exists('expires_at', $data)) {
            $exp = trim((string) $data['expires_at']);
            $update['expires_at'] = ! empty($exp) ? Carbon::parse($exp) : null;
        }
        if (array_key_exists('notes', $data)) {
            $update['notes'] = trim((string) $data['notes']) ?: null;
        }

        if (empty($update)) {
            return false;
        }

        $update['updated_at'] = now();

        return DB::table('vehicle_insurances')->where('id', $id)->update($update) >= 0;
    }

    // ===== Helpers =====

    private function getPlayerInfo(string $steamId): ?array
    {
        $loc = $this->getPlayerLocation($steamId);
        if (! $loc) {
            return null;
        }

        return [
            'steamId' => $steamId,
            'name' => null,
            'upid' => null,
            'x' => $loc['x'],
            'y' => $loc['y'],
            'z' => $loc['z'],
        ];
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

    private function fetchSpawnedVehicles(): array
    {
        $r = $this->rcon->sendCommand('ListSpawnedVehicles');
        if (! $r['success'] || empty($r['response'])) {
            return [];
        }

        return VehicleParser::parseList($r['response']);
    }
}
