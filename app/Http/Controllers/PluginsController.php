<?php

namespace App\Http\Controllers;

use App\Http\Requests\AirdropDropRequest;
use App\Http\Requests\AirdropSaveRequest;
use App\Http\Requests\BlacklistSaveRequest;
use App\Http\Requests\CalibrationRecordRequest;
use App\Http\Requests\ChatSenderSaveRequest;
use App\Http\Requests\RewardsSaveRequest;
use App\Http\Requests\SaveHomeSaveRequest;
use App\Http\Requests\TeleportSaveRequest;
use App\Http\Requests\VipPlayerRequest;
use App\Http\Requests\VipSaveRequest;
use App\Services\Scum\InsuranceService;
use App\Services\Scum\PluginService;
use App\Services\Scum\RatingService;
use App\Services\Scum\SettingsService;
use App\Services\Scum\VipService;
use Illuminate\Http\Request;

class PluginsController extends Controller
{
    public function __construct(
        private PluginService $plugins,
        private SettingsService $settings,
        private RatingService $rating,
        private VipService $vipService,
    ) {}

    // ===== Teleport =====

    public function teleport()
    {
        $config = $this->plugins->getPluginsConfig();

        return view('plugins.teleport', [
            'teleport' => $config['teleport'] ?? [],
            'locations' => $this->settings->getBotData()['teleportLocations'] ?? [],
        ]);
    }

    public function saveTeleport(TeleportSaveRequest $request)
    {
        $config = $this->plugins->getPluginsConfig();
        $locations = $this->parseLocations($request->input('locations_json', ''));

        $config['teleport'] = [
            'enabled' => $config['teleport']['enabled'] ?? false,
            'locations' => $locations,
        ];
        $this->plugins->savePluginsConfig(['teleport' => $config['teleport']]);
        $this->settings->saveBotData(['teleportLocations' => $locations]);

        return back()->with('success', 'Телепорт сохранён');
    }

    // ===== VIP =====

    public function vip()
    {
        $vip = $this->vipService->getVipConfig();
        $players = $this->vipService->getPlayers();
        $itemText = fn (array $items) => collect($items)->map(fn ($i) => '"'.$i['id'].'" "'.($i['name'] ?? '').'"')->implode("\n");

        return view('plugins.vip', [
            'vip' => $vip,
            'players' => $players,
            'starterItemsText' => $itemText($vip['starterBonus']['items'] ?? []),
            'dailyItemsText' => $itemText($vip['dailyBonus']['items'] ?? []),
        ]);
    }

    public function saveVip(VipSaveRequest $request)
    {
        $this->vipService->saveVipConfig($request->all());

        return back()->with('success', 'VIP конфигурация сохранена');
    }

    public function addVipPlayer(VipPlayerRequest $request)
    {
        $steamId = (string) $request->input('steam_id', '');
        $days = (int) $request->input('days', 30);
        if (! $steamId || $days <= 0) {
            return back()->with('error', 'Укажите SteamID и срок');
        }
        $this->vipService->addPlayer($steamId, $days);

        return back()->with('success', 'VIP игрок добавлен');
    }

    public function removeVipPlayer(VipPlayerRequest $request)
    {
        $steamId = (string) $request->input('steam_id', '');
        if (! $steamId) {
            return back()->with('error', 'Укажите SteamID');
        }
        $this->vipService->removePlayer($steamId);

        return back()->with('success', 'VIP игрок удалён');
    }

    // ===== SaveHome =====

    public function saveHome()
    {
        $config = $this->plugins->getPluginsConfig();

        return view('plugins.savehome', [
            'saveHome' => $config['saveHome'] ?? [],
            'homes' => $this->settings->getBotData()['homeLocations'] ?? [],
        ]);
    }

    public function saveSaveHome(SaveHomeSaveRequest $request)
    {
        $config = $this->plugins->getPluginsConfig();
        $saveHome = [
            'enabled' => $config['saveHome']['enabled'] ?? false,
            'maxLocations' => (int) $request->input('max_locations', 1),
            'vipMaxLocations' => (int) $request->input('vip_max_locations', 3),
            'teleportPrice' => (float) $request->input('teleport_price', 0),
        ];
        $this->plugins->savePluginsConfig(['saveHome' => $saveHome]);
        $this->settings->saveBotData(['saveHome' => $saveHome]);

        return back()->with('success', 'SaveHome сохранён');
    }

    // ===== Airdrop =====

    public function airdrop()
    {
        $config = $this->plugins->getPluginsConfig();
        $calibration = $this->plugins->getCalibrationStatus();

        return view('plugins.airdrop', [
            'airdrop' => $config['airdrop'] ?? [],
            'calibration' => $calibration,
        ]);
    }

