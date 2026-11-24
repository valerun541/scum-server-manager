<?php

namespace App\View\Composers;

use App\Services\Scum\SettingsService;
use Illuminate\View\View;

class LayoutComposer
{
    public function __construct(private SettingsService $settings) {}

    public function compose(View $view): void
    {
        $appConfig = $this->settings->getAppConfig();
        $pluginEnabledFlags = $appConfig['plugins'] ?? [];

        // 1. Мониторинг
        $monitoringNav = [
            ['name' => 'Серверы', 'route' => 'servers.index', 'icon' => 'server', 'badge' => null],
            ['name' => 'Игроки онлайн', 'route' => 'players.index', 'icon' => 'users', 'badge' => null],
            ['name' => 'Карта острова', 'route' => 'map.index', 'icon' => 'map', 'badge' => null],
        ];

        // 2. Управление и Инструменты
        $managementNav = [
            ['name' => 'RCON Консоль', 'route' => 'rcon.index', 'icon' => 'terminal', 'badge' => null],
            ['name' => 'База SCUM.db', 'route' => 'scumdb.index', 'icon' => 'database', 'badge' => null],
            ['name' => 'Паки лута', 'route' => 'packs.index', 'icon' => 'package', 'badge' => null],
            ['name' => 'Конфиги сервера', 'route' => 'config-files.index', 'icon' => 'settings', 'badge' => null],
            ['name' => 'Автоматизация', 'route' => 'automation.index', 'icon' => 'cpu', 'badge' => null],
            ['name' => 'Магазин WARGM', 'route' => 'wargm.index', 'icon' => 'shopping-cart', 'badge' => null],
        ];

        // 3. Плагины
        $pluginPages = [
            ['name' => 'VIP статус', 'route' => 'plugins.vip', 'icon' => 'crown'],
            ['name' => 'Страхование ТС', 'route' => 'plugins.insurance', 'icon' => 'shield'],
            ['name' => 'Airdrop дропы', 'route' => 'plugins.airdrop', 'icon' => 'parachute'],
            ['name' => 'SaveHome база', 'route' => 'plugins.savehome', 'icon' => 'home'],
            ['name' => 'Телепорт точки', 'route' => 'plugins.teleport', 'icon' => 'compass'],
            ['name' => 'Награды и Топ', 'route' => 'plugins.rewards', 'icon' => 'trophy'],
        ];

        $pluginKeys = ['vip', 'insurance', 'airdrop', 'saveHome', 'teleport', 'rewards'];
        $filteredPlugins = [];
        foreach ($pluginPages as $i => $p) {
            $key = $pluginKeys[$i];
            if (($pluginEnabledFlags[$key]['enabled'] ?? true) !== false) {
                $filteredPlugins[] = $p;
            }
        }

        // 4. Логи сервера
        $logHubNav = [
            ['name' => 'Чат игроков', 'route' => 'logs.chat', 'icon' => 'message-square'],
            ['name' => 'События игры', 'route' => 'logs.events', 'icon' => 'activity'],
            ['name' => 'Убийства (PVP/PVE)', 'route' => 'logs.kill', 'icon' => 'crosshair'],
            ['name' => 'RCON команды', 'route' => 'logs.rcon', 'icon' => 'terminal'],
            ['name' => 'Аудит действий', 'route' => 'logs.audit', 'icon' => 'file-text'],
            ['name' => 'Экономика / Банк', 'route' => 'logs.economy', 'icon' => 'coins'],
            ['name' => 'Входы / Выходы', 'route' => 'logs.login', 'icon' => 'users'],
            ['name' => 'Админ действия', 'route' => 'logs.admin', 'icon' => 'shield'],
            ['name' => 'Системные логи', 'route' => 'logs.system', 'icon' => 'settings'],
        ];

        $view->with('monitoringNav', $monitoringNav)
            ->with('managementNav', $managementNav)
            ->with('coreNav', $monitoringNav)
            ->with('toolsNav', $managementNav)
            ->with('pluginPages', $filteredPlugins)
            ->with('logLinks', $logHubNav);
    }
}
