@extends('layouts.app')

@section('title', $isNew ? 'Новый воркфлоу' : 'Воркфлоу: ' . $workflow['name'])

@section('content')
@php
    $trigger = $workflow['trigger'] ?? ['type' => 'chat_command', 'config' => []];
    $triggerType = $trigger['type'] ?? 'chat_command';
    $cfg = $trigger['config'] ?? [];

    $trigCards = [
        'chat_command' => [
            'icon' => 'terminal',
            'label' => 'Чат-команда',
            'desc' => 'Запуск, когда игрок пишет команду в игровой чат (!starter, !kit, !wargm)',
            'color' => '#3fb950',
            'badge' => 'bg-[#2ea043]/15 text-[#3fb950] border-[#2ea043]/30',
        ],
        'event' => [
            'icon' => 'activity',
            'label' => 'Событие сервера',
            'desc' => 'Реакция на события сервера (вход игрока, спавн техники, смерть, логин)',
            'color' => '#58a6ff',
            'badge' => 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30',
        ],
        'schedule' => [
            'icon' => 'clock',
            'label' => 'Расписание / Таймер',
            'desc' => 'Регулярный запуск по интервалу (например, каждые 30 или 60 минут)',
            'color' => '#d29922',
            'badge' => 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30',
        ],
        'webhook' => [
            'icon' => 'link',
            'label' => 'Внешний Вебхук',
            'desc' => 'Активация через входящий HTTP POST запрос от стороннего сервиса',
            'color' => '#a371f7',
            'badge' => 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30',
        ],
    ];
@endphp

<script id="wf-initial-nodes" type="application/json">{!! json_encode($workflow['nodes'] ?? [], JSON_UNESCAPED_UNICODE) !!}</script>
<script id="wf-initial-conns" type="application/json">{!! json_encode($workflow['connections'] ?? [], JSON_UNESCAPED_UNICODE) !!}</script>