    public function saveAirdrop(AirdropSaveRequest $request)
    {
        $config = $this->plugins->getPluginsConfig();
        $config['airdrop'] = [
            'enabled' => $config['airdrop']['enabled'] ?? false,
            'chestItem' => $request->input('chest_item', 'Improvised_Metal_Chest'),
            'minItems' => (int) $request->input('min_items', 3),
            'maxItems' => (int) $request->input('max_items', 8),
            'cooldownMinutes' => (int) $request->input('cooldown_minutes', 60),
            'autoDropEnabled' => $request->has('auto_drop_enabled') && $request->input('auto_drop_enabled') === '1',
            'autoDropIntervalMinutes' => (int) $request->input('auto_drop_interval', 30),
            'autoDropMinPlayers' => (int) $request->input('auto_drop_min_players', 2),
        ];
        $this->plugins->savePluginsConfig(['airdrop' => $config['airdrop']]);

        return back()->with('success', 'Airdrop настройки сохранены');
    }

    public function dropAirdrop(AirdropDropRequest $request)
    {
        $x = $request->input('x') !== '' && $request->input('x') !== null ? (float) $request->input('x') : null;
        $y = $request->input('y') !== '' && $request->input('y') !== null ? (float) $request->input('y') : null;
        $z = $request->input('z') !== '' && $request->input('z') !== null ? (float) $request->input('z') : null;
        $result = $this->plugins->dropAt($x, $y, $z);

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Аирдроп сброшен в '.implode(', ', array_map('round', $result['coords'])) : ($result['error'] ?? 'Ошибка'));
    }

    public function recordCalibration(CalibrationRecordRequest $request)
    {
        $index = (int) $request->input('index', 0);
        $z = (float) $request->input('z', 0);
        $this->plugins->recordCalibrationPoint($index, $z);

        return back()->with('success', "Точка #{$index} записана (Z={$z})");
    }

    public function resetCalibration()
    {
        $this->plugins->resetCalibration();

        return back()->with('success', 'Калибровка сброшена (125 точек)');
    }

    // ===== Rewards =====

    public function rewards()
    {
        $config = $this->plugins->getPluginsConfig();
        $blacklist = $config['ratingBlacklist'] ?? [];
        $leaderboard = array_values(array_filter($this->rating->getLeaderboard(), fn ($r) => ! in_array($r['steamId'], $blacklist)));

        return view('plugins.rewards', [
            'rewards' => $config['rewards'] ?? [],
            'data' => $this->plugins->getRewardsData(),
            'leaderboard' => $leaderboard,
            'totalOnlineSeconds' => $this->rating->getTotalOnlineSeconds(),
            'blacklist' => $blacklist,
        ]);
    }

    public function rewardsPlayer(Request $request, string $steamId)
    {
        $blacklist = $this->plugins->getPluginsConfig()['ratingBlacklist'] ?? [];
        $leaderboard = $this->rating->getLeaderboard();
        $player = null;
        $rank = 0;
        foreach ($leaderboard as $i => $e) {
            if ($e['steamId'] === $steamId) {
                $player = $e;
                $rank = $i + 1;
                break;
            }
        }
        if (! $player) {
            return response('<div class="text-sm text-[#8b949e] text-center py-8">Игрок не найден в рейтинге (нет учтённого времени в игре).</div>');
        }

        return view('plugins.rewards-player-fragment', [
            'player' => $player,
            'rank' => in_array($steamId, $blacklist) ? 0 : $rank,
            'blacklisted' => in_array($steamId, $blacklist),
        ]);
    }

    public function saveRewards(RewardsSaveRequest $request)
    {
        $config = $this->plugins->getPluginsConfig();
        $config['rewards'] = [
            'enabled' => $config['rewards']['enabled'] ?? false,
            'hourlyEnabled' => $request->has('hourly_enabled') && $request->input('hourly_enabled') === '1',
            'hourlyMoney' => (float) $request->input('hourly_money', 0),
            'hourlyGold' => (float) $request->input('hourly_gold', 0),
            'hourlyFame' => (float) $request->input('hourly_fame', 0),
            'topEnabled' => $request->has('top_enabled') && $request->input('top_enabled') === '1',
            'topIntervalDays' => (int) $request->input('top_interval_days', 1),
            'topCount' => (int) $request->input('top_count', 3),
            'topMoney' => (float) $request->input('top_money', 1000),
            'topGold' => (float) $request->input('top_gold', 100),
            'topFame' => (float) $request->input('top_fame', 0),
        ];
        $this->plugins->savePluginsConfig(['rewards' => $config['rewards']]);

        return back()->with('success', 'Rewards сохранены');
    }

