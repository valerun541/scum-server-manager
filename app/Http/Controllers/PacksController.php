<?php

namespace App\Http\Controllers;

use App\Http\Requests\PacksCooldownResetRequest;
use App\Http\Requests\PacksGiveRequest;
use App\Http\Requests\PacksSaveRequest;
use App\Services\Scum\ChatCommandProcessor;
use App\Services\Scum\PluginService;
use App\Services\Scum\RconService;
use App\Services\Scum\SettingsService;
use App\Support\ItemParser;

class PacksController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private ChatCommandProcessor $chat,
        private RconService $rcon,
        private PluginService $plugins,
    ) {}

    public function index()
    {
        $packs = $this->chat->getPacksConfig();

        return view('packs.index', [
            'packs' => $packs,
            'cooldowns' => $this->chat->getCooldowns(),
            'items' => $this->plugins->loadItemList(),
            'rconConnected' => $this->rcon->isConnected(),
        ]);
    }

    public function save(PacksSaveRequest $request)
    {
        $starterItems = ItemParser::parse($request->input('starter_items', ''));
        $dailyItems = ItemParser::parse($request->input('daily_items', ''));

        $packs = [
            'starter' => [
                'enabled' => $request->has('starter_enabled') && $request->input('starter_enabled') === '1',
                'items' => $starterItems,
                'cooldownHours' => (int) $request->input('starter_cooldown', 0),
            ],
            'daily' => [
                'enabled' => $request->has('daily_enabled') && $request->input('daily_enabled') === '1',
                'items' => $dailyItems,
                'cooldownHours' => (int) $request->input('daily_cooldown', 24),
            ],
        ];

        // Сохраняем и в app_config, и в bot_data (как в оригинале)
        $this->settings->saveAppConfig(['packs' => $packs]);
        $this->settings->saveBotData(['packs' => $packs]);

        return back()->with('success', 'Паки сохранены');
    }

    public function give(PacksGiveRequest $request)
    {
        $steamId = (string) $request->input('steam_id', '');
        $type = $request->input('type', 'starter');
        if (! $steamId) {
            return back()->with('error', 'Не указан SteamID');
        }
        $this->chat->resetPlayerCooldown($steamId);
        // Имитируем выдачу: обрабатываем как чат-команду от игрока
        $this->chat->process($steamId, 'Admin', $type === 'starter' ? '!startpack' : '!dailypack');

        return back()->with('success', 'Паки выданы');
    }

    public function resetCooldown(PacksCooldownResetRequest $request)
    {
        $steamId = (string) $request->input('steam_id', '');
        $type = $request->input('type') ?: null;
        if (! $steamId) {
            return back()->with('error', 'Не указан SteamID');
        }
        $this->chat->resetPlayerCooldown($steamId, $type);

        return back()->with('success', 'Кулдаун сброшен');
    }
}
