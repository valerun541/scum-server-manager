@php
    $currentRoute = request()->route()?->getName() ?? '';
    $logTabs = [
        ['route' => 'logs.chat', 'name' => 'Чат игроков', 'icon' => 'message-square'],
        ['route' => 'logs.events', 'name' => 'События игры', 'icon' => 'activity'],
        ['route' => 'logs.kill', 'name' => 'Убийства (PVP/PVE)', 'icon' => 'crosshair'],
        ['route' => 'logs.rcon', 'name' => 'RCON команды', 'icon' => 'terminal'],
        ['route' => 'logs.audit', 'name' => 'Аудит действий', 'icon' => 'file-text'],
        ['route' => 'logs.economy', 'name' => 'Экономика / Банк', 'icon' => 'coins'],
        ['route' => 'logs.login', 'name' => 'Входы / Выходы', 'icon' => 'users'],
        ['route' => 'logs.vehicle', 'name' => 'Транспорт', 'icon' => 'car'],
        ['route' => 'logs.admin', 'name' => 'Админ действия', 'icon' => 'shield'],
        ['route' => 'logs.loot', 'name' => 'Лут / Спавны', 'icon' => 'package'],
        ['route' => 'logs.violations', 'name' => 'Нарушения', 'icon' => 'alert-triangle'],
        ['route' => 'logs.gameplay', 'name' => 'Геймплей', 'icon' => 'compass'],
        ['route' => 'logs.famepoints', 'name' => 'Очки славы', 'icon' => 'trophy'],
        ['route' => 'logs.quests', 'name' => 'Квесты', 'icon' => 'award'],
        ['route' => 'logs.sentry', 'name' => 'Дроны и роботы', 'icon' => 'bot'],
        ['route' => 'logs.system', 'name' => 'Системные логи', 'icon' => 'settings'],
    ];
@endphp

<div class="flex items-center gap-1.5 p-1.5 bg-[#0c1017] border border-[#21262d] rounded-2xl overflow-x-auto custom-scrollbar mb-6 shadow-sm">
    @foreach ($logTabs as $tab)
        @php
            $isActive = $currentRoute === $tab['route'];
        @endphp
        <a href="{{ route($tab['route']) }}"
           @class([
               'flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0',
               'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' => $isActive,
               'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]' => !$isActive,
           ])>
            <x-icon :name="$tab['icon']" class="w-3.5 h-3.5" />
            <span>{{ $tab['name'] }}</span>
        </a>
    @endforeach
</div>
