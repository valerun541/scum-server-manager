@extends('layouts.app')

@section('title', 'Интерактивная карта')

@section('header-actions')
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.location.reload()" class="btn btn-sm flex items-center gap-1.5">
            <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
            <span>Обновить</span>
        </button>
    </div>
@endsection

@section('content')

@php
        $span = $maxX - $minX;
        // Секторы 5x5 (как в ScumMapService)
        $rowLabels = ['D', 'C', 'B', 'A', 'Z'];
        $colLabels = ['4', '3', '2', '1', '0'];
        $step = $mapSize / 5;
        $pixel = function ($x, $y) use ($minX, $minY, $maxX, $maxY, $span, $mapSize) {
            return [
                'x' => $mapSize - (($x - $minX) / $span) * $mapSize,
                'y' => (($maxY - $y) / $span) * $mapSize,
            ];
        };
        $sectorOf = function ($px, $py) use ($rowLabels, $colLabels, $step) {
            $ci = max(0, min(4, (int)floor($px / $step)));
            $ri = max(0, min(4, (int)floor($py / $step)));
            return $rowLabels[$ri] . $colLabels[$ci];
        };
        $humanize = function ($s) {
            $s = (string)$s;
            $s = preg_replace('/_C$/', '', $s);
            return trim(preg_replace('/[_\s]+/', ' ', $s));
        };
        $fmt = function ($n) { return number_format((float)$n, 0, '.', ' '); };
        $scale = $mapSize / $span; // единиц мира в одном «пикселе» карты

        $brokenCount = 0; $forbiddenCount = 0;
        foreach ($vehicles as $v) {
            if (($v['functional'] ?? true) === false) $brokenCount++;
            if ((int)($v['forbiddenZoneSeconds'] ?? 0) > 0) $forbiddenCount++;
        }
        $contestedCount = 0;
        foreach ($flags as $f) {
            $ot = (int)($f['overtakeEndTime'] ?? 0);
            if ($ot > 0 && $ot > time()) $contestedCount++;
        }
        $chestsCount = count($chests['chests'] ?? []);
        $takeoverCount = count($chests['takeovers'] ?? []);
    @endphp

    <style>
        #map-svg { cursor: grab; touch-action: none; }
        #map-svg.panning { cursor: grabbing; }
        #map-svg .marker { cursor: pointer; }
        #map-svg .marker .hit { pointer-events: all; }
        #map-svg .marker .glyph { transition: transform .12s ease; transform-origin: 0px 0px; }
        #map-svg .marker:hover .glyph { transform: scale(1.18); }
        #map-svg .marker:hover .glow { fill-opacity: .22; }
        #map-svg .label {
            fill: #e6edf3; stroke: #0d1117; stroke-width: 5; paint-order: stroke;
            font-size: 27px; font-family: 'Consolas', monospace; font-weight: 600;
            opacity: 0; transition: opacity .15s ease; pointer-events: none;
        }
        #map-svg.show-labels .marker .label,
        #map-svg .marker:hover .label { opacity: 1; }
        #map-svg .marker .label.label-hidden { display: none; }
        @keyframes mapPulse { 0%,100% { opacity: .2; transform: scale(1); } 50% { opacity: .7; transform: scale(1.14); } }
        #map-svg .pulse-ring { animation: mapPulse 2s ease-in-out infinite; transform-origin: 0px 0px; }
        #map-svg .marker-contested .flag-main { animation: mapWave 1s ease-in-out infinite; }
        #map-svg .marker-contested .flag-extra { animation: mapWave 1s ease-in-out .15s infinite; }
        @keyframes mapWave { 0%,100% { transform: skewY(0deg); } 50% { transform: skewY(-7deg); } }
        .map-hud-btn {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            background: rgba(13, 18, 28, 0.88); backdrop-filter: blur(8px);
            border: 1px solid rgba(51, 65, 85, 0.7); border-radius: 12px;
            color: #e6edf3; font-size: 16px; line-height: 1; cursor: pointer;
            transition: all .15s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
        }
        .map-hud-btn:hover { background: rgba(31, 111, 235, 0.25); border-color: rgba(88, 166, 255, 0.6); color: #58a6ff; transform: translateY(-1px); }
        .map-hud-btn:active { transform: translateY(1px); }
        #map-tooltip { background-color: #0c1017 !important; }
        #map-tooltip .coord-copy { cursor: copy !important; }
        .map-canvas {
            overscroll-behavior: contain;
            height: 72vh;
            min-height: 520px;
            background-color: #070b12;
            background-image: radial-gradient(rgba(56, 139, 253, 0.08) 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }
        @media (min-width: 768px) {
            .map-canvas {
                height: calc(100vh - 240px);
                height: calc(100dvh - 240px);
                min-height: 620px;
                max-height: 1050px;
            }
        }
        @media (pointer: coarse) {
            #map-svg .marker .hit { r: 130px; }
            .map-hud-btn { width: 44px; height: 44px; font-size: 18px; }
        }
    </style>

    @if (!$rconConnected && !$available)
        <div class="mb-5 p-4 rounded-2xl border border-amber-500/40 bg-amber-500/10 text-amber-300 text-xs sm:text-sm flex items-center gap-3 shadow-lg">
            <x-icon name="alert-triangle" class="w-5 h-5 text-amber-400 shrink-0" />
            <div>
                <span class="font-bold">Внимание:</span> RCON не подключён и SCUM.db недоступен — карта пуста. Запустите демоны <code class="mono px-1.5 py-0.5 rounded bg-black/40 text-amber-200">scum:rcon</code> / <code class="mono px-1.5 py-0.5 rounded bg-black/40 text-amber-200">scum:watch</code>.
            </div>
        </div>
    @endif

    <div class="space-y-6">
        {{-- Enterprise Hero Header --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-5 sm:p-6 shadow-2xl">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl pointer-events-none"></div>
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 text-emerald-400 shadow-inner">
                        <x-icon name="map" class="h-7 w-7" />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-100">Интерактивная карта острова SCUM</h1>
                            @if ($rconConnected)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-400">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    RCON Live Radar
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-400">
                                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                    SCUM.db Snapshot
                                </span>
                            @endif
                        </div>
                        <p class="text-xs sm:text-sm text-slate-400 mt-1">
                            Тактический спутниковый обзор в реальном времени: дислокация игроков, статус техники, форпосты и обнаруженные схроны
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button type="button" onclick="window.location.reload()"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/80 hover:bg-slate-700/80 px-3.5 py-2 text-xs font-semibold text-slate-200 transition-all hover:border-slate-600 shadow-sm cursor-pointer">
                        <x-icon name="refresh-cw" class="w-4 h-4 text-slate-400" />
                        <span>Обновить данные</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Статистика — 4 информационных бейджа со счётчиками и live-poll --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4"
             x-data="{
                 stats: {
                     players: {{ count($players) }},
                     vehicles: {{ count($vehicles) }},
                     broken: {{ $brokenCount }},
                     forbidden: {{ $forbiddenCount }},
                     flags: {{ count($flags) }},
                     contested: {{ $contestedCount }},
                     chests: {{ $chestsCount }},
                     takeover: {{ $takeoverCount }},
                     rcon: {{ $rconConnected ? 'true' : 'false' }},
                     available: {{ $available ? 'true' : 'false' }}
                 },
                 t: null,
                 init() {
                     this.t = setInterval(async () => {
                         try {
                             const r = await fetch('{{ route('map.stats') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
                             if (!r.ok) return;
                             const j = await r.json();
                             this.stats = { ...this.stats, ...j };
                         } catch(e) {}
                      }, {{ (int) config('panel.map.stats_poll_ms', 30000) }});
                 },
                 destroy() { if(this.t) clearInterval(this.t); }
             }" x-init="init()" x-destroy="destroy()">

            {{-- Карточка 1: Игроки онлайн --}}
            <div class="rounded-2xl border border-slate-800 bg-[#0d121c]/90 hover:border-blue-500/40 backdrop-blur-md p-4 sm:p-5 flex items-center justify-between gap-3 shadow-lg transition-all group">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500/20 to-sky-500/10 border border-blue-500/30 text-blue-400 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform">
                        <x-icon name="users" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold truncate">Игроки онлайн</div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-100 leading-tight" x-text="stats.players">{{ count($players) }}</div>
                        <div class="text-[10px] text-slate-500 font-medium">на острове</div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                    <span class="w-3 h-3 rounded-full shrink-0 shadow-sm"
                          :class="stats.rcon ? 'bg-emerald-400 animate-pulse shadow-emerald-500/50' : 'bg-rose-500 shadow-rose-500/50'"
                          :title="stats.rcon ? 'RCON шлюз активен' : 'RCON недоступен'"></span>
                    <span class="text-[10px] font-mono text-slate-500" x-text="stats.rcon ? 'Live' : 'DB'">Live</span>
                </div>
            </div>

            {{-- Карточка 2: Транспорт --}}
            <div class="rounded-2xl border border-slate-800 bg-[#0d121c]/90 hover:border-amber-500/40 backdrop-blur-md p-4 sm:p-5 flex items-center justify-between gap-3 shadow-lg transition-all group">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform">
                        <x-icon name="car" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold truncate">Транспорт</div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-100 leading-tight" x-text="stats.vehicles">{{ count($vehicles) }}</div>
                        <div class="text-[10px] text-slate-500 font-medium">в игровом мире</div>
                    </div>
                </div>
                <div class="flex flex-col gap-1 items-end shrink-0" x-show="stats.broken > 0 || stats.forbidden > 0" x-cloak>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 border border-rose-500/30 text-rose-400" x-show="stats.broken > 0" x-text="stats.broken + ' слом.'">{{ $brokenCount }} слом.</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 border border-amber-500/30 text-amber-400" x-show="stats.forbidden > 0" x-text="stats.forbidden + ' в ЗЗ'">{{ $forbiddenCount }} в ЗЗ</span>
                </div>
            </div>

            {{-- Карточка 3: Флаги и базы --}}
            <div class="rounded-2xl border border-slate-800 bg-[#0d121c]/90 hover:border-purple-500/40 backdrop-blur-md p-4 sm:p-5 flex items-center justify-between gap-3 shadow-lg transition-all group">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-500/20 to-fuchsia-500/10 border border-purple-500/30 text-purple-400 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform">
                        <x-icon name="flag" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold truncate">Флаги и базы</div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-100 leading-tight" x-text="stats.flags">{{ count($flags) }}</div>
                        <div class="text-[10px] text-slate-500 font-medium">форпостов</div>
                    </div>
                </div>
                <div class="shrink-0">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 border border-amber-500/30 text-amber-400" x-show="stats.contested > 0" x-text="stats.contested + ' оспар.'" x-cloak>{{ $contestedCount }} оспар.</span>
                </div>
            </div>

            {{-- Карточка 4: Сундуки и схроны --}}
            <div class="rounded-2xl border border-slate-800 bg-[#0d121c]/90 hover:border-orange-500/40 backdrop-blur-md p-4 sm:p-5 flex items-center justify-between gap-3 shadow-lg transition-all group">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-orange-500/20 to-rose-500/10 border border-orange-500/30 text-orange-400 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform">
                        <x-icon name="box" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold truncate">Сундуки и схроны</div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-100 leading-tight" x-text="stats.chests">{{ $chestsCount }}</div>
                        <div class="text-[10px] text-slate-500 font-medium">в базе SCUM.db</div>
                    </div>
                </div>
                <div class="shrink-0">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 border border-rose-500/30 text-rose-400" x-show="stats.takeover > 0" x-text="stats.takeover + ' перехв.'" x-cloak>{{ $takeoverCount }} перехв.</span>
                </div>
            </div>
        </div>

        {{-- Основной блок карты (Alpine mapView) --}}
        <div x-ref="mapContainer"
             :class="fullscreen ? '!fixed !inset-0 !z-50 !rounded-none !m-0 !w-screen !h-screen flex flex-col bg-[#080c14]' : 'rounded-2xl border border-slate-800 bg-[#0d121c]/95 shadow-2xl overflow-hidden transition-all'"
             x-data="mapView({ size: {{ $mapSize }}, minX: {{ $minX }}, maxX: {{ $maxX }}, minY: {{ $minY }}, maxY: {{ $maxY }}, span: {{ $span }}, imageUrl: '{{ route('map.image') }}' })">

            {{-- Панель управления и фильтры слоёв --}}
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3.5 px-4 sm:px-6 py-4 bg-[#090d14] border-b border-slate-800 shrink-0">
                <div class="flex items-center justify-between gap-3 min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center shrink-0 text-emerald-400 shadow-inner">
                            <x-icon name="map" class="w-4 h-4" />
                        </span>
                        <div>
                            <div class="text-sm font-black text-slate-100 leading-tight flex items-center gap-2">
                                <span>Спутниковая сетка острова</span>
                                @if (!$rconConnected)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 border border-amber-500/30 text-amber-400">
                                        Offline DB
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 border border-emerald-500/30 text-emerald-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        Live
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-400 hidden sm:block">Секторы D4…Z0 · Масштаб: колесо / щипок · Смещение: зажать кнопку мыши</div>
                        </div>
                    </div>
                </div>

                {{-- Интерактивные чипы слоёв, тема и полноэкранный режим --}}
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 text-xs">
                    {{-- Чип: Игроки --}}
                    <button type="button" @click="layers.players = !layers.players"
                            :class="layers.players ? 'bg-blue-500/15 border-blue-500/40 text-blue-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-blue-400" :class="layers.players ? 'animate-pulse' : 'opacity-40'"></span>
                        <x-icon name="users" class="w-3.5 h-3.5" />
                        <span>Игроки</span>
                    </button>

                    {{-- Чип: Транспорт --}}
                    <button type="button" @click="layers.vehicles = !layers.vehicles"
                            :class="layers.vehicles ? 'bg-amber-500/15 border-amber-500/40 text-amber-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-amber-400" :class="layers.vehicles ? '' : 'opacity-40'"></span>
                        <x-icon name="car" class="w-3.5 h-3.5" />
                        <span>Транспорт</span>
                    </button>

                    {{-- Чип: Флаги --}}
                    <button type="button" @click="layers.flags = !layers.flags"
                            :class="layers.flags ? 'bg-purple-500/15 border-purple-500/40 text-purple-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-purple-400" :class="layers.flags ? '' : 'opacity-40'"></span>
                        <x-icon name="flag" class="w-3.5 h-3.5" />
                        <span>Флаги</span>
                    </button>

                    {{-- Чип: Зоны --}}
                                        {{-- Чип: Локации и Бункеры --}}
                    <button type="button" @click="layers.pois = !layers.pois"
                            :class="layers.pois ? 'bg-rose-500/15 border-rose-500/40 text-rose-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-rose-400" :class="layers.pois ? '' : 'opacity-40'"></span>
                        <x-icon name="map-pin" class="w-3.5 h-3.5" />
                        <span>Локации ({{ count($markers ?? []) }})</span>
                    </button>

                    <button type="button" @click="layers.zones = !layers.zones"
                            :class="layers.zones ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-emerald-400" :class="layers.zones ? '' : 'opacity-40'"></span>
                        <x-icon name="shield" class="w-3.5 h-3.5" />
                        <span>Зоны</span>
                    </button>

                    {{-- Чип: Сундуки --}}
                    <button type="button" @click="layers.chests = !layers.chests"
                            :class="layers.chests ? 'bg-orange-500/15 border-orange-500/40 text-orange-400 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-orange-400" :class="layers.chests ? '' : 'opacity-40'"></span>
                        <x-icon name="box" class="w-3.5 h-3.5" />
                        <span>Сундуки</span>
                    </button>

                    {{-- Чип: Подписи --}}
                    <button type="button" @click="layers.labels = !layers.labels"
                            :class="layers.labels ? 'bg-slate-800 border-slate-600 text-slate-200 shadow-sm' : 'bg-slate-900/60 border-slate-800 text-slate-500 hover:text-slate-300'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer"
                            title="Показывать названия и ники на карте">
                        <x-icon name="tag" class="w-3.5 h-3.5" />
                        <span>Подписи</span>
                    </button>

                    <div class="h-4 w-px bg-slate-800 hidden sm:block"></div>

                    {{-- Выбор темы --}}
                    <div class="flex items-center gap-1.5 bg-slate-900/80 border border-slate-800 px-3 py-1 rounded-xl">
                        <x-icon name="sliders" class="w-3.5 h-3.5 text-slate-400" />
                        <select x-model="theme" class="bg-transparent border-0 text-xs text-slate-200 focus:outline-none cursor-pointer pr-1">
                            <option value="islandmap" class="bg-slate-900 text-slate-200">Спутник (Цвет)</option>
                            <option value="islandmapgrey" class="bg-slate-900 text-slate-200">Монохром</option>
                            <option value="islandmaporange" class="bg-slate-900 text-slate-200">Тепловая</option>
                        </select>
                    </div>

                    {{-- Кнопка: На весь экран --}}
                    <button type="button" @click="toggleFullscreen()"
                            class="inline-flex items-center gap-1.5 bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-300 transition-all cursor-pointer"
                            :title="fullscreen ? 'Выйти из полноэкранного режима' : 'Развернуть карту на весь экран'">
                        <span x-show="!fullscreen"><x-icon name="maximize" class="w-3.5 h-3.5 text-blue-400" /></span>
                        <span x-show="fullscreen"><x-icon name="minimize" class="w-3.5 h-3.5 text-amber-400" /></span>
                        <span x-text="fullscreen ? 'Свернуть' : 'Экран'">Экран</span>
                    </button>
                </div>
            </div>

            {{-- Полотно карты и HUD контроллеры --}}
            <div :class="fullscreen ? 'flex-1 !h-full !max-h-none' : 'map-canvas'"
                 class="relative bg-[#06090e] select-none overflow-hidden">

<svg id="map-svg" x-ref="svg" :class="layers.labels ? 'show-labels' : ''"
                     class="w-full h-full block" viewBox="0 0 {{ $mapSize }} {{ $mapSize }}" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"
                     @pointerdown="panStart($event)" @pointermove="panMove($event)" @pointerup="panEnd($event)" @pointercancel="panEnd($event)"
                     @wheel.prevent="wheel($event)" @mousemove="move($event)" @mouseleave="clearCoords()" @click="svgClick($event)">

                    <defs>
                        <pattern id="ocean-tactical-grid" width="200" height="200" patternUnits="userSpaceOnUse">
                            <path d="M 200 0 L 0 0 0 200" fill="none" stroke="#162235" stroke-width="1.2"/>
                            <circle cx="0" cy="0" r="2.5" fill="#388bfd" opacity="0.35"/>
                        </pattern>
                    </defs>

                    <!-- Океанический тактический радар за пределами острова для широкоформатных дисплеев -->
                    <rect x="-16384" y="-16384" width="36864" height="36864" fill="#080c14"/>
                    <rect x="-16384" y="-16384" width="36864" height="36864" fill="url(#ocean-tactical-grid)"/>

                    <!-- Фон: реальная карта острова (4096x4096, координаты совпадают с сеткой) -->
                    <rect width="{{ $mapSize }}" height="{{ $mapSize }}" fill="#0e151e"/>
                    <image id="map-image" x-ref="mapImage" href="{{ route('map.image') }}" x="0" y="0" width="{{ $mapSize }}" height="{{ $mapSize }}" preserveAspectRatio="xMidYMid meet"/>

                    <!-- Сетка секторов -->
                    <g id="map-grid">
                        @for ($i = 1; $i < 5; $i++)
                            <line x1="{{ $i * $step }}" y1="0" x2="{{ $i * $step }}" y2="{{ $mapSize }}" stroke="#30363d" stroke-width="2" stroke-opacity="0.5"/>
                            <line x1="0" y1="{{ $i * $step }}" x2="{{ $mapSize }}" y2="{{ $i * $step }}" stroke="#30363d" stroke-width="2" stroke-opacity="0.5"/>
                        @endfor
                        @foreach ($rowLabels as $ri => $row)
                            @foreach ($colLabels as $ci => $col)
                                <text x="{{ ($ci + 0.5) * $step }}" y="{{ ($ri + 0.5) * $step }}"
                                      text-anchor="middle" dominant-baseline="middle"
                                      fill="#0d1117" stroke="#e6edf3" stroke-width="6" paint-order="stroke"
                                      font-size="150" font-family="'Consolas', monospace" font-weight="700"
                                      opacity="0.55">{{ $row . $col }}</text>
                            @endforeach
                        @endforeach
                        <text x="20" y="{{ $mapSize - 30 }}" fill="#0d1117" stroke="#8b949e" stroke-width="4" paint-order="stroke" font-size="34" font-family="'Consolas', monospace">X: {{ number_format($minX) }} … {{ number_format($maxX) }}</text>
                        <text x="20" y="{{ $mapSize - 70 }}" fill="#0d1117" stroke="#8b949e" stroke-width="4" paint-order="stroke" font-size="34" font-family="'Consolas', monospace">Y: {{ number_format($minY) }} … {{ number_format($maxY) }}</text>
                    </g>

                    <!-- Зоны (сейфзоны, трейдеры) — круги или прямоугольники -->
                    <g id="marker-zones" :style="layerStyle('zones')">
                        @foreach ($zones as $z)
                            @if (($z['x'] ?? null) === null || ($z['y'] ?? null) === null) @continue @endif
                            @php
                                $zp = $pixel((float)$z['x'], (float)$z['y']);
                                $zColor = $z['color'] ?? '#3f8a5a';
                                $zName = $z['name'] ?: 'Зона';
                                if ($z['shape'] === 'rect') {
                                    $hw = max(1.0, (float)$z['sizeX'] * $scale);
                                    $hh = max(1.0, (float)$z['sizeY'] * $scale);
                                } else {
                                    $zr = max(8.0, (float)$z['radius'] * $scale);
                                }
                            @endphp
                            <g class="zone" data-name="{{ $zName }}" data-color="{{ $zColor }}" data-info="{{ json_encode([
                                'name' => $zName,
                                'shape' => $z['shape'],
                                'sizeX' => (float)$z['sizeX'],
                                'sizeY' => (float)$z['sizeY'],
                                'radius' => (float)($z['radius'] ?? 0),
                                'x' => (int)round((float)$z['x']),
                                'y' => (int)round((float)$z['y']),
                            ], JSON_UNESCAPED_UNICODE) }}">
                                @if ($z['shape'] === 'rect')
                                    <rect x="{{ round($zp['x'] - $hw, 1) }}" y="{{ round($zp['y'] - $hh, 1) }}"
                                          width="{{ round($hw * 2, 1) }}" height="{{ round($hh * 2, 1) }}"
                                          fill="{{ $zColor }}" fill-opacity="0.16" stroke="{{ $zColor }}" stroke-width="3" stroke-dasharray="14 10"/>
                                @else
                                    <circle cx="{{ round($zp['x'], 1) }}" cy="{{ round($zp['y'], 1) }}" r="{{ round($zr, 1) }}"
                                            fill="{{ $zColor }}" fill-opacity="0.16" stroke="{{ $zColor }}" stroke-width="3" stroke-dasharray="14 10"/>
                                @endif
                                @if ($zName !== '')
                                    <text x="{{ round($zp['x'], 1) }}" y="{{ round($zp['y'] + 6, 1) }}" text-anchor="middle"
                                          fill="#0d1117" stroke="{{ $zColor }}" stroke-width="4" paint-order="stroke"
                                          font-size="34" font-family="'Consolas', monospace" font-weight="700"
                                          opacity="0.75">{{ $zName }}</text>
                                @endif
                            </g>
                        @endforeach
                    </g>

                                        <!-- Локации и Бункеры (POI) -->
                    <g id="marker-pois" :style="layerStyle('pois')">
                        @foreach ($markers ?? [] as $m)
                            @php
                                $mp = $pixel((float)$m['x'], (float)$m['y']);
                                $mType = $m['type'] ?? 'poi';
                                $mColor = match($mType) {
                                    'bunker', 'ww2' => '#f85149',
                                    'police' => '#58a6ff',
                                    'hospital' => '#f43f5e',
                                    'atm' => '#3fb950',
                                    'gas_station' => '#d29922',
                                    'cave' => '#a371f7',
                                    'outpost' => '#2ea043',
                                    default => '#8b949e',
                                };
                                $mInfo = json_encode([
                                    'sector' => $sectorOf($mp['x'], $mp['y']),
                                    'type' => $m['type_ru'] ?? $mType,
                                    'command' => $m['command'] ?? '',
                                    'x' => (int)round((float)$m['x']),
                                    'y' => (int)round((float)$m['y']),
                                    'z' => (int)round((float)$m['z']),
                                ], JSON_UNESCAPED_UNICODE);
                            @endphp
                            <g class="marker" data-kind="poi" data-name="{{ $m['name'] }}" data-info="{{ $mInfo }}"
                               transform="translate({{ round($mp['x'], 1) }}, {{ round($mp['y'], 1) }})">
                                <circle r="22" class="hit" fill="transparent"/>
                                <g class="glyph">
                                    <circle r="12" fill="{{ $mColor }}" fill-opacity="0.85" stroke="#0d1117" stroke-width="2.5"/>
                                    <circle r="4" fill="#ffffff"/>
                                </g>
                            </g>
                        @endforeach
                    </g>

                    <!-- Транспорт -->
                    <g id="marker-vehicles" :style="layerStyle('vehicles')">
                        @foreach ($vehicles as $v)
                            @if ($v['x'] === null || $v['y'] === null) @continue @endif
                            @php
                                $p = $pixel((float)$v['x'], (float)$v['y']);
                                $broken = (($v['functional'] ?? true) === false);
                                $forbidden = (int)($v['forbiddenZoneSeconds'] ?? 0) > 0;
                                $auto = (($v['automatic'] ?? false) == 1);
                                $color = $broken ? '#f85149' : ($forbidden ? '#f9924b' : '#d29922');
                                $state = $broken ? 'сломан' : ($forbidden ? 'в запретной зоне' : 'работает');
                                $asset = $v['asset'] ?? '';
                                $fullName = $v['alias'] ?: ($asset ?: 'Транспорт');
                                $label = $v['alias'] ?: $humanize($asset);
                                $info = json_encode([
                                    'sector' => $sectorOf($p['x'], $p['y']),
                                    'alias' => $v['alias'] ?: null,
                                    'entityId' => (int)($v['entityId'] ?? 0),
                                    'state' => $state,
                                    'health' => $broken ? 0 : 100,
                                    'auto' => (bool)$auto,
                                    'broken' => $broken,
                                    'forbidden' => (int)($v['forbiddenZoneSeconds'] ?? 0),
                                    'last' => $v['lastAccess'] ?? null,
                                    'x' => (int)round((float)$v['x']),
                                    'y' => (int)round((float)$v['y']),
                                    'z' => isset($v['z']) && $v['z'] !== null ? (int)round((float)$v['z']) : null,
                                ], JSON_UNESCAPED_UNICODE);
                            @endphp
                            <g class="marker marker-vehicle" data-kind="vehicle" data-name="{{ $fullName }}" data-info="{{ $info }}"
                               transform="translate({{ round($p['x'], 1) }} {{ round($p['y'], 1) }})">
                                <circle class="hit" r="70" fill="transparent"/>
                                <circle class="glow" r="32" fill="{{ $color }}" fill-opacity="0.13"/>
                                @if ($broken)
                                    <circle class="warn" r="24" fill="none" stroke="{{ $color }}" stroke-width="3" stroke-dasharray="9 9"/>
                                @endif
                                <g class="glyph">
                                    <rect x="-18" y="-23" width="36" height="18" rx="6" fill="{{ $color }}" stroke="#0d1117" stroke-width="3"/>
                                    <path d="M-11 -23 L-6 -31 L9 -31 L13 -23 Z" fill="#0d1117"/>
                                    <circle cx="-10" cy="-5" r="3.5" fill="#0d1117"/>
                                    <circle cx="10" cy="-5" r="3.5" fill="#0d1117"/>
                                </g>
                                @if ($label !== '')
                                    <text class="label" y="14" text-anchor="middle">{{ $label }}</text>
                                @endif
                            </g>
                        @endforeach
                    </g>

                    <!-- Флаги -->
                    <g id="marker-flags" :style="layerStyle('flags')">
                        @foreach ($flags as $f)
                            @php
                                $fx = $f['ex'] ?? $f['x'] ?? $f['bx'] ?? null;
                                $fy = $f['ey'] ?? $f['y'] ?? $f['by'] ?? null;
                                $fz = $f['ez'] ?? null;
                            @endphp
                            @if ($fx === null || $fy === null) @continue @endif
                            @php
                                $p = $pixel((float)$fx, (float)$fy);
                                $bname = $f['baseName'] ?: ('Флаг #' . ($f['elementId'] ?? ''));
                                $otEnd = (int)($f['overtakeEndTime'] ?? 0);
                                $overtaking = $otEnd > 0 && $otEnd > time();
                                $color = $overtaking ? '#f0883e' : '#a371f7';
                                $info = json_encode([
                                    'sector' => $sectorOf($p['x'], $p['y']),
                                    'owner' => $f['ownerName'] ?: '—',
                                    'ownerSteamId' => $f['ownerSteamId'] ?? null,
                                    'baseOwner' => $f['baseOwnerName'] ?? null,
                                    'baseOwnerSteamId' => $f['baseOwnerSteamId'] ?? null,
                                    'elementOwner' => $f['elementOwnerName'] ?? null,
                                    'elementOwnerSteamId' => $f['elementOwnerSteamId'] ?? null,
                                    'squadName' => $f['squadName'] ?? null,
                                    'isOwnedByPlayer' => (int)($f['isOwnedByPlayer'] ?? 1),
                                    'state' => $overtaking ? 'оспаривается' : 'установлен',
                                    'health' => (int)round(((float)($f['health'] ?? 1)) * 100),
                                    'elementId' => (int)($f['elementId'] ?? 0),
                                    'baseId' => (int)($f['baseId'] ?? 0),
                                    'overtaker' => $f['overtakerName'] ?: null,
                                    'overtakerSteamId' => $f['overtakerSteamId'] ?? null,
                                    'overtakeEnd' => $otEnd > 0 ? $otEnd : null,
                                    'x' => (int)round((float)$fx),
                                    'y' => (int)round((float)$fy),
                                    'z' => $fz !== null ? (int)round((float)$fz) : null,
                                ], JSON_UNESCAPED_UNICODE);
                            @endphp
                            <g class="marker marker-flag{{ $overtaking ? ' marker-contested' : '' }}" data-kind="flag" data-name="{{ $bname }}" data-info="{{ $info }}"
                               transform="translate({{ round($p['x'], 1) }} {{ round($p['y'], 1) }})">
                                <circle class="hit" r="70" fill="transparent"/>
                                <circle class="glow" r="28" fill="{{ $color }}" fill-opacity="0.12"/>
                                <g class="glyph">
                                    <rect x="-3.5" y="-46" width="7" height="48" rx="2" fill="#c9d1d9" stroke="#0d1117" stroke-width="2"/>
                                    <path class="flag-main" d="M-2 -46 L18 -39.5 L-2 -33 Z" fill="{{ $color }}" stroke="#0d1117" stroke-width="1.5"/>
                                    @if ($overtaking)
                                        <path class="flag-extra" d="M-2 -32 L18 -25.5 L-2 -19 Z" fill="#f85149" stroke="#0d1117" stroke-width="1.5"/>
                                    @endif
                                </g>
                                @if ($bname !== 'Флаг')
                                    <text class="label" y="6" text-anchor="middle">{{ $bname }}</text>
                                @endif
                            </g>
                        @endforeach
                    </g>

                    <!-- Сундуки (владельцы из chest_ownership*.log, позиции из SCUM.db) -->
                    <g id="marker-chests" :style="layerStyle('chests')">
                        @foreach ($chests['chests'] ?? [] as $ch)
                            @if (($ch['x'] ?? null) === null || ($ch['y'] ?? null) === null) @continue @endif
                            @php
                                $cp = $pixel((float)$ch['x'], (float)$ch['y']);
                                $taken = (bool)($ch['taken'] ?? false);
                                $chColor = $taken ? '#f85149' : '#f0883e';
                                $owner = $ch['ownerName'] ?: ($ch['ownerSteamId'] ?: 'бесхозный');
                                $chInfo = json_encode([
                                    'sector' => $sectorOf($cp['x'], $cp['y']),
                                    'entityId' => (int)($ch['entityId'] ?? 0),
                                    'owner' => $owner,
                                    'ownerSteamId' => $ch['ownerSteamId'] ?? null,
                                    'state' => $taken ? 'перехвачен' : 'установлен',
                                    'ownerChanges' => (int)($ch['ownerChanges'] ?? 0),
                                    'claimedAt' => $ch['claimedAt'] ?? null,
                                    'x' => (int)round((float)$ch['x']),
                                    'y' => (int)round((float)$ch['y']),
                                    'z' => isset($ch['z']) && $ch['z'] !== null ? (int)round((float)$ch['z']) : null,
                                ], JSON_UNESCAPED_UNICODE);
                                $chName = !empty($ch['entityClass']) ? $humanize($ch['entityClass']) : ('Сундук #' . ($ch['entityId'] ?? 0));
                            @endphp
                            <g class="marker marker-chest{{ $taken ? ' marker-chest-taken' : '' }}" data-kind="chest" data-name="{{ $chName }}" data-info="{{ $chInfo }}"
                               transform="translate({{ round($cp['x'], 1) }} {{ round($cp['y'], 1) }})">
                                <circle class="hit" r="70" fill="transparent"/>
                                <circle class="glow" r="26" fill="{{ $chColor }}" fill-opacity="0.14"/>
                                @if ($taken)
                                    <circle class="pulse-ring" r="26" fill="none" stroke="{{ $chColor }}" stroke-width="2.5"/>
                                @endif
                                <g class="glyph">
                                    <rect x="-16" y="-20" width="32" height="24" rx="3.5" fill="{{ $chColor }}" stroke="#0d1117" stroke-width="3"/>
                                    <rect x="-16" y="-12" width="32" height="7" rx="2" fill="#0d1117"/>
                                    <rect x="-8" y="-30" width="5" height="11" fill="#c9d1d9" stroke="#0d1117" stroke-width="1.5"/>
                                    <rect x="3" y="-30" width="5" height="11" fill="#c9d1d9" stroke="#0d1117" stroke-width="1.5"/>
                                </g>
                            </g>
                        @endforeach
                    </g>

                    <!-- Игроки -->
                    <g id="marker-players" :style="layerStyle('players')">
                        @foreach ($players as $cp)
                            @php
                                $loc = $cp['location'];
                                $pp = $pixel((float)$loc['x'], (float)$loc['y']);
                                $info = json_encode([
                                    'sector' => $sectorOf($pp['x'], $pp['y']),
                                    'steamId' => $cp['steamId'] ?? null,
                                    'money' => (float)($cp['money'] ?? 0),
                                    'gold' => (float)($cp['gold'] ?? 0),
                                    'fame' => (float)($cp['fame'] ?? 0),
                                    'lastLogin' => (int)($cp['lastLogin'] ?? 0),
                                    'x' => (int)round((float)$loc['x']),
                                    'y' => (int)round((float)$loc['y']),
                                    'z' => isset($loc['z']) && $loc['z'] !== null ? (int)round((float)$loc['z']) : null,
                                ], JSON_UNESCAPED_UNICODE);
                            @endphp
                            <g class="marker marker-player" data-kind="player" data-name="{{ $cp['name'] }}" data-info="{{ $info }}"
                               transform="translate({{ round($pp['x'], 1) }} {{ round($pp['y'], 1) }})">
                                <circle class="hit" r="75" fill="transparent"/>
                                <circle class="glow" r="34" fill="#58a6ff" fill-opacity="0.12"/>
                                <circle class="pulse-ring" r="34" fill="none" stroke="#58a6ff" stroke-width="2.5"/>
                                <g class="glyph">
                                    <path d="M0 -1 C-13 -16 -19 -25 -19 -37 a19 19 0 1 1 38 0 C19 -25 13 -16 0 -1 Z" fill="#58a6ff" stroke="#0d1117" stroke-width="3"/>
                                    <circle cx="0" cy="-43" r="6.5" fill="#0d1117"/>
                                    <path d="M-10 -32 A10 9 0 0 1 10 -32 Z" fill="#0d1117"/>
                                </g>
                                <text class="label" y="18" text-anchor="middle">{{ $cp['name'] }}</text>
                            </g>
                        @endforeach
                    </g>
                </svg>

                {{-- Floating HUD: Управление масштабом и центрированием --}}
                <div class="absolute top-4 right-4 z-20 flex flex-col gap-2">
                    <button type="button" class="map-hud-btn" :class="drawerOpen ? '!border-blue-400 !bg-blue-500/20 !text-blue-300 shadow-blue-500/20' : ''"
                            title="Тактический пульт (Секторы / Объекты / Поиск)" @click="toggleDrawer()">
                        <x-icon name="layers" class="w-4 h-4" />
                    </button>
                    <button type="button" class="map-hud-btn" title="Приблизить (+)" @click="zoomIn()">
                        <x-icon name="plus" class="w-4 h-4" />
                    </button>
                    <button type="button" class="map-hud-btn" title="Отдалить (−)" @click="zoomOut()">
                        <span class="text-xl font-bold leading-none select-none">−</span>
                    </button>
                    <button type="button" class="map-hud-btn" title="Сбросить масштаб и центрировать" @click="zoomReset()">
                        <x-icon name="compass" class="w-4 h-4 text-emerald-400" />
                    </button>
                    <button type="button" class="map-hud-btn" :title="fullscreen ? 'Свернуть экран' : 'На весь экран'" @click="toggleFullscreen()">
                        <span x-show="!fullscreen"><x-icon name="maximize" class="w-4 h-4 text-blue-400" /></span>
                        <span x-show="fullscreen"><x-icon name="minimize" class="w-4 h-4 text-amber-400" /></span>
                    </button>
                </div>

                {{-- Floating HUD: Тактический координатный радар (низ слева) --}}
                <div class="absolute bottom-4 left-4 z-20 flex items-center gap-2 max-w-[85%] sm:max-w-[65%]">
                    <div class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl border border-slate-700/80 bg-slate-900/90 backdrop-blur-md text-xs font-mono shadow-2xl text-slate-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                        <span class="text-[11px] uppercase tracking-wider text-slate-500 font-sans font-bold shrink-0">GPS:</span>
                        <span class="text-blue-400 font-semibold truncate" x-text="coords">—</span>
                    </div>
                </div>

                {{-- Floating HUD: Масштаб сетки (низ справа) --}}
                <div class="hidden sm:flex absolute bottom-4 right-4 z-20 items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-800/80 bg-slate-900/80 backdrop-blur-md text-[11px] font-mono text-slate-400 shadow-xl pointer-events-none">
                    <span>4096 × 4096 м</span>
                    <span class="text-slate-600">·</span>
                    <span>Сетка 5×5</span>
                </div>

                {{-- Всплывающая подсказка карточки объекта (Tooltip) --}}
                <div id="map-tooltip" x-ref="tip" x-show="tipVisible" x-cloak
                     class="pointer-events-auto absolute z-30 w-[320px] max-w-[94%] p-4 rounded-2xl border border-slate-700/90 bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl text-xs"
                     @mouseenter="tipEnter()" @mouseleave="tipLeave()" @click="onTipCopy($event)">
                    <div class="flex items-center justify-between gap-3 mb-2.5 pb-2.5 border-b border-slate-800">
                        <span class="font-bold text-slate-100 text-sm leading-tight truncate" x-text="tipName"></span>
                        <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 uppercase font-bold tracking-wider"
                              :class="tipKindCls" x-text="tipKind"></span>
                    </div>
                    <div class="space-y-1.5 text-slate-300" x-html="tipHtml"></div>
                    <div class="text-[10px] text-slate-500 mt-2.5 pt-2 border-t border-slate-800 font-mono flex items-center justify-between" x-text="tipFoot"></div>
                </div>

                {{-- ТАКТИЧЕСКИЙ ОПЕРАТИВНЫЙ ПУЛЬТ (Enterprise Tactical Operations Center) --}}
                <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                     class="absolute top-0 right-0 bottom-0 z-30 w-80 sm:w-96 bg-[#0a0f18]/95 backdrop-blur-xl border-l border-slate-800 shadow-2xl flex flex-col select-text"
                     x-cloak>
                    
                    {{-- Шапка пульта --}}
                    <div class="flex items-center justify-between gap-3 px-4 py-3.5 border-b border-slate-800 bg-[#0d131f]">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-blue-500/15 border border-blue-500/30 flex items-center justify-center text-blue-400 shrink-0">
                                <x-icon name="radar" class="w-4 h-4" />
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-wider text-slate-100 truncate">Тактический радар</div>
                                <div class="text-[10px] text-slate-400 font-mono">SCUM GIS Operations</div>
                            </div>
                        </div>
                        <button type="button" @click="toggleDrawer()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>

                    {{-- Навигация по вкладкам пульта --}}
                    <div class="flex items-center border-b border-slate-800 bg-[#080d14] text-[11px] font-semibold text-slate-400">
                        <button type="button" @click="drawerTab = 'sectors'"
                                :class="drawerTab === 'sectors' ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-500/5' : 'hover:text-slate-200'"
                                class="flex-1 py-2.5 text-center transition-colors">Секторы</button>
                        <button type="button" @click="drawerTab = 'players'"
                                :class="drawerTab === 'players' ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-500/5' : 'hover:text-slate-200'"
                                class="flex-1 py-2.5 text-center transition-colors">Игроки ({{ count($players) }})</button>
                        <button type="button" @click="drawerTab = 'vehicles'"
                                :class="drawerTab === 'vehicles' ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-500/5' : 'hover:text-slate-200'"
                                class="flex-1 py-2.5 text-center transition-colors">Техника ({{ count($vehicles) }})</button>
                        <button type="button" @click="drawerTab = 'flags'"
                                :class="drawerTab === 'flags' ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-500/5' : 'hover:text-slate-200'"
                                class="flex-1 py-2.5 text-center transition-colors">Базы ({{ count($flags) }})</button>
                        <button type="button" @click="drawerTab = 'chests'"
                                :class="drawerTab === 'chests' ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-500/5' : 'hover:text-slate-200'"
                                class="flex-1 py-2.5 text-center transition-colors">Схроны</button>
                    </div>

                    {{-- Тело вкладок --}}
                    <div class="flex-1 overflow-y-auto p-4 custom-scrollbar space-y-3">
                        
                        {{-- 1. СЕКТОРЫ (5x5 Tactical Matrix) --}}
                        <div x-show="drawerTab === 'sectors'" class="space-y-3">
                            <div class="text-[11px] text-slate-400">Нажмите на любой сектор для плавного фокусирования спутника:</div>
                            <div class="grid grid-cols-5 gap-1.5 font-mono text-xs font-bold">
                                @foreach ($rowLabels as $r)
                                    @foreach ($colLabels as $c)
                                        @php $sec = $r . $c; @endphp
                                        <button type="button" @click="flyToSector('{{ $sec }}')"
                                                class="py-2.5 rounded-lg border border-slate-800 bg-[#0c121d] hover:bg-blue-600/20 hover:border-blue-500/50 hover:text-blue-300 text-slate-300 text-center transition-all shadow-sm">
                                            {{ $sec }}
                                        </button>
                                    @endforeach
                                @endforeach
                            </div>
                            <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Обзор острова:</span>
                                <button type="button" @click="zoomReset()" class="text-blue-400 hover:text-blue-300 font-semibold">Центрировать остров</button>
                            </div>
                        </div>

                        {{-- 2. ИГРОКИ ОНЛАЙН --}}
                        <div x-show="drawerTab === 'players'" class="space-y-2">
                            <input type="text" x-model="drawerSearch" placeholder="Поиск игрока / SteamID..."
                                   class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-900 border border-slate-800 text-slate-200 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
                            <div class="space-y-1.5">
                                @forelse ($players as $p)
                                    <div class="p-2.5 rounded-xl border border-slate-800 bg-[#0d121c] hover:border-blue-500/40 transition-all flex items-center justify-between gap-2"
                                         x-show="!drawerSearch || '{{ strtolower($p['name']) }}'.includes(drawerSearch.toLowerCase()) || '{{ $p['steamId'] ?? '' }}'.includes(drawerSearch)">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-slate-100 truncate">{{ $p['name'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $p['steamId'] ?? '—' }}</div>
                                        </div>
                                        <button type="button" @click="flyToEntity('player', '{{ $p['name'] }}')"
                                                class="px-2.5 py-1 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 hover:bg-blue-500/25 text-[11px] font-semibold shrink-0">
                                            Навести
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-500 text-center py-6">Нет игроков на сервере в данный момент</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 3. ТРАНСПОРТ --}}
                        <div x-show="drawerTab === 'vehicles'" class="space-y-2">
                            <input type="text" x-model="drawerSearch" placeholder="Поиск по ID или модели..."
                                   class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-900 border border-slate-800 text-slate-200 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
                            <div class="space-y-1.5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                @forelse ($vehicles as $v)
                                    @php
                                        $broken = (($v['functional'] ?? true) === false);
                                        $forbid = (int)($v['forbiddenZoneSeconds'] ?? 0) > 0;
                                        $vTitle = $v['alias'] ?: $humanize($v['asset'] ?? 'Транспорт');
                                    @endphp
                                    <div class="p-2.5 rounded-xl border border-slate-800 bg-[#0d121c] hover:border-amber-500/40 transition-all flex items-center justify-between gap-2"
                                         x-show="!drawerSearch || '{{ strtolower($vTitle) }}'.includes(drawerSearch.toLowerCase()) || '{{ $v['entityId'] ?? '' }}'.includes(drawerSearch)">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-slate-100 truncate flex items-center gap-1.5">
                                                <span>{{ $vTitle }}</span>
                                                @if ($broken)
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                @elseif ($forbid)
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-slate-400 font-mono">ID: #{{ $v['entityId'] ?? '—' }} · {{ $broken ? 'Сломан' : ($forbid ? 'В ЗЗ' : 'Исправен') }}</div>
                                        </div>
                                        <button type="button" @click="flyToEntity('vehicle', '{{ $v['entityId'] ?? '' }}')"
                                                class="px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 hover:bg-amber-500/25 text-[11px] font-semibold shrink-0">
                                            Навести
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-500 text-center py-6">Транспорт не обнаружен</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 4. ФЛАГИ И БАЗЫ --}}
                        <div x-show="drawerTab === 'flags'" class="space-y-2">
                            <input type="text" x-model="drawerSearch" placeholder="Поиск владельца базы..."
                                   class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-900 border border-slate-800 text-slate-200 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
                            <div class="space-y-1.5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                @forelse ($flags as $f)
                                    @php
                                        $fTitle = $f['baseName'] ?: ('Флаг #' . ($f['elementId'] ?? ''));
                                        $fOwner = $f['ownerName'] ?: ($f['squadName'] ?: 'Бесхозный');
                                        $otEnd = (int)($f['overtakeEndTime'] ?? 0);
                                        $isCont = $otEnd > 0 && $otEnd > time();
                                    @endphp
                                    <div class="p-2.5 rounded-xl border border-slate-800 bg-[#0d121c] hover:border-purple-500/40 transition-all flex items-center justify-between gap-2"
                                         x-show="!drawerSearch || '{{ strtolower($fTitle) }}'.includes(drawerSearch.toLowerCase()) || '{{ strtolower($fOwner) }}'.includes(drawerSearch.toLowerCase())">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-slate-100 truncate flex items-center gap-1.5">
                                                <span>{{ $fTitle }}</span>
                                                @if ($isCont)
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-500/20 text-amber-400 border border-amber-500/40 font-bold">Оспаривается</span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-slate-400 truncate">Владелец: {{ $fOwner }}</div>
                                        </div>
                                        <button type="button" @click="flyToEntity('flag', '{{ $fTitle }}')"
                                                class="px-2.5 py-1 rounded-lg bg-purple-500/15 border border-purple-500/30 text-purple-400 hover:bg-purple-500/25 text-[11px] font-semibold shrink-0">
                                            Навести
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-500 text-center py-6">Флаги не найдены в базе</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 5. СУНДУКИ И СХРОНЫ --}}
                        <div x-show="drawerTab === 'chests'" class="space-y-2">
                            <input type="text" x-model="drawerSearch" placeholder="Поиск сундука..."
                                   class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-900 border border-slate-800 text-slate-200 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
                            <div class="space-y-1.5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                @forelse ($chests['chests'] ?? [] as $ch)
                                    @php
                                        $chTitle = 'Сундук #' . ($ch['entityId'] ?? '');
                                        $chOwner = $ch['ownerName'] ?: ($ch['ownerSteamId'] ?: 'Неизвестен');
                                    @endphp
                                    <div class="p-2.5 rounded-xl border border-slate-800 bg-[#0d121c] hover:border-orange-500/40 transition-all flex items-center justify-between gap-2"
                                         x-show="!drawerSearch || '{{ strtolower($chTitle) }}'.includes(drawerSearch.toLowerCase()) || '{{ strtolower($chOwner) }}'.includes(drawerSearch.toLowerCase()) || '{{ $ch['entityId'] ?? '' }}'.includes(drawerSearch)">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-slate-100 truncate">{{ $chTitle }}</div>
                                            <div class="text-[10px] text-slate-400 truncate">{{ $chOwner }}</div>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button" @click="flyToEntity('chest', '{{ $ch['entityId'] ?? '' }}')"
                                                    class="px-2 py-1 rounded-lg bg-orange-500/15 border border-orange-500/30 text-orange-400 hover:bg-orange-500/25 text-[11px] font-semibold">
                                                Навести
                                            </button>
                                            <button type="button" @click="openChest('{{ $ch['entityId'] ?? '' }}')"
                                                    class="px-2 py-1 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-[11px]">
                                                Лут
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-500 text-center py-6">Сундуки не зарегистрированы</div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>

                
                {{-- Модальное окно: Содержимое сундука --}}
                <div x-show="chestOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4"
                     @click.self="closeChest()" @keydown.escape.window="closeChest()">
                    <div class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl border border-slate-800 bg-[#0c1017] shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-slate-800 bg-[#090d14]">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-xl bg-orange-500/15 border border-orange-500/30 flex items-center justify-center shrink-0 text-orange-400 shadow-inner">
                                    <x-icon name="box" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-100 text-base leading-tight truncate" x-text="chestTitle"></div>
                                    <div class="text-[11px] text-slate-400 font-mono truncate" x-text="chestClass"></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm shrink-0" @click="closeChest()">
                                <x-icon name="x" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                            <div x-show="chestLoading" class="text-sm text-slate-400 py-12 text-center flex items-center justify-center gap-2">
                                <x-icon name="refresh-cw" class="w-4 h-4 animate-spin text-blue-400" />
                                <span>Загрузка содержимого хранилища…</span>
                            </div>
                            <div x-show="!chestLoading && chestError" class="text-sm text-rose-400 font-mono py-12 text-center" x-text="chestError"></div>
                            <div x-show="!chestLoading && !chestError && chestItems.length === 0" class="text-sm text-slate-400 py-12 text-center">
                                Сундук пуст.
                            </div>
                            <div x-show="!chestLoading && !chestError && chestItems.length > 0">
                                <div class="flex items-center justify-between gap-2 mb-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 border border-slate-700 text-slate-300" x-text="chestItems.length + ' предметов'"></span>
                                    <span class="inline-flex items-center gap-1.5 text-xs text-amber-400/90 font-medium">
                                        <x-icon name="info" class="w-3.5 h-3.5 text-amber-400" />
                                        <span>Снимок SCUM.db</span>
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <template x-for="item in chestItems" :key="item.entityId">
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3.5 hover:border-slate-700 transition-colors">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="font-mono text-xs font-semibold text-slate-200 break-all" x-text="item.itemId || item.itemClass"></div>
                                                <span x-show="item.hasInventory" class="badge badge-blue shrink-0">
                                                    <x-icon name="box" class="w-3 h-3" />
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                                <span x-text="item.depth > 0 ? 'внутри контейнера' : 'в сундуке'"></span>
                                                <span x-show="item.count !== null" class="font-bold text-slate-300" x-text="'· ×' + item.count"></span>
                                            </div>
                                            <div x-show="item.components && item.components.length" class="mt-2.5 flex flex-wrap gap-1.5 border-t border-slate-800 pt-2.5">
                                                <template x-for="c in item.components" :key="c.name">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-medium bg-blue-500/10 border border-blue-500/20 text-blue-300" x-text="c.name + '=' + c.value"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-slate-800 bg-[#090d14]">
                            <div class="text-xs text-slate-400 font-mono" x-show="chestCoords"
                                 x-text="'X ' + chestCoords.x + '  Y ' + chestCoords.y + (chestCoords.z !== null ? '  Z ' + chestCoords.z : '')"></div>
                            <button type="button" class="btn ml-auto" @click="closeChest()">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