<div x-data="automationEditor('{{ $triggerType }}')"
     @open-test-drawer.window="testDrawerOpen = true"
     class="space-y-6 w-full pb-16">

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
                            {{ $isNew ? 'Создание нового сценария' : 'Воркфлоу: ' . $workflow['name'] }}
                        </h1>
                        <span class="badge badge-blue">Visual Builder</span>
                    </div>
                    <p class="text-xs sm:text-sm text-[#8b949e]">
                        Конструктор автоматизации: настройка триггеров, цепочек действий, кулдаунов и шаблонов
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0 pt-2 lg:pt-0">
                <a href="{{ route('automation.index') }}"
                   class="btn btn-sm px-3.5 py-2 text-xs border-[#30363d] bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] flex items-center gap-1.5 transition-colors cursor-pointer">
                    <x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
                    <span>Все сценарии</span>
                </a>
                @if (!$isNew)
                    <button type="button" @click="testDrawerOpen = true"
                            class="btn btn-sm btn-accent px-3.5 py-2 text-xs font-semibold flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/20 cursor-pointer">
                        <x-icon name="play" class="w-3.5 h-3.5" />
                        <span>Тест сценария</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ОСНОВНАЯ ФОРМА СЦЕНАРИЯ --}}
    <form action="{{ route('automation.save') }}" method="POST" @submit="syncToRawJson()" class="space-y-6">
        @csrf
        <input type="hidden" name="workflow_id" value="{{ $workflow['id'] }}">
        <input type="hidden" name="enabled" value="0">

        {{-- Скрытые JSON поля --}}
        <input type="hidden" name="nodes_json" :value="rawNodesJson">
        <input type="hidden" name="connections_json" :value="rawConnectionsJson">
        <input type="hidden" name="trigger_type" x-model="trigger">

        {{-- ===== ШАГ 1: ОСНОВНЫЕ ПАРАМЕТРЫ И ТРИГГЕР ЗАПУСКА ===== --}}
        <div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-5">
            
            {{-- Шапка шага 1: Название и Тоггл активности --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#21262d]">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#8b949e] mb-1.5">
                        Название воркфлоу <span class="text-[#f85149]">*</span>
                    </label>
                    <input type="text" name="name"
                           class="w-full h-11 px-4 rounded-xl bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/20 text-sm font-semibold text-[#e6edf3] placeholder-[#8b949e] outline-none shadow-sm transition"
                           value="{{ $workflow['name'] }}" placeholder="Например: Стартовый набор игрока (!starter)" required>
                </div>

                <div class="flex items-center gap-3 p-2.5 rounded-xl bg-[#0d1117] border border-[#30363d] self-start sm:self-end">
                    <span class="text-xs font-semibold text-[#8b949e]">Статус:</span>
                    <label class="toggle" title="Включить / Выключить воркфлоу">
                        <input type="checkbox" name="enabled" value="1" @checked($workflow['enabled'])>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="text-xs font-bold text-[#3fb950]" x-text="document.querySelector('input[name=\'enabled\']')?.checked ? 'Активен' : 'Выключен'"></span>
                </div>
            </div>

            {{-- Карточки выбора типа триггера --}}
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-bold text-[#e6edf3] flex items-center gap-2">
                        <x-icon name="zap" class="w-4 h-4 text-[#58a6ff]" />
                        <span>Шаг 1: Событие запуска (Триггер)</span>
                    </h3>
                    <p class="text-xs text-[#8b949e] mt-0.5">Выберите условие, при котором сценарий начинает автоматическое выполнение</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($trigCards as $t => $tc)
                        <div class="cursor-pointer transition-all border rounded-2xl p-4 flex flex-col justify-between select-none"
                             :class="trigger === '{{ $t }}' ? 'bg-[#1f6feb]/15 border-[#388bfd] shadow-lg shadow-[#1f6feb]/15 ring-1 ring-[#388bfd]' : 'bg-[#0d1117] border-[#21262d] hover:border-[#30363d] hover:bg-[#161b22]'"
                             @click="selectTrigger('{{ $t }}')">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="w-9 h-9 rounded-xl bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#58a6ff] shrink-0">
                                        <x-icon :name="$tc['icon']" class="w-4 h-4" />
                                    </span>
                                    <span class="w-3 h-3 rounded-full transition-transform duration-200"
                                          :style="trigger === '{{ $t }}' ? 'background:{{ $tc['color'] }}; transform: scale(1.1); box-shadow: 0 0 8px {{ $tc['color'] }}' : 'background:#30363d'"></span>
                                </div>
                                <div class="font-bold text-sm text-[#e6edf3]">{{ $tc['label'] }}</div>
                                <div class="text-xs text-[#8b949e] mt-1 leading-relaxed">{{ $tc['desc'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Настройки выбранного триггера --}}
                <div class="p-4 sm:p-5 rounded-2xl bg-[#0d1117] border border-[#21262d] space-y-4">
                    
                    {{-- 1. Чат-команда --}}
                    <div x-show="trigger === 'chat_command'" class="space-y-3" x-cloak>
                        <div class="text-xs font-bold text-[#3fb950] flex items-center gap-1.5 pb-2 border-b border-[#21262d]">
                            <x-icon name="terminal" class="w-4 h-4 text-[#3fb950]" />
                            <span>Параметры распознавания чат-команды</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-[#8b949e] font-semibold mb-1">Команда в чате (без символа !)</label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 text-xs text-[#8b949e] font-mono font-bold">!</span>
                                    <input type="text" name="trigger_chat_trigger" class="input font-mono pl-7 text-xs sm:text-sm w-full" value="{{ $cfg['trigger'] ?? '' }}" placeholder="starter">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-[#8b949e] font-semibold mb-1">Синонимы (через запятую)</label>
                                <input type="text" name="trigger_chat_aliases" class="input font-mono text-xs sm:text-sm w-full" value="{{ implode(', ', $cfg['aliases'] ?? []) }}" placeholder="st, start, kit">
                            </div>
                            <div>
                                <label class="block text-xs text-[#8b949e] font-semibold mb-1">Кулдаун на игрока (секунд)</label>
                                <div class="flex gap-2">
                                    <input type="number" name="trigger_chat_cooldown" class="input text-xs sm:text-sm w-full" value="{{ $cfg['cooldownSeconds'] ?? 0 }}" placeholder="0 = без кулдауна">
                                    <span class="text-xs text-[#8b949e] self-center">сек</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1 flex-wrap">
                            <span class="text-[11px] text-[#8b949e]">Быстрый выбор кулдауна:</span>
                            <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="document.querySelector('input[name=\'trigger_chat_cooldown\']').value = '0'">Без КД</button>
                            <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="document.querySelector('input[name=\'trigger_chat_cooldown\']').value = '300'">5 минут</button>
                            <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="document.querySelector('input[name=\'trigger_chat_cooldown\']').value = '3600'">1 час</button>
                            <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="document.querySelector('input[name=\'trigger_chat_cooldown\']').value = '86400'">24 часа</button>
                        </div>
                    </div>

                    {{-- 2. Серверное событие --}}
                    <div x-show="trigger === 'event'" class="space-y-3" x-cloak>
                        <div class="text-xs font-bold text-[#58a6ff] flex items-center gap-1.5 pb-2 border-b border-[#21262d]">
                            <x-icon name="activity" class="w-4 h-4 text-[#58a6ff]" />
                            <span>Выбор серверного события</span>
                        </div>
                        <div class="max-w-md">
                            <label class="block text-xs text-[#8b949e] font-semibold mb-1">Событие SCUM</label>
                            <select name="trigger_event_event" class="input text-xs sm:text-sm w-full">
                                <option value="player_join" @selected(($cfg['event'] ?? '') === 'player_join')>Подключение игрока к серверу (player_join)</option>
                                <option value="player_leave" @selected(($cfg['event'] ?? '') === 'player_leave')>Выход игрока с сервера (player_leave)</option>
                                <option value="player_kill" @selected(($cfg['event'] ?? '') === 'player_kill')>Убийство игрока (player_kill)</option>
                                <option value="vehicle_spawned" @selected(($cfg['event'] ?? '') === 'vehicle_spawned')>Спавн транспорта (vehicle_spawned)</option>
                                <option value="cargo_dropped" @selected(($cfg['event'] ?? '') === 'cargo_dropped')>Падение аирдропа / груза (cargo_dropped)</option>
                                <option value="admin_login" @selected(($cfg['event'] ?? '') === 'admin_login')>Вход администратора в панель (admin_login)</option>
                            </select>
                        </div>
                    </div>

                    {{-- 3. Расписание / Таймер --}}
                    <div x-show="trigger === 'schedule'" class="space-y-3" x-cloak>
                        <div class="text-xs font-bold text-[#d29922] flex items-center gap-1.5 pb-2 border-b border-[#21262d]">
                            <x-icon name="clock" class="w-4 h-4 text-[#d29922]" />
                            <span>Периодичность запуска</span>
                        </div>
                        <div class="flex items-center gap-4 flex-wrap">
                            <label class="flex items-center gap-2 text-xs font-semibold text-[#e6edf3] cursor-pointer">
                                <input type="checkbox" name="trigger_schedule_interval" value="1" @checked(($cfg['type'] ?? '') === 'interval' || $isNew) class="rounded border-[#30363d] text-[#388bfd]">
                                <span>Включить циклический авто-повтор</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-[#8b949e]">Каждые:</span>
                                <input type="number" name="trigger_schedule_minutes" class="input !w-24 text-xs font-mono" value="{{ $cfg['intervalMinutes'] ?? 60 }}" min="1">
                                <span class="text-xs text-[#8b949e]">минут</span>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Вебхук --}}
                    <div x-show="trigger === 'webhook'" class="space-y-3" x-cloak>
                        <div class="text-xs font-bold text-[#a371f7] flex items-center gap-1.5 pb-2 border-b border-[#21262d]">
                            <x-icon name="link" class="w-4 h-4 text-[#a371f7]" />
                            <span>Настройки входящего HTTP вебхука</span>
                        </div>
                        <div class="max-w-lg">
                            <label class="block text-xs text-[#8b949e] font-semibold mb-1">API URL Path (путь вебхука)</label>
                            <input type="text" name="trigger_webhook_path" class="input font-mono text-xs sm:text-sm w-full" value="{{ $cfg['path'] ?? '' }}" placeholder="/api/webhook/custom">
                            <div class="text-[11px] text-[#8b949e] mt-1">Отправьте HTTP POST запрос на этот адрес для вызова сценария сторонними сервисами.</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ===== ШАГ 2: ВИЗУАЛЬНАЯ ЦЕПОЧКА ДЕЙСТВИЙ ===== --}}
        <div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[#21262d]">
                <div>
                    <h3 class="text-sm font-bold text-[#e6edf3] flex items-center gap-2">
                        <x-icon name="sliders" class="w-4 h-4 text-[#58a6ff]" />
                        <span>Шаг 2: Цепочка действий сценария</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-[#21262d] text-[#58a6ff] border border-[#30363d]"
                              x-text="steps.length + ' действ.'"></span>
                    </h3>
                    <p class="text-xs text-[#8b949e] mt-0.5">Действия исполняются последовательно сверху вниз</p>
                </div>

                {{-- Переключатель режима: Визуальный / JSON --}}
                <div class="flex items-center rounded-xl bg-[#0d1117] border border-[#21262d] p-1 text-xs">
                    <button type="button" @click="setMode('visual')"
                            :class="mode === 'visual' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent'"
                            class="px-3 py-1.5 rounded-lg border transition-all flex items-center gap-1.5 cursor-pointer">
                        <x-icon name="sliders" class="w-3.5 h-3.5" />
                        <span>Визуальный режим</span>
                    </button>
                    <button type="button" @click="setMode('expert')"
                            :class="mode === 'expert' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent'"
                            class="px-3 py-1.5 rounded-lg border transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="font-mono">{ }</span>
                        <span>JSON код</span>
                    </button>
                </div>
            </div>

            {{-- Палитра доступных переменных для быстрой вставки --}}
            <div class="p-3.5 bg-[#0d1117] rounded-xl border border-[#21262d] space-y-2">
                <div class="text-[11px] uppercase tracking-wider text-[#8b949e] font-semibold flex items-center gap-1.5">
                    <x-icon name="sparkles" class="w-3.5 h-3.5 text-[#58a6ff]" />
                    <span>Быстрая вставка переменных (кликните на тег):</span>
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs font-mono">
                    <button type="button" @click="insertTag('steamId')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#388bfd]/50 text-[#58a6ff] transition cursor-pointer">
                        @{{steamId}} <span class="text-[10px] text-[#8b949e] font-sans">SteamID</span>
                    </button>
                    <button type="button" @click="insertTag('playerName')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#2ea043]/50 text-[#3fb950] transition cursor-pointer">
                        @{{playerName}} <span class="text-[10px] text-[#8b949e] font-sans">Ник</span>
                    </button>
                    <button type="button" @click="insertTag('message')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#d29922]/50 text-[#d29922] transition cursor-pointer">
                        @{{message}} <span class="text-[10px] text-[#8b949e] font-sans">Текст</span>
                    </button>
                    <button type="button" @click="insertTag('args[0]')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#a371f7]/50 text-[#a371f7] transition cursor-pointer">
                        @{{args[0]}} <span class="text-[10px] text-[#8b949e] font-sans">1-й аргумент</span>
                    </button>
                    <button type="button" @click="insertTag('now.time')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#388bfd]/50 text-[#79c0ff] transition cursor-pointer">
                        @{{now.time}} <span class="text-[10px] text-[#8b949e] font-sans">Время</span>
                    </button>
                    <button type="button" @click="insertTag('now.date')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#388bfd]/50 text-[#79c0ff] transition cursor-pointer">
                        @{{now.date}} <span class="text-[10px] text-[#8b949e] font-sans">Дата</span>
                    </button>
                    <button type="button" @click="insertTag('random.int(1,100)')" class="px-2 py-1 rounded-lg bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#e6edf3]/50 text-[#e6edf3] transition cursor-pointer">
                        @{{random.int(1,100)}} <span class="text-[10px] text-[#8b949e] font-sans">Рандом</span>
                    </button>
                </div>
            </div>

            {{-- 2.1 ВИЗУАЛЬНЫЙ РЕЖИМ СПИСКА ДЕЙСТВИЙ --}}
            <div x-show="mode === 'visual'" class="space-y-4">
                
                {{-- Пустое состояние --}}
                <div x-show="steps.length === 0" class="p-8 text-center border-2 border-dashed border-[#30363d] rounded-2xl bg-[#0d1117]/60 space-y-2">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center">
                        <x-icon name="sliders" class="w-6 h-6" />
                    </div>
                    <div class="font-bold text-sm text-[#e6edf3]">Нет добавленных действий</div>
                    <p class="text-xs text-[#8b949e] max-w-sm mx-auto">
                        Нажмите кнопку добавления действия ниже или примените готовый шаблон из галереи.
                    </p>
                </div>

                {{-- Список шагов сценария --}}
                <template x-for="(step, index) in steps" :key="step.id">
                    <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#388bfd]/40 transition-all space-y-3 shadow-sm group">
                        
                        {{-- Шапка шага --}}
                        <div class="flex items-center justify-between gap-2 border-b border-[#21262d] pb-2.5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-6 h-6 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center text-xs font-bold text-[#58a6ff] shrink-0" x-text="index + 1"></span>
                                <span class="font-bold text-xs sm:text-sm text-[#e6edf3] truncate" x-text="stepMeta[step.type]?.title || step.type"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono border"
                                      :class="stepMeta[step.type]?.badge" x-text="step.type"></span>
                            </div>

                            {{-- Кнопки управления шагом --}}
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="moveStepUp(index)" :disabled="index === 0"
                                        class="p-1 rounded text-xs text-[#8b949e] hover:text-[#e6edf3] disabled:opacity-30 cursor-pointer" title="Переместить выше"><x-icon name="chevron-up" class="w-3.5 h-3.5" /></button>
                                <button type="button" @click="moveStepDown(index)" :disabled="index === steps.length - 1"
                                        class="p-1 rounded text-xs text-[#8b949e] hover:text-[#e6edf3] disabled:opacity-30 cursor-pointer" title="Переместить ниже"><x-icon name="chevron-down" class="w-3.5 h-3.5" /></button>
                                <button type="button" @click="duplicateStep(index)"
                                        class="p-1 rounded text-xs text-[#8b949e] hover:text-[#58a6ff] cursor-pointer" title="Дублировать шаг"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                <button type="button" @click="removeStep(index)"
                                        class="p-1 rounded text-xs text-[#da3633] hover:text-[#f85149] cursor-pointer" title="Удалить шаг"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>

                        {{-- Содержимое: RCON Команда --}}
                        <div x-show="step.type === 'rcon_command'" class="space-y-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Команда RCON (с поддержкой тегов @{{steamId}}, @{{playerName}})</label>
                                <input type="text" x-model="step.config.command" class="input font-mono text-xs w-full"
                                       placeholder="Announce "Внимание! Игрок @{{playerName}} получил стартовый набор!""
                                       @input="syncToRawJson()">
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap text-[11px] text-[#8b949e]">
                                <span>Шаблоны команд:</span>
                                <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="step.config.command = 'Announce "Текст анонса"'; syncToRawJson()">Announce</button>
                                <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="step.config.command = 'SendNotification 1 "Уведомление" @{{steamId}}'; syncToRawJson()">SendNotification</button>
                                <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="step.config.command = 'SpawnItem BP_Weapon_AK47 1 Location "@{{steamId}}"'; syncToRawJson()">SpawnItem</button>
                                <button type="button" class="btn btn-xs bg-[#161b22] border-[#30363d] cursor-pointer" @click="step.config.command = 'ChangeCurrencyBalance Cash 1000 @{{steamId}}'; syncToRawJson()">ChangeCurrencyBalance</button>
                            </div>
                        </div>

                        {{-- Содержимое: Lolka --}}
                        <div x-show="step.type === 'lolka_webhook'" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Канал / Направление</label>
                                    <select x-model="step.config.webhookKey" class="input text-xs w-full" @change="syncToRawJson()">
                                        <option value="chat">Игровой чат сервера</option>
                                        <option value="login">Логи входа/выхода игроков</option>
                                        <option value="admin">Логи действий админов</option>
                                        <option value="kill">Логи убийств и смертей</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Имя отправителя бота</label>
                                    <input type="text" x-model="step.config.username" class="input text-xs w-full" placeholder="SCUM Server Bot" @input="syncToRawJson()">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Текст Lolka сообщения</label>
                                <textarea x-model="step.config.content" rows="2" class="input text-xs w-full font-mono" placeholder="@{{playerName}}, стартовый набор успешно выдан!" @input="syncToRawJson()"></textarea>
                            </div>
                        </div>

                        {{-- Содержимое: Wargm Выдача --}}
                        <div x-show="step.type === 'wargm_process'" class="p-3.5 bg-[#161b22] rounded-xl border border-[#f0883e]/30 text-xs flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#f0883e]/15 border border-[#f0883e]/30 flex items-center justify-center text-[#f0883e] shrink-0">
                                <x-icon name="shopping-cart" class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-[#f0883e]">Автоматическая выдача покупок с Wargm</div>
                                <div class="text-[#8b949e] mt-0.5">Опрашивает API Wargm для игрока <code>@{{steamId}}</code>, выдаёт купленные товары в игре и отправляет уведомление в чат.</div>
                            </div>
                        </div>

                        {{-- Содержимое: Задержка --}}
                        <div x-show="step.type === 'delay'" class="flex items-center gap-3 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-[#8b949e] font-semibold">Подождать:</span>
                                <input type="number" x-model.number="step.config.duration" min="1" class="input !w-24 text-xs font-mono" @input="syncToRawJson()">
                            </div>
                            <div class="flex items-center gap-2">
                                <select x-model="step.config.unit" class="input !w-32 text-xs" @change="syncToRawJson()">
                                    <option value="seconds">Секунд</option>
                                    <option value="minutes">Минут</option>
                                    <option value="hours">Часов</option>
                                </select>
                            </div>
                        </div>

                        {{-- Содержимое: Условие --}}
                        <div x-show="step.type === 'condition'" class="space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Поле / Переменная</label>
                                    <input type="text" x-model="step.config.field" class="input font-mono text-xs w-full" placeholder="@{{message}}" @input="syncToRawJson()">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Оператор сравнения</label>
                                    <select x-model="step.config.operator" class="input text-xs w-full" @change="syncToRawJson()">
                                        <option value="eq">Равно (=)</option>
                                        <option value="ne">Не равно (!=)</option>
                                        <option value="contains">Содержит</option>
                                        <option value="gt">Больше (&gt;)</option>
                                        <option value="gte">Больше или равно (&gt;=)</option>
                                        <option value="lt">Меньше (&lt;)</option>
                                        <option value="lte">Меньше или равно (&lt;=)</option>
                                        <option value="exists">Не пустое</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Ожидаемое значение</label>
                                    <input type="text" x-model="step.config.value" class="input font-mono text-xs w-full" placeholder="!starter" @input="syncToRawJson()">
                                </div>
                            </div>
                        </div>

                        {{-- Содержимое: Лог --}}
                        <div x-show="step.type === 'log'" class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                            <div>
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Уровень</label>
                                <select x-model="step.config.level" class="input text-xs w-full" @change="syncToRawJson()">
                                    <option value="info">INFO</option>
                                    <option value="warn">WARN</option>
                                    <option value="error">ERROR</option>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Сообщение в лог</label>
                                <input type="text" x-model="step.config.message" class="input text-xs w-full" placeholder="Выполнен сценарий для @{{playerName}}" @input="syncToRawJson()">
                            </div>
                        </div>

                        {{-- Содержимое: Переменная --}}
                        <div x-show="step.type === 'variable'" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <div>
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Область</label>
                                <select x-model="step.config.scope" class="input text-xs w-full" @change="syncToRawJson()">
                                    <option value="player">Игрок</option>
                                    <option value="workflow">Воркфлоу</option>
                                    <option value="persistent">Файл (навсегда)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Действие</label>
                                <select x-model="step.config.action" class="input text-xs w-full" @change="syncToRawJson()">
                                    <option value="set">Задать (=)</option>
                                    <option value="increment">Прибавить (+1)</option>
                                    <option value="clear">Очистить</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Имя</label>
                                <input type="text" x-model="step.config.name" class="input font-mono text-xs w-full" placeholder="kits_count" @input="syncToRawJson()">
                            </div>
                            <div>
                                <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Значение</label>
                                <input type="text" x-model="step.config.value" class="input font-mono text-xs w-full" placeholder="1" @input="syncToRawJson()">
                            </div>
                        </div>

                        {{-- Содержимое: HTTP Запрос --}}
                        <div x-show="step.type === 'http_request'" class="space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">HTTP Метод</label>
                                    <select x-model="step.config.method" class="input text-xs w-full" @change="syncToRawJson()">
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                        <option value="PUT">PUT</option>
                                        <option value="DELETE">DELETE</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] text-[#8b949e] font-semibold mb-1">Целевой URL</label>
                                    <input type="text" x-model="step.config.url" class="input font-mono text-xs w-full" placeholder="https://api.example.com/webhook" @input="syncToRawJson()">
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                {{-- Панель добавления нового шага --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] space-y-3">
                    <div class="text-xs font-bold text-[#e6edf3] flex items-center gap-1.5">
                        <x-icon name="plus" class="w-4 h-4 text-[#58a6ff]" />
                        <span>Добавить новое действие в цепочку:</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <button type="button" @click="addStep('rcon_command')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#3fb950]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="zap" class="w-5 h-5 text-[#3fb950] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#3fb950] truncate">RCON Команда</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Вызов на сервере</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('lolka_webhook')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#58a6ff]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="message-square" class="w-5 h-5 text-[#58a6ff] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#58a6ff] truncate">Lolka Бот</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Отправить в чат</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('wargm_process')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#f0883e]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="shopping-cart" class="w-5 h-5 text-[#f0883e] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#f0883e] truncate">WARGM Выдача</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Выдать покупки</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('delay')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#d29922]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="clock" class="w-5 h-5 text-[#d29922] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#d29922] truncate">Пауза (Задержка)</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Подождать N сек</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('condition')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#a371f7]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="share-2" class="w-5 h-5 text-[#a371f7] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#a371f7] truncate">Условие</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Фильтр команд</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('log')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#e6edf3]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="file-text" class="w-5 h-5 text-[#8b949e] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] truncate">Запись в лог</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Системный лог</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('variable')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#79c0ff]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="coins" class="w-5 h-5 text-[#79c0ff] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#79c0ff] truncate">Переменная</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Счётчик / флаг</div>
                            </div>
                        </button>

                        <button type="button" @click="addStep('http_request')" class="p-3 text-left rounded-xl bg-[#161b22] hover:bg-[#21262d] border border-[#30363d] hover:border-[#388bfd]/50 transition-all flex items-start gap-2.5 group cursor-pointer">
                            <x-icon name="link" class="w-5 h-5 text-[#58a6ff] shrink-0" />
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#58a6ff] truncate">HTTP Запрос</div>
                                <div class="text-[10px] text-[#8b949e] mt-0.5 truncate">Внешний вебхук</div>
                            </div>
                        </button>
                    </div>
                </div>

            </div>

            {{-- 2.2 ЭКСПЕРТНЫЙ РЕЖИМ (JSON) --}}
            <div x-show="mode === 'expert'" class="space-y-4" x-cloak>
                <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#30363d] text-xs text-[#8b949e] flex items-center gap-2">
                    <x-icon name="alert-triangle" class="w-4 h-4 text-[#d29922] shrink-0" />
                    <span><strong class="text-[#58a6ff]">Экспертный режим:</strong> прямое редактирование JSON массива узлов и подключений. Изменения мгновенно синхронизируются с визуальным конструктором.</span>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#8b949e] mb-1">Узлы сценария (Nodes JSON)</label>
                    <textarea x-model="rawNodesJson" rows="10" class="input font-mono text-xs w-full" @input="syncFromRawJson()"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#8b949e] mb-1">Подключения узлов (Connections JSON)</label>
                    <textarea x-model="rawConnectionsJson" rows="6" class="input font-mono text-xs w-full" @input="syncFromRawJson()"></textarea>
                </div>
            </div>

        </div>

        {{-- ===== ШАГ 3: ГОТОВЫЕ СЦЕНАРИИ (1-CLICK ШАБЛОНЫ) ===== --}}
        <div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-4">
            <div class="pb-3 border-b border-[#21262d]">
                <h3 class="text-sm font-bold text-[#e6edf3] flex items-center gap-2">
                    <x-icon name="gift" class="w-4 h-4 text-[#a371f7]" />
                    <span>Готовые сценарии (1-Click Шаблоны)</span>
                </h3>
                <p class="text-xs text-[#8b949e] mt-0.5">Примените готовый шаблон, чтобы мгновенно наполнить воркфлоу проверенной конфигурацией</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                
                {{-- Пресет 1 --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#3fb950]/50 transition-all flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center text-[#3fb950] shrink-0">
                                <x-icon name="gift" class="w-4 h-4" />
                            </div>
                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3]">Стартовый набор (!starter)</div>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            Игрок пишет <code>!starter</code> в чат. Сервер спавнит оружие и рюкзак через RCON, отправляет поздравление в чат и Lolka и ставит кулдаун на 1 час.
                        </p>
                    </div>
                    <button type="button" @click="loadPreset('starter_kit')" class="btn btn-sm btn-primary w-full justify-center text-xs font-semibold cursor-pointer">
                        Применить шаблон
                    </button>
                </div>

                {{-- Пресет 2 --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#58a6ff]/50 transition-all flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff] shrink-0">
                                <x-icon name="user" class="w-4 h-4" />
                            </div>
                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3]">Приветствие новичка</div>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            При подключении игрока (<code>player_join</code>) отправляет личное приветствие в игре и публикует уведомление в Lolka-канал логинов.
                        </p>
                    </div>
                    <button type="button" @click="loadPreset('welcome_player')" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] border-[#30363d] w-full justify-center text-xs font-semibold cursor-pointer">
                        Применить шаблон
                    </button>
                </div>

                {{-- Пресет 3 --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#d29922]/50 transition-all flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center text-[#d29922] shrink-0">
                                <x-icon name="radio" class="w-4 h-4" />
                            </div>
                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3]">Авто-анонс (каждые 30м)</div>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            Каждые 30 минут выводит глобальное объявление на экран всех игроков через <code>Announce</code> и делает запись в системный лог.
                        </p>
                    </div>
                    <button type="button" @click="loadPreset('periodic_announcement')" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] border-[#30363d] w-full justify-center text-xs font-semibold cursor-pointer">
                        Применить шаблон
                    </button>
                </div>

                {{-- Пресет 4 --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#f0883e]/50 transition-all flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#f0883e]/15 border border-[#f0883e]/30 flex items-center justify-center text-[#f0883e] shrink-0">
                                <x-icon name="shopping-cart" class="w-4 h-4" />
                            </div>
                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3]">WARGM Выдача (!wargm)</div>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            Игрок пишет <code>!wargm</code> или <code>!donate</code>. Сценарий автоматически выдаёт купленные товары из очереди и отправляет чек в чат.
                        </p>
                    </div>
                    <button type="button" @click="loadPreset('wargm_delivery')" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] border-[#30363d] w-full justify-center text-xs font-semibold cursor-pointer">
                        Применить шаблон
                    </button>
                </div>

                {{-- Пресет 5 --}}
                <div class="p-4 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#3fb950]/50 transition-all flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center text-[#3fb950] shrink-0">
                                <x-icon name="coins" class="w-4 h-4" />
                            </div>
                            <div class="font-bold text-xs sm:text-sm text-[#e6edf3]">Ежедневный бонус (!daily)</div>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            Игрок пишет <code>!daily</code> раз в 24 часа. Получает 500 монет и 25 очков славы с уведомлением в игровой чат.
                        </p>
                    </div>
                    <button type="button" @click="loadPreset('daily_reward')" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] border-[#30363d] w-full justify-center text-xs font-semibold cursor-pointer">
                        Применить шаблон
                    </button>
                </div>

            </div>
        </div>

        {{-- ===== НИЖНЯЯ ПАНЕЛЬ СОХРАНЕНИЯ (STICKY BOTTOM BAR) ===== --}}
        <div class="p-4 flex items-center justify-between gap-3 sticky bottom-4 shadow-2xl bg-[#121824]/95 backdrop-blur-md border border-[#30363d] rounded-2xl z-20">
            <div class="flex items-center gap-3">
                <a href="{{ route('automation.index') }}" class="btn btn-sm cursor-pointer">
                    Отмена
                </a>
                <span class="text-xs text-[#8b949e] hidden sm:inline" x-text="steps.length + ' действий в цепочке сценария'"></span>
            </div>

            <div class="flex items-center gap-2.5">
                @if (!$isNew)
                    <button type="button" @click="testDrawerOpen = true"
                            class="btn btn-sm btn-accent px-4 py-2 text-xs font-semibold flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/20 cursor-pointer">
                        <x-icon name="play" class="w-3.5 h-3.5" />
                        <span>Тест</span>
                    </button>
                @endif
                <button type="submit" class="btn btn-primary px-5 py-2 text-xs sm:text-sm font-bold shadow-lg shadow-[#1f6feb]/20 flex items-center gap-2 cursor-pointer">
                    <x-icon name="check" class="w-4 h-4" />
                    <span>Сохранить сценарий</span>
                </button>
            </div>
        </div>

    </form>

    {{-- ===== МОДАЛЬНОЕ ОКНО ТЕСТИРОВАНИЯ ===== --}}
    @if (!$isNew)
    <div x-show="testDrawerOpen"
         x-cloak
         role="dialog"
         aria-modal="true"
         x-transition.opacity
         @keydown.escape.window="testDrawerOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         style="display: none;">
        
        <div class="w-full max-w-md rounded-2xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-hidden relative"
             @click.away="testDrawerOpen = false">
            
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#21262d] bg-[#0d1117]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#d29922]/20 border border-[#d29922]/30 flex items-center justify-center text-[#d29922]">
                        <x-icon name="zap" class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm sm:text-base text-[#e6edf3]">Тестирование сценария</h3>
                        <div class="text-[11px] text-[#8b949e] truncate max-w-[260px]">{{ $workflow['name'] }}</div>
                    </div>
                </div>
                <button type="button" @click="testDrawerOpen = false" class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] flex items-center justify-center cursor-pointer">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form action="{{ route('automation.test', $workflow['id']) }}" method="POST" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Входящая команда или сообщение</label>
                    <input type="text" name="message" x-model="testMessage" class="input font-mono text-xs sm:text-sm w-full" placeholder="!starter" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">SteamID</label>
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
                        <span>Что произойдёт:</span>
                    </div>
                    <div>• Эмулируется вызов триггера и цепочки действий воркфлоу.</div>
                    <div>• Команды RCON будут реально отправлены на сервер (если шлюз подключен).</div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#21262d]">
                    <button type="button" @click="testDrawerOpen = false" class="btn btn-sm cursor-pointer">
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
    @endif

</div>
@endsection
