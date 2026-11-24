@extends('layouts.app')

@section('title', 'SCUM.db — База Данных Сервера')

@section('content')
    @include('scumdb._nav', ['active' => 'index'])

    {{-- ========================================================================= --}}
    {{-- HERO BANNER                                                               --}}
    {{-- ========================================================================= --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#010409] p-5 sm:p-7 md:p-8 mb-6 shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#2ea043]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-20 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#2ea043]/25 to-[#1f6feb]/25 border border-[#2ea043]/40 flex items-center justify-center shadow-lg shadow-[#2ea043]/10 shrink-0"><x-icon name="database" class="w-7 h-7 text-[#58a6ff]" /></div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h1 class="text-2xl sm:text-3xl font-black text-[#e6edf3] tracking-tight">SCUM.db</h1>
                        <x-ui.badge :variant="$available ? 'green' : 'red'" dot>
                            {{ $available ? 'База подключена' : 'Файл не найден' }}
                        </x-ui.badge>
                        @if ($hasPending)
                            <x-ui.badge variant="yellow">
                                <x-icon name="clock" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /> Есть локальные правки
                            </x-ui.badge>
                        @endif
                    </div>
                    <p class="text-xs sm:text-sm text-[#8b949e] max-w-2xl leading-relaxed">
                        Прямой доступ к локальному дампу SQLite базы игрового мира SCUM. Просмотр профилей, инвентарей, транспорта, баз и автоматическая синхронизация по FTP.
                    </p>
                </div>
            </div>

            @if ($ftpEnabled)
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <form method="POST" action="{{ route('scumdb.upload') }}" onsubmit="return confirm('Выгрузить локальную SCUM.db на игровой сервер?')">
                        @csrf
                        <button type="submit" class="btn btn-primary flex items-center gap-2 shadow-md shadow-[#238636]/20">
                            <x-icon name="upload" class="w-4 h-4" />
                            <span>Выгрузить на сервер</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('scumdb.sync') }}" onsubmit="return confirm('Скачать свежую SCUM.db с сервера? Локальная копия будет обновлена.')">
                        @csrf
                        <button type="submit" class="btn btn-accent flex items-center gap-2 shadow-md shadow-[#1f6feb]/20">
                            <x-icon name="download" class="w-4 h-4" />
                            <span>Скачать с сервера</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @if (!$available)
        <div class="p-5 rounded-2xl border border-[#d29922]/40 bg-[#d29922]/10 text-xs sm:text-sm text-[#e3b341] mb-6 flex items-start gap-3">
            <x-icon name="alert-triangle" class="w-5 h-5 text-amber-400" />
            <div class="leading-relaxed">
                <div class="font-bold text-[#e6edf3] mb-1">Файл базы данных SCUM.db не найден локально</div>
                <div>
                    Разместите файл в <code class="px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] font-mono text-xs text-[#58a6ff]">storage/app/scum/SCUM.db</code>
                    или настройте доступ к FTP в настройках хоста и нажмите кнопку <strong class="text-[#e6edf3]">«Скачать с сервера»</strong> выше.
                </div>
            </div>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- NAVIGATION HUB CARDS (6 ГЛАВНЫХ РАЗДЕЛОВ)                                 --}}
    {{-- ========================================================================= --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 mb-6">
        
        {{-- 1. Игроки --}}
        <a href="{{ route('scumdb.players') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#58a6ff]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="user" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[#1f6feb]/10 border border-[#388bfd]/30 text-[#58a6ff]">
                        {{ number_format($counts['players'] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors mb-1">
                    Игроки
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Профили персонажей, инвентарь, навыки, атрибуты и балансы.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#58a6ff] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

        {{-- 2. Отряды --}}
        <a href="{{ route('scumdb.squads') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#a371f7]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 text-[#a371f7] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="shield" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[#a371f7]/10 border border-[#a371f7]/30 text-[#a371f7]">
                        {{ number_format($counts['squads'] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#a371f7] transition-colors mb-1">
                    Отряды
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Клановые составы, ранги участников, лимиты и очки отрядов.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#a371f7] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

        {{-- 3. Транспорт --}}
        <a href="{{ route('scumdb.vehicles') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#d29922]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 text-[#d29922] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="truck" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[#d29922]/10 border border-[#d29922]/30 text-[#d29922]">
                        {{ number_format($counts['vehicles'] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#d29922] transition-colors mb-1">
                    Транспорт
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Вся техника на сервере, точные координаты и статус на ходу.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#d29922] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

        {{-- 4. Флаги баз --}}
        <a href="{{ route('scumdb.flags') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#bc4c9a]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#bc4c9a]/15 border border-[#bc4c9a]/30 text-[#bc4c9a] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="flag" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[#bc4c9a]/10 border border-[#bc4c9a]/30 text-[#bc4c9a]">
                        {{ number_format($counts['flags'] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#bc4c9a] transition-colors mb-1">
                    Флаги баз
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Территории баз, здоровье флагов, защита и таймеры овертейка.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#bc4c9a] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

        {{-- 5. Банки --}}
        <a href="{{ route('scumdb.banks') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#2ea043]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 text-[#2ea043] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="dollar-sign" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[#2ea043]/10 border border-[#2ea043]/30 text-[#2ea043]">
                        {{ number_format($counts['banks'] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#2ea043] transition-colors mb-1">
                    Банки
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Банковские счета игроков, номера карт, деньги и золото.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#2ea043] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

        {{-- 6. Экономика --}}
        <a href="{{ route('scumdb.economy') }}"
           class="card p-4 sm:p-5 flex flex-col justify-between hover:border-[#f0883e]/60 transition-all group bg-[#0d1117]/80 hover:bg-[#161b22]/70 shadow-md hover:-translate-y-0.5">
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="w-11 h-11 rounded-xl bg-[#f0883e]/15 border border-[#f0883e]/30 text-[#f0883e] flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner"><x-icon name="coins" class="w-5 h-5" /></span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#f0883e]/10 border border-[#f0883e]/30 text-[#f0883e] uppercase">
                        ТОП
                    </span>
                </div>
                <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#f0883e] transition-colors mb-1">
                    Экономика
                </h3>
                <p class="text-xs text-[#8b949e] line-clamp-2">
                    Рейтинг самых богатых игроков сервера по балансу банка и золоту.
                </p>
            </div>
            <div class="pt-3 mt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#f0883e] font-medium">
                <span>Перейти</span>
                <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
            </div>
        </a>

    </div>

    {{-- ========================================================================= --}}
    {{-- STATUS & SYNC DASHBOARD                                                   --}}
    {{-- ========================================================================= --}}
    <div class="panel p-5 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]"><x-icon name="refresh-cw" class="w-4 h-4" /></div>
                <div>
                    <h2 class="font-bold text-sm sm:text-base text-[#e6edf3]">Состояние и синхронизация базы</h2>
                    <div class="text-xs text-[#8b949e]">Контроль актуальности локального файла и FTP сервера</div>
                </div>
            </div>
            @if ($ftpHost)
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-[#161b22] border border-[#30363d] text-xs font-mono text-[#8b949e]">
                    <span>FTP:</span>
                    <span class="text-[#58a6ff]">{{ $ftpHost }}</span>
                </div>
            @endif
        </div>

        @if (!$ftpEnabled)
            <div class="mb-4 p-3.5 rounded-xl bg-[#d29922]/10 border border-[#d29922]/30 flex items-center gap-2.5 text-xs text-[#d29922]">
                <x-icon name="alert-triangle" class="w-4 h-4 text-amber-400" />
                <span>FTP не настроен в конфигурации хоста — автоматическая синхронизация и выгрузка на сервер отключены.</span>
            </div>
        @endif

        @if ($hasPending)
            <div class="mb-4 p-3.5 rounded-xl bg-[#e3b341]/10 border border-[#e3b341]/30 flex items-center gap-2.5 text-xs text-[#e3b341]">
                <x-icon name="clock" class="w-3.5 h-3.5 text-[#d29922] inline-block" />
                <span>В локальной базе есть невыгруженные правки. Скачивание с сервера временно заблокировано, чтобы не затереть ваши изменения.</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#30363d]">
                <div class="text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Локальный файл</span>
                    <x-icon name="database" class="w-4 h-4 text-sky-400" />
                </div>
                <div class="font-mono text-sm font-bold text-[#e6edf3]">
                    {{ $localMtime ? date('d.m.Y H:i:s', $localMtime) : '—' }}
                </div>
                <div class="text-[11px] text-[#58a6ff] font-mono mt-1">
                    {{ $localSize ? number_format($localSize / 1048576, 2, ',', ' ') . ' МБ' : 'файл отсутствует' }}
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#30363d]">
                <div class="text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Выгружено на сервер</span>
                    <x-icon name="upload" class="w-4 h-4" />
                </div>
                <div class="font-mono text-sm font-bold text-[#e6edf3]">
                    {{ !empty($dbState['uploaded_at']) ? date('d.m.Y H:i:s', $dbState['uploaded_at']) : 'никогда' }}
                </div>
                <div class="text-[11px] text-[#8b949e] mt-1">
                    {{ !empty($dbState['uploaded_at']) ? 'актуальная копия' : 'ручная выгрузка' }}
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#30363d]">
                <div class="text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Серверный mtime</span>
                    <x-icon name="server" class="w-4 h-4 text-emerald-400" />
                </div>
                <div class="font-mono text-sm font-bold text-[#e6edf3]">
                    {{ !empty($dbState['remote_mtime']) ? date('d.m.Y H:i:s', $dbState['remote_mtime']) : '—' }}
                </div>
                <div class="text-[11px] text-[#8b949e] font-mono mt-1">
                    {{ !empty($dbState['remote_size']) ? number_format($dbState['remote_size'] / 1048576, 2, ',', ' ') . ' МБ' : '—' }}
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#30363d]">
                <div class="text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Проверка FTP</span>
                    <x-icon name="activity" class="w-4 h-4 text-purple-400" />
                </div>
                <div class="font-mono text-sm font-bold text-[#e6edf3]">
                    {{ !empty($dbState['synced_at']) ? date('d.m.Y H:i:s', $dbState['synced_at']) : '—' }}
                </div>
                <div class="text-[11px] text-[#3fb950] font-medium mt-1">
                    {{ !empty($dbState['synced_at']) ? 'Связь в норме' : 'Ожидание' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- DATABASE TABLES EXPLORER                                                  --}}
    {{-- ========================================================================= --}}
    @if ($available)
        <div class="panel p-5 sm:p-6" x-data="searchList()">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-[#30363d]">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center text-[#a371f7]"><x-icon name="database" class="w-4 h-4" /></div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-[#e6edf3]">Все таблицы базы данных</h2>
                        <div class="text-xs text-[#8b949e]">Прямой доступ и просмотр всех таблиц SQLite ({{ count($tables) }})</div>
                    </div>
                </div>

                <div class="relative w-full sm:w-72">
                    <span class="absolute left-3 top-2.5 text-[#8b949e] pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                    <input type="search" x-model="q"
                           class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-8 pr-3 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none"
                           placeholder="Фильтр по названию таблицы…">
                </div>
            </div>

            <div id="tables-list" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
                @foreach ($tables as $t)
                    <a href="{{ route('scumdb.table', $t) }}" x-show="match($el)"
                       class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-[#0d1117] border border-[#30363d] hover:border-[#58a6ff]/60 hover:bg-[#161b22] transition-all group cursor-pointer shadow-sm">
                        <span class="font-mono text-xs text-[#c9d1d9] group-hover:text-[#58a6ff] truncate transition-colors">
                            {{ $t }}
                        </span>
                        <span class="text-xs text-[#8b949e] opacity-0 group-hover:opacity-100 transition-opacity">
                            ↗
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="text-xs text-[#8b949e] text-center py-8" x-show="visible(document.getElementById('tables-list')) === 0" x-cloak>
                Таблицы по запросу не найдены.
            </div>
        </div>
    @endif
@endsection
