@extends('layouts.app')

@section('title', 'Онлайн игроки')



@section('content')
@php
    $onlineList = $online ?? [];
    $onlineCount = count($onlineList);
    $isConnected = $rconConnected ?? false;
@endphp

<div x-data="{
    search: '',
    sortBy: 'name_asc', // 'name_asc' | 'money_desc' | 'gold_desc' | 'fame_desc'
    autoRefresh: true,
    refreshInterval: 10,
    timer: null,
    isRefreshing: false,
    onlineCount: {{ $onlineCount }},
    isConnected: {{ $isConnected ? 'true' : 'false' }},
    lastUpdated: new Date().toLocaleTimeString(),
    players: {{ json_encode(array_values(array_map(function($p) {
        return [
            'steamId' => (string)($p['steamId'] ?? ''),
            'name' => (string)($p['name'] ?? 'Unknown'),
            'money' => (float)($p['money'] ?? 0),
            'gold' => (float)($p['gold'] ?? 0),
            'fame' => (float)($p['fame'] ?? 0),
            'location' => $p['location'] ?? null,
            'x' => isset($p['location']['x']) ? round($p['location']['x']) : null,
            'y' => isset($p['location']['y']) ? round($p['location']['y']) : null,
            'z' => isset($p['location']['z']) ? round($p['location']['z']) : null,
            'profileUrl' => route('scumdb.player', $p['steamId'] ?? ''),
        ];
    }, $onlineList)), JSON_UNESCAPED_UNICODE) }},

    init() {
        this.setupTimer();
        this.$watch('autoRefresh', () => this.setupTimer());
        this.$watch('refreshInterval', () => this.setupTimer());
    },

    setupTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        if (this.autoRefresh && this.refreshInterval > 0) {
            this.timer = setInterval(() => {
                this.fetchPlayers();
            }, this.refreshInterval * 1000);
        }
    },

    async fetchPlayers() {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        try {
            const res = await fetch('{{ route('players.index') }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.success) {
                    this.players = data.players || [];
                    this.onlineCount = data.onlineCount || 0;
                    this.isConnected = Boolean(data.rconConnected);
                    this.lastUpdated = new Date().toLocaleTimeString();
                }
            }
        } catch (e) {
            console.error('Ошибка автообновления игроков:', e);
        } finally {
            this.isRefreshing = false;
        }
    },

    get filteredPlayers() {
        let list = this.players.filter(p => {
            if (!this.search) return true;
            const q = this.search.toLowerCase().trim();
            const nameMatch = p.name.toLowerCase().includes(q);
            const steamMatch = p.steamId.includes(q);
            const coordsMatch = (p.x !== null && `${p.x} ${p.y}`.includes(q));
            return nameMatch || steamMatch || coordsMatch;
        });

        // Сортировка
        if (this.sortBy === 'name_asc') {
            list.sort((a, b) => a.name.localeCompare(b.name));
        } else if (this.sortBy === 'money_desc') {
            list.sort((a, b) => b.money - a.money);
        } else if (this.sortBy === 'gold_desc') {
            list.sort((a, b) => b.gold - a.gold);
        } else if (this.sortBy === 'fame_desc') {
            list.sort((a, b) => b.fame - a.fame);
        }

        return list;
    }
}" class="space-y-6">

    {{-- ===== Hero Banner с live-индикаторами и автообновлением ===== --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 sm:p-6 shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#2ea043]/10 blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb]/30 to-[#388bfd]/15 border border-[#1f6feb]/40 flex items-center justify-center text-[#58a6ff] shadow-lg shadow-[#1f6feb]/10 shrink-0">
                    <x-icon name="users" class="w-6 h-6 sm:w-7 sm:h-7" />
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-bold text-[#e6edf3] tracking-tight">Онлайн игроки на сервере</h1>
                        <template x-if="isConnected">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#238636]/20 text-[#3fb950] border border-[#238636]/40">
                                <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-pulse"></span> Сервер онлайн
                            </span>
                        </template>
                        <template x-if="!isConnected">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#da3633]/20 text-[#f85149] border border-[#da3633]/40">
                                RCON офлайн
                            </span>
                        </template>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-[#1f6feb]/20 text-[#58a6ff] border border-[#1f6feb]/40">
                            <span x-text="onlineCount"></span> в игре
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-[#8b949e] mt-1">
                        Мониторинг активных сессий в реальном времени, координаты персонажей, баланс и быстрые действия
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                {{-- Контролы автообновления --}}
                <div class="flex items-center gap-2 bg-[#0c1017] border border-[#21262d] px-3 py-1.5 rounded-xl text-xs">
                    <button type="button" @click="fetchPlayers()" :disabled="isRefreshing" class="btn btn-xs flex items-center gap-1.5 bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3]" title="Обновить сейчас вручную">
                        <span class="inline-block transition-transform text-xs" :class="isRefreshing ? 'animate-spin' : ''"><x-icon name="refresh-cw" class="w-3 h-3" /></span>
                        <span x-text="isRefreshing ? 'Загрузка...' : 'Обновить'"></span>
                    </button>
                    <div class="h-4 w-px bg-[#21262d]"></div>
                    <label class="flex items-center gap-1.5 cursor-pointer text-[#8b949e] hover:text-[#e6edf3] select-none text-xs">
                        <input type="checkbox" x-model="autoRefresh" class="rounded border-[#30363d] bg-[#161b22] text-[#58a6ff] focus:ring-0">
                        <span>Авто</span>
                    </label>
                    <select x-show="autoRefresh" x-model.number="refreshInterval" class="bg-[#161b22] border border-[#30363d] rounded-lg text-xs py-0.5 px-1.5 text-[#c9d1d9] focus:outline-none focus:border-[#58a6ff]">
                        <option value="5">5 с</option>
                        <option value="10">10 с</option>
                        <option value="30">30 с</option>
                        <option value="60">1 мин</option>
                    </select>
                </div>

                <a href="{{ route('scumdb.players') }}" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] border-[#30363d] text-xs text-[#8b949e] hover:text-[#e6edf3] flex items-center gap-1.5 shadow-sm" title="Открыть базу данных всех игроков">
                    <x-icon name="database" class="w-3.5 h-3.5 text-[#58a6ff]" />
                    <span>Все игроки (SCUM.db)</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Предупреждение если RCON не подключён --}}
    <div x-show="!isConnected" class="p-4 rounded-xl border border-[#d29922]/50 bg-[#3a2f1a]/40 text-[#d29922] text-sm flex items-center gap-3" x-cloak>
        <x-icon name="alert-triangle" class="w-6 h-6 text-amber-400" />
        <div>
            <span class="font-bold">RCON-соединение не активно.</span>
            <span class="text-xs text-[#e6edf3]/80 block mt-0.5">Для обновления актуального онлайна включите RCON на панели управления.</span>
        </div>
    </div>

    {{-- ===== Панель поиска и сортировки ===== --}}
    <div class="panel p-3.5 sm:p-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-[#161b22]">
        <div class="flex-1 relative flex items-center">
            <span class="absolute left-3.5 text-[#8b949e] text-sm pointer-events-none flex items-center justify-center"><x-icon name="search" class="w-4 h-4" /></span>
            <input type="text" 
                   x-model="search" 
                   class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-10 pr-9 py-2 text-sm text-[#e6edf3] placeholder-[#8b949e] transition-colors outline-none" 
                   placeholder="Поиск по нику, SteamID или координатам...">
            <button type="button" x-show="search" 
                    @click="search = ''" 
                    class="absolute right-3 text-[#8b949e] hover:text-[#e6edf3] text-sm p-0.5 rounded transition-colors" 
                    title="Очистить поиск"><x-icon name="x" class="w-3.5 h-3.5" /></button>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <div class="flex items-center gap-2 text-xs text-[#8b949e] w-full sm:w-auto justify-between sm:justify-start">
                <span class="font-medium whitespace-nowrap">Сортировка:</span>
                <select x-model="sortBy" class="bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] rounded-lg text-xs py-2 px-2.5 text-[#c9d1d9] focus:outline-none transition-colors">
                    <option value="name_asc">По имени (А-Я)</option>
                    <option value="money_desc">По деньгам (баланс)</option>
                    <option value="gold_desc">По золоту (Gold)</option>
                    <option value="fame_desc">По очкам славы</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ===== Таблица игроков ===== --}}
    <div class="panel overflow-hidden bg-[#161b22]">
        <template x-if="onlineCount === 0">
            <div class="text-sm text-[#8b949e] text-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-[#21262d]/50 border border-[#30363d] flex items-center justify-center mx-auto mb-3 opacity-60"><x-icon name="server" class="w-8 h-8 text-[#58a6ff]" /></div>
                <div class="font-bold text-base text-[#e6edf3] mb-1">Сейчас на сервере нет игроков онлайн</div>
                <div class="text-xs text-[#8b949e] max-w-sm mx-auto">
                    Список обновляется автоматически при запросах к RCON.
                </div>
            </div>
        </template>

        <template x-if="onlineCount > 0">
            <div class="overflow-x-auto max-h-[720px] custom-scrollbar border border-[#21262d] rounded-2xl shadow-sm">
                <table class="table min-w-full">
                    <thead class="sticky top-0 z-10 bg-[#0c1017]">
                        <tr class="bg-[#0c1017] border-b border-[#21262d]">
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e]">Игрок</th>
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e]">Steam ID</th>
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e] text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-[#3fb950]" /> Деньги</span></th>
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e] text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922]" /> Золото</span></th>
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e] text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="trophy" class="w-3.5 h-3.5 text-[#bc4c9a]" /> Слава</span></th>
                            <th class="py-3 px-4 font-semibold text-[11px] uppercase tracking-wider text-[#8b949e]"><span class="inline-flex items-center gap-1.5"><x-icon name="map" class="w-3.5 h-3.5 text-[#58a6ff]" /> Координаты (X, Y, Z)</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]">
                        <template x-for="p in filteredPlayers" :key="p.steamId">
                            <tr class="group hover:bg-[#1c2128] transition-colors">
                                {{-- Игрок --}}
                                <td class="py-3 px-4">
                                    <a :href="p.profileUrl" class="flex items-center gap-3 min-w-0 group/link" :title="'Профиль игрока ' + p.name">
                                        <div class="relative shrink-0">
                                            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb]/25 to-[#bc4c9a]/20 border border-[#388bfd]/30 flex items-center justify-center text-xs font-bold text-[#58a6ff] transition-transform group-hover:scale-105 shadow-inner">
                                                <span x-text="p.name.substring(0, 2).toUpperCase()"></span>
                                            </span>
                                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-[#3fb950] border-2 border-[#161b22]" title="Онлайн"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="font-bold text-[#e6edf3] group-hover/link:text-[#58a6ff] transition-colors truncate block text-sm" x-text="p.name"></span>
                                            <span class="text-[11px] text-[#8b949e] flex items-center gap-1 mt-0.5">
                                                <span class="text-[#3fb950] flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#3fb950]"></span><span>онлайн</span></span>
                                                <span class="text-[#484f58]">·</span>
                                                <span class="group-hover/link:underline text-[#58a6ff]">профиль</span>
                                            </span>
                                        </div>
                                    </a>
                                </td>

                                {{-- Steam ID --}}
                                <td class="py-3 px-4">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#0d1117] border border-[#30363d] mono text-xs text-[#c9d1d9] whitespace-nowrap group-hover:border-[#484f58] transition-colors">
                                        <span x-text="p.steamId"></span>
                                        <button type="button" @click="copyToClipboard(p.steamId, $el)" class="text-[#8b949e] hover:text-[#58a6ff] transition-colors p-0.5 cursor-pointer" title="Скопировать Steam ID"><x-icon name="copy" class="w-3 h-3" /></button>
                                    </div>
                                </td>

                                {{-- Деньги --}}
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center gap-1 font-bold text-[#3fb950] tabular-nums whitespace-nowrap bg-[#238636]/10 px-2.5 py-1 rounded-md border border-[#238636]/20 text-xs">
                                        <span x-text="new Intl.NumberFormat('ru-RU').format(p.money)"></span>
                                        
                                    </span>
                                </td>

                                {{-- Золото --}}
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center gap-1 font-bold text-[#d29922] tabular-nums whitespace-nowrap bg-[#d29922]/10 px-2.5 py-1 rounded-md border border-[#d29922]/20 text-xs">
                                        <span x-text="new Intl.NumberFormat('ru-RU').format(p.gold)"></span>
                                        
                                    </span>
                                </td>

                                {{-- Слава --}}
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center gap-1 font-bold text-[#bc4c9a] tabular-nums whitespace-nowrap bg-[#bc4c9a]/10 px-2.5 py-1 rounded-md border border-[#bc4c9a]/20 text-xs">
                                        <span x-text="new Intl.NumberFormat('ru-RU').format(p.fame)"></span>
                                        
                                    </span>
                                </td>

                                {{-- Локация --}}
                                <td class="py-3 px-4">
                                    <template x-if="p.x !== null">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#0d1117] border border-[#30363d] mono text-xs text-[#58a6ff] whitespace-nowrap group-hover:border-[#484f58] transition-colors">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950]"></span>
                                            <span x-text="p.x + ', ' + p.y + ', ' + p.z"></span>
                                            <button type="button" @click="copyToClipboard(p.x + ' ' + p.y + ' ' + p.z, $el)" class="text-[#8b949e] hover:text-[#e6edf3] ml-0.5 cursor-pointer" title="Скопировать координаты"><x-icon name="copy" class="w-3 h-3" /></button>
                                            <a :href="'/map?x=' + p.x + '&y=' + p.y + '&steam_id=' + encodeURIComponent(p.steamId) + '&name=' + encodeURIComponent(p.name)" class="text-[#8b949e] hover:text-[#3fb950] ml-0.5 cursor-pointer" title="Показать на интерактивной карте"><x-icon name="map" class="w-3 h-3" /></a>
                                        </div>
                                    </template>
                                    <template x-if="p.x === null">
                                        <span class="text-[#8b949e] text-xs">—</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
        </template>

        {{-- Сообщение если ничего не найдено поиском --}}
        <div x-show="onlineCount > 0 && filteredPlayers.length === 0" class="text-center py-10 text-sm text-[#8b949e]" x-cloak>
            <div class="w-10 h-10 mx-auto rounded-xl bg-[#21262d] flex items-center justify-center text-[#8b949e] mb-2"><x-icon name="search" class="w-5 h-5" /></div>
            По вашему запросу «<span class="text-[#e6edf3]" x-text="search"></span>» ничего не найдено.
        </div>
    </div>

</div>
@endsection
