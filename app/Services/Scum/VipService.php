<?php

declare(strict_types=1);

namespace App\Services\Scum;

use App\Support\ItemParser;

class VipService
{
    public function __construct(
        private readonly PluginService $plugins,
        private readonly SettingsService $settings,
    ) {}

    public function getVipConfig(): array
    {
        return $this->plugins->getPluginsConfig()['vip'] ?? [];
    }

    public function getPlayers(): array
    {
        return $this->settings->getBotData()['vip']['players'] ?? [];
    }

    public function saveVipConfig(array $input): void
    {
        $vipCurrent = $this->getVipConfig();
        $vip = [
            'enabled' => $vipCurrent['enabled'] ?? false,
            'starterBonus' => [
                'items' => ItemParser::parse($input['starter_items'] ?? ''),
                'money' => (float) ($input['starter_money'] ?? 0),
                'gold' => (float) ($input['starter_gold'] ?? 0),
                'fame' => (float) ($input['starter_fame'] ?? 0),
            ],
            'dailyBonus' => [
                'items' => ItemParser::parse($input['daily_items'] ?? ''),
                'money' => (float) ($input['daily_money'] ?? 0),
                'gold' => (float) ($input['daily_gold'] ?? 0),
                'fame' => (float) ($input['daily_fame'] ?? 0),
            ],
        ];

        $this->plugins->savePluginsConfig(['vip' => $vip]);
        $this->settings->saveBotData(['vip' => $vip]);
    }

    public function addPlayer(string $steamId, int $days = 30): void
    {
        $bot = $this->settings->getBotData();
        $vip = $bot['vip'] ?? [
            'enabled' => true,
            'players' => [],
            'starterBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0],
            'dailyBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0],
        ];
        $players = $vip['players'] ?? [];
        $found = false;

        foreach ($players as &$p) {
            if (($p['steamId'] ?? '') === $steamId) {
                $p['expiresAt'] = time() + $days * 86400;
                $found = true;
                break;
            }
        }
        unset($p);

        if (! $found) {
            $players[] = ['steamId' => $steamId, 'expiresAt' => time() + $days * 86400, 'note' => ''];
        }

        $vip['players'] = $players;
        $this->settings->saveBotData(['vip' => $vip]);
    }

    public function removePlayer(string $steamId): void
    {
        $bot = $this->settings->getBotData();
        $vip = $bot['vip'] ?? ['players' => []];
        $vip['players'] = array_values(array_filter($vip['players'] ?? [], fn ($p) => ($p['steamId'] ?? '') !== $steamId));
        $this->settings->saveBotData(['vip' => $vip]);
    }
}
