@if ($blacklisted)
    <div class="mb-4 p-3.5 rounded-xl border border-[#da3633]/50 bg-[#da3633]/15 text-[#f85149] text-xs sm:text-sm flex items-center gap-2.5">
        <x-icon name="alert-triangle" class="w-4 h-4 text-[#f85149] shrink-0" />
        <span>Игрок в чёрном списке рейтинга — позиция скрыта.</span>
    </div>
@endif

{{-- Метрики игрока --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="panel p-3.5 rounded-xl bg-[#161b22]/80 border border-[#30363d]/70 flex flex-col justify-between">
        <div class="flex items-center justify-between text-[11px] text-[#8b949e] font-semibold mb-1">
            <span>Место</span>
            <x-icon name="trophy" class="w-4 h-4 text-[#d29922]" />
        </div>
        <div class="text-xl sm:text-2xl font-bold text-[#d29922]">#{{ $rank }}</div>
    </div>
    
    <div class="panel p-3.5 rounded-xl bg-[#161b22]/80 border border-[#30363d]/70 flex flex-col justify-between">
        <div class="flex items-center justify-between text-[11px] text-[#8b949e] font-semibold mb-1">
            <span>В игре</span>
            <x-icon name="clock" class="w-4 h-4 text-[#58a6ff]" />
        </div>
        <div class="text-xl sm:text-2xl font-bold text-[#e6edf3]">{{ gmdate('Hч iм', $player['onlineSeconds'] ?? 0) }}</div>
    </div>
    
    <div class="panel p-3.5 rounded-xl bg-[#161b22]/80 border border-[#30363d]/70 flex flex-col justify-between">
        <div class="flex items-center justify-between text-[11px] text-[#8b949e] font-semibold mb-1">
            <span>Деньги</span>
            <x-icon name="coins" class="w-4 h-4 text-[#3fb950]" />
        </div>
        <div class="text-xl sm:text-2xl font-bold text-[#3fb950] tabular-nums">${{ number_format($player['money'] ?? 0, 0, ',', ' ') }}</div>
    </div>
    
    <div class="panel p-3.5 rounded-xl bg-[#161b22]/80 border border-[#30363d]/70 flex flex-col justify-between">
        <div class="flex items-center justify-between text-[11px] text-[#8b949e] font-semibold mb-1">
            <span>Золото</span>
            <x-icon name="coins" class="w-4 h-4 text-[#d29922]" />
        </div>
        <div class="text-xl sm:text-2xl font-bold text-[#d29922] tabular-nums">{{ number_format($player['gold'] ?? 0, 0, ',', ' ') }}</div>
    </div>
</div>

{{-- Детальная карточка --}}
<div class="panel p-4 sm:p-5 rounded-2xl border border-[#30363d]/80 bg-[#161b22]/60 space-y-4">
    <div class="flex items-center justify-between border-b border-[#21262d] pb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]">
                <x-icon name="user" class="w-4 h-4" />
            </div>
            <div>
                <h3 class="font-bold text-sm text-[#e6edf3]">{{ $player['playerName'] ?: 'Неизвестный' }}</h3>
                <div class="text-[11px] text-[#8b949e] font-mono">{{ $player['steamId'] }}</div>
            </div>
        </div>
        <button type="button" @click="navigator.clipboard.writeText('{{ $player['steamId'] }}'); window.toast ? window.toast('Steam ID скопирован', 'success') : null"
                class="btn btn-xs bg-[#21262d] hover:bg-[#30363d] text-[#8b949e] hover:text-[#e6edf3] gap-1.5" title="Скопировать Steam ID">
            <x-icon name="copy" class="w-3 h-3" />
            <span>Скопировать</span>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="p-2.5 rounded-xl bg-[#0d1117] border border-[#21262d] flex items-center justify-between">
            <span class="text-[#8b949e]">Очки славы (Fame):</span>
            <span class="font-bold text-[#e6edf3] tabular-nums">{{ number_format($player['fame'] ?? 0, 0, ',', ' ') }}</span>
        </div>
        <div class="p-2.5 rounded-xl bg-[#0d1117] border border-[#21262d] flex items-center justify-between">
            <span class="text-[#8b949e]">Последний вход:</span>
            <span class="text-[#e6edf3] font-mono">{{ isset($player['lastSeen']) ? date('d.m.Y H:i', $player['lastSeen']) : '—' }}</span>
        </div>
    </div>

    {{-- Быстрые переходы --}}
    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-[#21262d]">
        <a href="{{ route('players.whois', $player['steamId']) }}"
           class="btn btn-sm bg-[#21262d] hover:bg-[#30363d] text-xs text-[#e6edf3] flex items-center gap-1.5"
           onclick="event.preventDefault(); openModal(@js(route('players.whois', $player['steamId'])), @js('Whois: '.$player['playerName']), 3);">
            <x-icon name="search" class="w-3.5 h-3.5 text-[#58a6ff]" />
            <span>Whois карточка</span>
        </a>

        <a href="{{ route('scumdb.player', $player['steamId']) }}"
           class="btn btn-sm bg-[#21262d] hover:bg-[#30363d] text-xs text-[#e6edf3] flex items-center gap-1.5">
            <x-icon name="database" class="w-3.5 h-3.5 text-[#58a6ff]" />
            <span>SCUM.db профиль</span>
        </a>

        <a href="{{ route('wargm.deliveries', $player['steamId']) }}"
           class="btn btn-sm bg-[#21262d] hover:bg-[#30363d] text-xs text-[#e6edf3] flex items-center gap-1.5"
           onclick="event.preventDefault(); openModal(@js(route('wargm.deliveries', $player['steamId'])), @js('WARGM выдачи: '.$player['playerName']));">
            <x-icon name="shopping-cart" class="w-3.5 h-3.5 text-[#a371f7]" />
            <span>WARGM покупки</span>
        </a>
    </div>
</div>