    public function hourlyRewards()
    {
        $result = $this->plugins->hourlyRewardsTick();

        return back()->with($result['ok'] ? 'success' : 'info', $result['ok'] ? "Часовые награды выданы ({$result['granted']} игрокам)" : 'Часовые награды не выданы');
    }

    public function topRewards()
    {
        $result = $this->plugins->topRewardsTick();

        return back()->with($result['ok'] ? 'success' : 'info', $result['ok'] ? 'Топ-награды выданы' : 'Топ-награды не выданы');
    }

    // ===== Rating blacklist =====

    public function saveBlacklist(BlacklistSaveRequest $request)
    {
        $list = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $request->input('blacklist', '')) ?: []));
        $config = $this->plugins->getPluginsConfig();
        $config['ratingBlacklist'] = array_values($list);
        $this->plugins->savePluginsConfig(['ratingBlacklist' => $config['ratingBlacklist']]);

        return back()->with('success', 'Чёрный список рейтинга сохранён');
    }

    // ===== Chat sender =====

    public function saveChatSender(ChatSenderSaveRequest $request)
    {
        $config = $this->plugins->getPluginsConfig();
        $config['chatSender'] = $request->input('chat_sender', 'AMUR bot') ?: 'AMUR bot';
        $this->plugins->savePluginsConfig(['chatSender' => $config['chatSender']]);

        return back()->with('success', 'Имя отправителя сохранено');
    }

    // ===== Утилиты =====

    private function parseLocations(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }
        $result = [];
        foreach ($data as $loc) {
            if (empty($loc['name']) || ! isset($loc['x'])) {
                continue;
            }
            $result[] = [
                'name' => $loc['name'],
                'x' => (float) $loc['x'],
                'y' => (float) $loc['y'],
                'z' => (float) ($loc['z'] ?? 0),
            ];
        }

        return $result;
    }

    // ===== Insurance =====

    public function insurance(Request $request, InsuranceService $insurance)
    {
        $settings = $insurance->getSettings();
        $stats = $insurance->getStats();
        $search = $request->query('q');
        $policies = $insurance->listAll(limit: 100, search: $search);

        return view('plugins.insurance', [
            'settings' => $settings,
            'stats' => $stats,
            'policies' => $policies,
            'search' => $search,
        ]);
    }

    public function saveInsuranceSettings(Request $request, InsuranceService $insurance)
    {
        $insurance->saveSettings([
            'cooldown_minutes' => (int) $request->input('cooldown_minutes', 60),
            'claims_per_policy' => (int) $request->input('claims_per_policy', 1),
            'duration_days' => (int) $request->input('duration_days', 30),
            'destroy_old_if_alive' => $request->has('destroy_old_if_alive'),
        ]);

        return back()->with('success', 'Настройки страхования сохранены');
    }

    public function grantInsurance(Request $request, InsuranceService $insurance)
    {
        $steamId = trim((string) $request->input('steam_id', ''));
        if (empty($steamId)) {
            return back()->with('error', 'Укажите Steam ID игрока');
        }

        $playerName = trim((string) $request->input('player_name', '')) ?: null;
        $claims = max(1, (int) $request->input('claims', 1));
        $durationDays = (int) $request->input('duration_days', 30);

        $res = $insurance->grantInsurance($steamId, $playerName, 'MANUAL', $claims, $durationDays);

        return back()->with($res['success'] ? 'success' : 'error', $res['message']);
    }

    public function claimInsuranceAdmin(Request $request, InsuranceService $insurance)
    {
        $steamId = trim((string) $request->input('steam_id', ''));
        if (empty($steamId)) {
            return back()->with('error', 'Укажите Steam ID игрока');
        }

        $res = $insurance->claimVehicle($steamId, null, isAdmin: true);

        return back()->with($res['success'] ? 'success' : 'error', $res['message']);
    }

    public function updateInsurance(int $id, Request $request, InsuranceService $insurance)
    {
        $validated = $request->validate([
            'steam_id' => 'required|string|max:32',
            'player_name' => 'nullable|string|max:128',
            'vehicle_id' => 'nullable|integer',
            'vehicle_asset' => 'nullable|string|max:128',
            'vehicle_alias' => 'nullable|string|max:128',
            'status' => 'required|in:pending,active,destroyed,claimed,expired',
            'claims_total' => 'required|integer|min:1|max:1000',
            'claims_used' => 'required|integer|min:0|max:1000',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $insurance->updateInsurance($id, $validated);

        return back()->with('success', "Страховой полис #{$id} успешно обновлён");
    }

    public function deleteInsurance(int $id, InsuranceService $insurance)
    {
        $insurance->deleteInsurance($id);

        return back()->with('success', "Страховой полис #{$id} удалён");
    }
}
