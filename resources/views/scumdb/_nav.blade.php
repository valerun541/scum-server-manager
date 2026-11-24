@php
    $currentRoute = request()->route()?->getName() ?? '';
    $navTabs = [
        ['route' => 'scumdb.index', 'name' => 'index', 'icon' => 'database', 'label' => 'Обзор'],
        ['route' => 'scumdb.players', 'name' => 'players', 'icon' => 'users', 'label' => 'Игроки'],
        ['route' => 'scumdb.squads', 'name' => 'squads', 'icon' => 'shield', 'label' => 'Отряды'],
        ['route' => 'scumdb.vehicles', 'name' => 'vehicles', 'icon' => 'car', 'label' => 'Транспорт'],
        ['route' => 'scumdb.flags', 'name' => 'flags', 'icon' => 'flag', 'label' => 'Флаги баз'],
        ['route' => 'scumdb.banks', 'name' => 'banks', 'icon' => 'coins', 'label' => 'Банки'],
        ['route' => 'scumdb.economy', 'name' => 'economy', 'icon' => 'activity', 'label' => 'Экономика'],
    ];
@endphp

<div class="flex items-center gap-1.5 p-1.5 bg-[#0c1017] border border-[#21262d] rounded-2xl overflow-x-auto custom-scrollbar mb-6 shadow-sm">
    @foreach ($navTabs as $tab)
        @php
            $isActive = $currentRoute === $tab['route'] || (isset($active) && $active === $tab['name']);
        @endphp
        <a href="{{ route($tab['route']) }}"
           @class([
               'flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0',
               'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' => $isActive,
               'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]' => !$isActive,
           ])>
            <x-icon :name="$tab['icon']" class="w-3.5 h-3.5" />
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</div>
