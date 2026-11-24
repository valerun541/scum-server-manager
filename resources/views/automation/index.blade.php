@extends('layouts.app')

@section('title', 'Автоматизация и Сценарии')

@section('content')
@php
    $total       = count($workflows);
    $enabled     = count(array_filter($workflows, fn($w) => $w['enabled']));
    $totalRuns   = array_sum(array_map(fn($w) => $w['stats']['runs'] ?? 0, $workflows));
    $totalErrors = array_sum(array_map(fn($w) => $w['stats']['errors'] ?? 0, $workflows));

    $trigMeta = [
        'chat_command' => ['icon' => 'terminal', 'label' => 'Чат-команда', 'badge' => 'bg-[#2ea043]/15 text-[#3fb950] border-[#2ea043]/30', 'dot' => '#3fb950'],
        'event'        => ['icon' => 'activity', 'label' => 'Событие',    'badge' => 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30', 'dot' => '#58a6ff'],
        'schedule'     => ['icon' => 'clock',    'label' => 'Расписание', 'badge' => 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30', 'dot' => '#d29922'],
        'webhook'      => ['icon' => 'link',     'label' => 'Вебхук',    'badge' => 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30', 'dot' => '#a371f7'],
    ];
@endphp

<div x-data="{
    search: '',
    filterType: 'all',
    filterStatus: 'all',
    testModalOpen: false,
    testWfId: '',
    testWfName: '',
    testMessage: '!starter',
    testSteamId: '76561198000000000',
    testPlayerName: 'TestPlayer',

    openTestModal(id, name, defaultMsg) {
        this.testWfId = id;
        this.testWfName = name;
        this.testMessage = defaultMsg || '!test';
        this.testModalOpen = true;
    },

    matchesFilter(name, type, isEnabled) {
        const q = (this.search || '').toLowerCase().trim();
        const matchesSearch = !q || name.toLowerCase().includes(q) || type.toLowerCase().includes(q);
        const matchesType = this.filterType === 'all' || type === this.filterType;
        const matchesStatus = this.filterStatus === 'all' || (this.filterStatus === 'enabled' && isEnabled) || (this.filterStatus === 'disabled' && !isEnabled);
        return matchesSearch && matchesType && matchesStatus;
    }
}" class="space-y-6">

    {{-- ===== 1. HERO HEADER ===== --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d]/80 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-5 sm:p-7 shadow-2xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-20 w-72 h-72 rounded-full bg-[#238636]/10 blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-14 h-14 shrink-0 rounded-2xl bg-gradient-to-br from-[#1f6feb] via-[#388bfd] to-[#238636] p-[2px] shadow-lg shadow-[#1f6feb]/25">
                    <div class="w-full h-full rounded-[14px] bg-[#0d1117] flex items-center justify-center text-[#58a6ff]">
                        <x-icon name="cpu" class="w-7 h-7" />
                    </div>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-[#e6edf3] tracking-tight">
                            Автоматизация и Сценарии
                        </h1>
                        <span class="badge badge-blue">Workflow Engine</span>
                    </div>
                    <p class="text-xs sm:text-sm text-[#8b949e]">
                        Визуальный конструктор сценариев: реакция на чат-команды, игровые события сервера, таймеры и вебхуки
                    </p>
                    <div class="flex flex-wrap items-center gap-2.5 mt-2.5 text-xs">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#2ea043]/15 border border-[#2ea043]/40 text-[#3fb950] font-semibold">
                            <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-pulse"></span>
                            Активно: {{ $enabled }} из {{ $total }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] font-medium">
                            <x-icon name="activity" class="w-3.5 h-3.5" />
                            <span>{{ number_format($totalRuns, 0, '.', ' ') }} запусков</span>
                        </span>
                        @if ($totalErrors > 0)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#da3633]/15 border border-[#da3633]/40 text-[#f85149] font-medium">
                                <x-icon name="alert-triangle" class="w-3.5 h-3.5" />
                                <span>{{ $totalErrors }} ошибок</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0 pt-2 lg:pt-0">
                <form action="{{ route('automation.schedules') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-sm px-3.5 py-2 text-xs border-[#30363d] bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] flex items-center gap-2 transition-colors cursor-pointer" title="Принудительно проверить и запустить сценарии по расписанию">
                        <x-icon name="clock" class="w-3.5 h-3.5 text-[#d29922]" />
                        <span>Проверить таймеры</span>
                    </button>
                </form>
                <a href="{{ route('automation.create') }}" class="btn btn-primary px-4 py-2.5 text-xs sm:text-sm font-semibold shadow-lg shadow-[#1f6feb]/20 flex items-center gap-2 cursor-pointer">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span>Создать воркфлоу</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ===== 2. СТАТИСТИКА (KPI STATS CARDS) ===== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#1f6feb]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="cpu" class="w-6 h-6 text-[#58a6ff]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#e6edf3] tabular-nums">{{ $total }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Всего сценариев</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#2ea043]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="check" class="w-6 h-6 text-[#3fb950]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#3fb950] tabular-nums">{{ $enabled }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Активных в игре</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#d29922]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="activity" class="w-6 h-6 text-[#d29922]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#e6edf3] tabular-nums">{{ number_format($totalRuns, 0, '.', ' ') }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Всего выполнений</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#f85149]/40 transition-all">
            <div class="w-12 h-12 rounded-xl {{ $totalErrors > 0 ? 'bg-[#f85149]/15 border border-[#f85149]/30 text-[#f85149]' : 'bg-[#21262d] border border-[#30363d] text-[#8b949e]' }} flex items-center justify-center shrink-0 shadow-inner">
                @if($totalErrors > 0)
                    <x-icon name="alert-triangle" class="w-6 h-6 text-[#f85149]" />
                @else
                    <x-icon name="shield" class="w-6 h-6 text-[#8b949e]" />
                @endif
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold tabular-nums {{ $totalErrors > 0 ? 'text-[#f85149]' : 'text-[#8b949e]' }}">{{ $totalErrors }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Ошибок при запуске</div>
            </div>
        </div>
    </div>

    {{-- ===== 3. ФИЛЬТРЫ И ПОИСК ===== --}}
    <div class="p-3.5 sm:p-4 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        
        {{-- Поле поиска --}}
        <div class="flex-1 relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8b949e] pointer-events-none">
                <x-icon name="search" class="w-4 h-4" />
            </span>
            <input type="text" x-model="search"
                   class="w-full h-10 pl-10 pr-9 rounded-xl bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/20 text-xs sm:text-sm text-[#e6edf3] placeholder-[#8b949e] transition outline-none shadow-sm"
                   placeholder="Поиск по названию, команде чата или триггеру...">
            <button type="button" x-show="search" @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[#8b949e] hover:text-[#e6edf3] cursor-pointer">
                <x-icon name="x" class="w-3.5 h-3.5" />
            </button>
        </div>

        {{-- Фильтры по типу --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 md:pb-0">
            <div class="flex items-center gap-1 p-1 rounded-xl bg-[#0d1117] border border-[#21262d] text-xs">
                <button type="button" @click="filterType = 'all'"
                        :class="filterType === 'all' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                        class="px-2.5 py-1.5 rounded-lg border transition-all whitespace-nowrap cursor-pointer">
                    Все типы
                </button>
                <button type="button" @click="filterType = 'chat_command'"
                        :class="filterType === 'chat_command' ? 'bg-[#2ea043]/20 text-[#3fb950] border-[#2ea043]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                        class="px-2.5 py-1.5 rounded-lg border transition-all whitespace-nowrap cursor-pointer">
                    Чат-команды
                </button>
                <button type="button" @click="filterType = 'event'"
                        :class="filterType === 'event' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                        class="px-2.5 py-1.5 rounded-lg border transition-all whitespace-nowrap cursor-pointer">
                    События
                </button>
                <button type="button" @click="filterType = 'schedule'"
                        :class="filterType === 'schedule' ? 'bg-[#d29922]/20 text-[#d29922] border-[#d29922]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                        class="px-2.5 py-1.5 rounded-lg border transition-all whitespace-nowrap cursor-pointer">
                    Таймеры
                </button>
                <button type="button" @click="filterType = 'webhook'"
                        :class="filterType === 'webhook' ? 'bg-[#a371f7]/20 text-[#a371f7] border-[#a371f7]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                        class="px-2.5 py-1.5 rounded-lg border transition-all whitespace-nowrap cursor-pointer">
                    Вебхуки
                </button>
            </div>

            {{-- Фильтр активности --}}
            <div class="flex items-center gap-1 p-1 rounded-xl bg-[#0d1117] border border-[#21262d] text-xs">
                <button type="button" @click="filterStatus = 'all'"
                        :class="filterStatus === 'all' ? 'bg-[#21262d] text-[#e6edf3] font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                        class="px-2.5 py-1.5 rounded-lg transition-all cursor-pointer">
                    Все
                </button>
                <button type="button" @click="filterStatus = 'enabled'"
                        :class="filterStatus === 'enabled' ? 'bg-[#2ea043]/20 text-[#3fb950] font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                        class="px-2.5 py-1.5 rounded-lg transition-all cursor-pointer">
                    Вкл
                </button>
                <button type="button" @click="filterStatus = 'disabled'"
                        :class="filterStatus === 'disabled' ? 'bg-[#f85149]/20 text-[#f85149] font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                        class="px-2.5 py-1.5 rounded-lg transition-all cursor-pointer">
                    Выкл
                </button>
            </div>
        </div>
    </div>

    {{-- ===== 4. РЕЕСТР СЦЕНАРИЕВ (ТАБЛИЦА) ===== --}}
    @if ($total === 0)
        <div class="p-12 text-center rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-4">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff] shadow-inner">
                <x-icon name="cpu" class="w-8 h-8" />
            </div>
            <div class="space-y-1">
                <h3 class="text-base font-bold text-[#e6edf3]">Сценарии автоматизации ещё не созданы</h3>
                <p class="text-xs text-[#8b949e] max-w-md mx-auto">
                    Создайте свой первый сценарий для выдачи наборов по чат-командам (!starter), приветствий новичков или авто-анонсов.
                </p>
            </div>
            <a href="{{ route('automation.create') }}" class="btn btn-primary px-5 py-2 text-xs font-semibold shadow-md shadow-[#1f6feb]/20 inline-flex items-center gap-2 cursor-pointer">
                <x-icon name="plus" class="w-4 h-4" />
                <span>Создать первый сценарий</span>
            </a>
        </div>
    @else
        {{-- Десктопная таблица --}}
        <div class="hidden md:block rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#0d1117] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider border-b border-[#21262d]">
                            <th class="py-3.5 px-4">Сценарий / Название</th>
                            <th class="py-3.5 px-4">Тип & Условие триггера</th>
                            <th class="py-3.5 px-4 text-center">Действий</th>
                            <th class="py-3.5 px-4 text-right">Запусков</th>
                            <th class="py-3.5 px-4 text-right">Ошибок</th>
                            <th class="py-3.5 px-4">Последний запуск</th>
                            <th class="py-3.5 px-4 text-center">Статус</th>
                            <th class="py-3.5 px-4 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d] bg-[#0d1117]/60 text-xs">
                        @foreach ($workflows as $wf)
                            @php
                                $type = $wf['trigger']['type'] ?? 'unknown';
                                $cfg  = $wf['trigger']['config'] ?? [];
                                $meta = $trigMeta[$type] ?? ['icon' => 'info', 'label' => 'Неизвестно', 'badge' => 'bg-[#21262d] text-[#8b949e] border-[#30363d]', 'dot' => '#8b949e'];
                                $trigDesc = '—';
                                $testTriggerCmd = '';
                                if ($type === 'chat_command') {
                                    $trigName = $cfg['trigger'] ?? 'starter';
                                    $testTriggerCmd = '!' . $trigName;
                                    $parts = ['!' . $trigName];
                                    if (!empty($cfg['aliases'])) $parts[] = '(' . implode(', ', array_map(fn($a) => '!' . $a, $cfg['aliases'])) . ')';
                                    if (!empty($cfg['cooldownSeconds'])) {
                                        $cd = (int)$cfg['cooldownSeconds'];
                                        $cdStr = $cd >= 3600 ? round($cd/3600, 1).'ч' : ($cd >= 60 ? round($cd/60).'м' : $cd.'с');
                                        $parts[] = 'CD: ' . $cdStr;
                                    }
                                    $trigDesc = implode(' ', $parts);
                                } elseif ($type === 'event') {
                                    $trigDesc = 'Событие: ' . ($cfg['event'] ?? '—');
                                    $testTriggerCmd = $cfg['event'] ?? 'player_join';
                                } elseif ($type === 'schedule') {
                                    $trigDesc = ($cfg['type'] ?? '') === 'interval'
                                        ? 'Каждые ' . ($cfg['intervalMinutes'] ?? '60') . ' мин'
                                        : 'Вручную';
                                    $testTriggerCmd = 'schedule';
                                } elseif ($type === 'webhook') {
                                    $trigDesc = 'Path: ' . ($cfg['path'] ?? '—');
                                    $testTriggerCmd = 'webhook';
                                }
                                $errs = $wf['stats']['errors'] ?? 0;
                                $runs = $wf['stats']['runs'] ?? 0;
                                $nodesCount = count($wf['nodes'] ?? []);
                                $isEnabled = (bool) $wf['enabled'];
                            @endphp
                            <tr x-show="matchesFilter('{{ addslashes($wf['name']) }}', '{{ $type }}', {{ $isEnabled ? 'true' : 'false' }})"
                                class="hover:bg-[#161d2d] transition-colors group">
                                
                                {{-- Название сценария --}}
                                <td class="py-3 px-4 font-medium">
                                    <a href="{{ route('automation.edit', $wf['id']) }}" class="flex items-center gap-3 group/link">
                                        <span class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#58a6ff] group-hover/link:border-[#388bfd]/50 group-hover/link:bg-[#1f6feb]/15 transition-all shrink-0">
                                            <x-icon :name="$meta['icon']" class="w-4 h-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3] group-hover/link:text-[#58a6ff] transition-colors truncate">
                                                {{ $wf['name'] }}
                                            </div>
                                            <div class="text-[10px] font-mono text-[#8b949e]">ID: {{ $wf['id'] }}</div>
                                        </div>
                                    </a>
                                </td>

                                {{-- Триггер --}}
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $meta['badge'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $meta['dot'] }}"></span>
                                            {{ $meta['label'] }}
                                        </span>
                                        <span class="text-xs text-[#c9d1d9] font-mono bg-[#0d1117] px-2 py-0.5 rounded-lg border border-[#30363d]">
                                            {{ $trigDesc }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Количество действий --}}
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-lg font-mono text-xs font-bold bg-[#161b22] border border-[#30363d] text-[#e6edf3]">
                                        {{ $nodesCount }}
                                    </span>
                                </td>

                                {{-- Количество запусков --}}
                                <td class="py-3 px-4 text-right tabular-nums text-[#e6edf3] font-semibold">
                                    {{ number_format($runs, 0, '.', ' ') }}
                                </td>

                                {{-- Количество ошибок --}}
                                <td class="py-3 px-4 text-right tabular-nums">
                                    @if ($errs > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#da3633]/20 border border-[#da3633]/40 text-[#f85149]">
                                            {{ $errs }}
                                        </span>
                                    @else
                                        <span class="text-[#484f58]">—</span>
                                    @endif
                                </td>

                                {{-- Последний запуск --}}
                                <td class="py-3 px-4 text-xs text-[#8b949e] font-mono whitespace-nowrap">
                                    {{ !empty($wf['stats']['lastRun']) ? date('d.m.Y H:i', strtotime($wf['stats']['lastRun'])) : '—' }}
                                </td>

                                {{-- Тоггл активности --}}
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('automation.toggle', $wf['id']) }}" method="POST" class="inline">
                                        @csrf
                                        <label class="toggle" title="{{ $isEnabled ? 'Выключить воркфлоу' : 'Активировать воркфлоу' }}">
                                            <input type="checkbox" @checked($isEnabled) onchange="this.form.submit()">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </form>
                                </td>

                                {{-- Кнопки действий --}}
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" @click="openTestModal('{{ $wf['id'] }}', '{{ addslashes($wf['name']) }}', '{{ addslashes($testTriggerCmd) }}')"
                                                class="btn btn-xs btn-accent px-2.5 py-1 text-xs flex items-center gap-1 shadow-sm cursor-pointer" title="Тестовый запуск сценария">
                                            <x-icon name="play" class="w-3 h-3" />
                                            <span>Тест</span>
                                        </button>

                                        <a href="{{ route('automation.edit', $wf['id']) }}"
                                           class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] hover:text-[#58a6ff] border-[#30363d] px-2 py-1 cursor-pointer" title="Редактировать воркфлоу">
                                            <x-icon name="edit" class="w-3.5 h-3.5" />
                                        </a>

                                        @if ($errs > 0)
                                            <form action="{{ route('automation.reset-stats', $wf['id']) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Обнулить счётчик ошибок для «{{ addslashes($wf['name']) }}»?')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#d29922] border-[#30363d] px-2 py-1 cursor-pointer" title="Сбросить ошибки">
                                                    Сброс
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('automation.delete', $wf['id']) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Удалить воркфлоу «{{ addslashes($wf['name']) }}»?')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-danger px-2 py-1 cursor-pointer" title="Удалить сценарий">
                                                <x-icon name="trash" class="w-3.5 h-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Мобильный список (Карточки) --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @foreach ($workflows as $wf)
                @php
                    $type = $wf['trigger']['type'] ?? 'unknown';
                    $cfg  = $wf['trigger']['config'] ?? [];
                    $meta = $trigMeta[$type] ?? ['icon' => 'info', 'label' => 'Неизвестно', 'badge' => 'bg-[#21262d] text-[#8b949e] border-[#30363d]', 'dot' => '#8b949e'];
                    $trigDesc = '—';
                    $testTriggerCmd = '';
                    if ($type === 'chat_command') {
                        $trigName = $cfg['trigger'] ?? 'starter';
                        $testTriggerCmd = '!' . $trigName;
                        $parts = ['!' . $trigName];
                        if (!empty($cfg['aliases'])) $parts[] = '(' . implode(', ', array_map(fn($a) => '!' . $a, $cfg['aliases'])) . ')';
                        if (!empty($cfg['cooldownSeconds'])) $parts[] = 'CD: ' . $cfg['cooldownSeconds'] . 'с';
                        $trigDesc = implode(' ', $parts);
                    } elseif ($type === 'event') {
                        $trigDesc = $cfg['event'] ?? '—';
                        $testTriggerCmd = $cfg['event'] ?? 'player_join';
                    } elseif ($type === 'schedule') {
                        $trigDesc = ($cfg['type'] ?? '') === 'interval' ? 'Каждые ' . ($cfg['intervalMinutes'] ?? '60') . ' мин' : 'Вручную';
                        $testTriggerCmd = 'schedule';
                    } elseif ($type === 'webhook') {
                        $trigDesc = $cfg['path'] ?? '—';
                        $testTriggerCmd = 'webhook';
                    }
                    $errs = $wf['stats']['errors'] ?? 0;
                    $runs = $wf['stats']['runs'] ?? 0;
                    $nodesCount = count($wf['nodes'] ?? []);
                    $isEnabled = (bool) $wf['enabled'];
                @endphp
                <div x-show="matchesFilter('{{ addslashes($wf['name']) }}', '{{ $type }}', {{ $isEnabled ? 'true' : 'false' }})"
                     class="panel p-4 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-3">
                    
                    <div class="flex items-start justify-between gap-3 border-b border-[#21262d] pb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#58a6ff] shrink-0">
                                <x-icon :name="$meta['icon']" class="w-4 h-4" />
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('automation.edit', $wf['id']) }}" class="font-bold text-sm text-[#e6edf3] hover:text-[#58a6ff] truncate block">
                                    {{ $wf['name'] }}
                                </a>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold border {{ $meta['badge'] }}">
                                        {{ $meta['label'] }}
                                    </span>
                                    <span class="text-[11px] text-[#8b949e] font-mono truncate">{{ $trigDesc }}</span>
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('automation.toggle', $wf['id']) }}" method="POST" class="shrink-0">
                            @csrf
                            <label class="toggle">
                                <input type="checkbox" @checked($isEnabled) onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>

                    <div class="grid grid-cols-3 gap-2 py-2 bg-[#0d1117] rounded-xl border border-[#21262d] text-center text-xs">
                        <div>
                            <div class="text-[10px] uppercase text-[#8b949e] font-semibold">Действий</div>
                            <div class="font-extrabold text-[#e6edf3] text-sm mt-0.5">{{ $nodesCount }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase text-[#8b949e] font-semibold">Запусков</div>
                            <div class="font-extrabold text-[#58a6ff] text-sm mt-0.5">{{ $runs }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase text-[#8b949e] font-semibold">Ошибок</div>
                            <div class="font-extrabold text-sm mt-0.5 {{ $errs > 0 ? 'text-[#f85149]' : 'text-[#8b949e]' }}">{{ $errs }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-[#8b949e] pt-1">
                        <div class="font-mono text-[11px]">
                            Посл: {{ !empty($wf['stats']['lastRun']) ? date('d.m H:i', strtotime($wf['stats']['lastRun'])) : '—' }}
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openTestModal('{{ $wf['id'] }}', '{{ addslashes($wf['name']) }}', '{{ addslashes($testTriggerCmd) }}')"
                                    class="btn btn-xs btn-accent px-2.5 py-1">
                                Тест
                            </button>
                            <a href="{{ route('automation.edit', $wf['id']) }}" class="btn btn-xs bg-[#161b22] border-[#30363d] px-2 py-1 text-[#e6edf3]">
                                <x-icon name="edit" class="w-3.5 h-3.5" />
                            </a>
                            <form action="{{ route('automation.delete', $wf['id']) }}" method="POST" onsubmit="return confirm('Удалить?')">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-danger px-2 py-1"><x-icon name="trash" class="w-3.5 h-3.5" /></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== 5. МОДАЛЬНОЕ ОКНО ТЕСТИРОВАНИЯ ===== --}}
    <div x-show="testModalOpen"
         x-cloak
         role="dialog"
         aria-modal="true"
         x-transition.opacity
         @keydown.escape.window="testModalOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         style="display: none;">
        
        <div class="w-full max-w-md rounded-2xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-hidden relative"
             @click.away="testModalOpen = false">
            
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#21262d] bg-[#0d1117]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#d29922]/20 border border-[#d29922]/30 flex items-center justify-center text-[#d29922]">
                        <x-icon name="zap" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm sm:text-base text-[#e6edf3]">Тестовый запуск сценария</h3>
                        <div class="text-[11px] text-[#8b949e] truncate max-w-[260px]" x-text="testWfName"></div>
                    </div>
                </div>
                <button type="button" @click="testModalOpen = false" class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] flex items-center justify-center cursor-pointer">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form :action="'/automation/' + testWfId + '/test'" method="POST" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Входящая команда или сообщение</label>
                    <input type="text" name="message" x-model="testMessage" class="input font-mono text-xs sm:text-sm w-full" placeholder="!starter" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">SteamID игрока</label>
                        <input type="text" name="steam_id" x-model="testSteamId" class="input font-mono text-xs w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Имя игрока</label>
                        <input type="text" name="player_name" x-model="testPlayerName" class="input text-xs w-full">
                    </div>
                </div>

                <div class="p-3.5 bg-[#0d1117] rounded-xl border border-[#21262d] text-xs text-[#8b949e] space-y-1.5">
                    <div class="font-bold text-[#58a6ff] flex items-center gap-1.5">
                        <x-icon name="info" class="w-3.5 h-3.5" />
                        <span>Как работает тест:</span>
                    </div>
                    <div>• Эмулирует вызов триггера и пошагово исполняет действия сценария.</div>
                    <div>• RCON команды отправляются на игровой сервер (если шлюз подключен).</div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#21262d]">
                    <button type="button" @click="testModalOpen = false" class="btn btn-sm cursor-pointer">
                        Отмена
                    </button>
                    <button type="submit" class="btn btn-sm btn-accent px-4 py-2 flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/20 cursor-pointer">
                        <x-icon name="play" class="w-3.5 h-3.5" />
                        <span>Запустить тест</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
