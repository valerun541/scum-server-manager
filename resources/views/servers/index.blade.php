@extends('layouts.app')

@section('title', 'Выбор сервера')

@section('content')
<div x-data="serverForm(window.SERVERS, window.PLUGINS_CONFIG, window.LOLKA_CONFIG)" class="min-h-screen pb-12 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    @php
        $total = count($servers);
        $activeId = (int) $activeServerId;
        $activeServer = null;
        foreach ($servers as $srv) {
            if ((int) $srv['id'] === $activeId) {
                $activeServer = $srv;
                break;
            }
        }
        $as = $activeStatus ?? [
            'rcon' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false, 'pid' => null]],
            'ftp' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false, 'pid' => null]],
        ];
        $ftpDaemonRunning = !empty($as['ftp']['daemon']['running']);
        $rconDaemonRunning = !empty($as['rcon']['daemon']['running']);
    @endphp

    {{-- ===== 1. GATEWAY TOP NAVIGATION BAR ===== --}}
    <div class="flex items-center justify-between gap-4 py-4 border-b border-[#21262d] mb-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb] via-[#238636] to-[#d29922] p-[1.5px] shadow-lg shadow-[#1f6feb]/25">
                <div class="w-full h-full rounded-[10px] bg-[#090d14] flex items-center justify-center font-black text-sm text-[#58a6ff]">
                    SC
                </div>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-base text-[#e6edf3] tracking-tight">SCUM Server Manager</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff]">Gateway Hub</span>
                </div>
                <div class="text-[11px] text-[#8b949e]">Центральный шлюз выбора и управления серверами</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('account.settings') }}"
               class="btn btn-sm px-3.5 py-1.5 text-xs font-medium border-[#30363d] bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] flex items-center gap-2 transition-colors">
                <x-icon name="user" class="w-3.5 h-3.5 text-[#58a6ff]" />
                <span class="hidden sm:inline">Профиль администратора</span>
            </a>
            <form action="{{ route('logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn btn-sm px-3 py-1.5 text-xs text-[#8b949e] hover:text-[#f85149] hover:border-[#f85149]/40 transition-colors" title="Выйти из системы">
                    <x-icon name="logout" class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Выйти</span>
                </button>
            </form>
        </div>
    </div>

    {{-- ===== 2. HERO GATEWAY HEADER ===== --}}
    <div class="relative overflow-hidden rounded-3xl border border-[#30363d]/80 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-6 sm:p-8 shadow-2xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-20 w-72 h-72 rounded-full bg-[#2ea043]/10 blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl sm:text-3xl font-black text-[#e6edf3] tracking-tight">
                        Игровые серверы SCUM
                    </h1>
                    <span class="badge badge-blue">Enterprise Portal</span>
                </div>
                <p class="text-sm text-[#8b949e] leading-relaxed">
                    Выберите сервер для входа в панель управления, мониторинга игроков онлайн, карты острова и отправки RCON команд.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <button type="button" @click="openCreate()"
                        class="btn btn-accent px-5 py-2.5 text-sm font-semibold shadow-lg shadow-[#1f6feb]/25 flex items-center gap-2">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span>Добавить сервер</span>
                </button>
            </div>
        </div>
    </div>



    {{-- ===== 4. СЕТКА КАРТОЧЕК СЕРВЕРОВ (MAIN GRID) ===== --}}
    @if (empty($servers))
        <div class="panel p-10 text-center border-dashed border-[#30363d] rounded-3xl my-8">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-[#1f6feb]/10 border border-[#388bfd]/30 flex items-center justify-center mb-4 text-[#58a6ff]">
                <x-icon name="server" class="w-8 h-8" />
            </div>
            <h2 class="text-xl font-bold text-[#e6edf3] mb-2">Серверы ещё не добавлены</h2>
            <p class="text-sm text-[#8b949e] max-w-md mx-auto mb-6">
                Подключите ваш первый игровой сервер SCUM, чтобы активировать интерактивную карту, RCON консоль и автоматизацию.
            </p>
            <button type="button" @click="openCreate()" class="btn btn-accent px-6 py-2.5 text-sm font-semibold shadow-lg shadow-[#1f6feb]/25">
                <span class="inline-flex items-center gap-2"><x-icon name="plus" class="w-4 h-4" /> Добавить сервер</span>
            </button>
        </div>
    @else
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3 px-1">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                    Все подключенные серверы ({{ $total }})
                </h2>
                <button type="button" onclick="window.location.reload()" class="text-xs text-[#8b949e] hover:text-[#58a6ff] flex items-center gap-1.5 transition-colors">
                    <x-icon name="refresh-cw" class="w-3 h-3" />
                    <span>Обновить статусы</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($servers as $server)
                    @php
                        $isActive = (int) $server['id'] === $activeId;
                        $st = $statuses[$server['id']] ?? [
                            'rcon' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false]],
                            'ftp' => ['connected' => false, 'error' => null, 'configured' => false, 'daemon' => ['running' => false]],
                        ];
                        $ftpOn = !empty($st['ftp']['connected']);
                        $rconOn = !empty($st['rcon']['connected']);
                        $isEnabled = (int) $server['enabled'];
                        $srvHost = $server['rcon_host'] ?: ($server['ftp_host'] ?: '127.0.0.1');
                        $srvPort = $server['rcon_port'] ?: 7004;

                        // Список включенных плагинов
                        $pConfig = $pluginsConfig[$server['id']] ?? [];
                        $enabledPlugins = [];
                        if (!empty($pConfig['teleport'])) $enabledPlugins[] = 'Телепорт';
                        if (!empty($pConfig['vip'])) $enabledPlugins[] = 'VIP';
                        if (!empty($pConfig['saveHome'])) $enabledPlugins[] = 'SaveHome';
                        if (!empty($pConfig['airdrop'])) $enabledPlugins[] = 'Airdrop';
                        if (!empty($pConfig['insurance'])) $enabledPlugins[] = 'Страховка';
                        if (!empty($pConfig['packs'])) $enabledPlugins[] = 'Паки';
                        if (!empty($pConfig['rewards'])) $enabledPlugins[] = 'Награды';
                    @endphp

                    <div @class([
                        'rounded-3xl border flex flex-col justify-between overflow-hidden transition-all duration-200 group shadow-xl',
                        'bg-gradient-to-b from-[#141d2e] via-[#101522] to-[#0c1017] border-[#388bfd]/60 shadow-[#1f6feb]/15 ring-1 ring-[#388bfd]/30' => $isActive,
                        'bg-[#0f141f] border-[#252b36] hover:border-[#388bfd]/50 hover:bg-[#121824]' => !$isActive,
                    ])>
                        
                        {{-- Верхний акцент карточки --}}
                        <div @class([
                            'h-1.5 w-full',
                            'bg-gradient-to-r from-[#1f6feb] via-[#58a6ff] to-[#3fb950]' => $isActive,
                            'bg-[#21262d] group-hover:bg-[#388bfd]/50 transition-colors' => !$isActive,
                        ])></div>

                        <div class="p-5 sm:p-6 space-y-4">
                            {{-- Заголовок сервера --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div @class([
                                        'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 transition-all shadow-md',
                                        'bg-[#1f6feb]/25 border border-[#388bfd]/60 text-[#58a6ff]' => $isActive,
                                        'bg-[#161b22] border border-[#30363d] text-[#8b949e] group-hover:text-white' => !$isActive
                                    ])>
                                        <x-icon name="server" class="w-6 h-6" />
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-black text-lg sm:text-xl text-[#e6edf3] truncate group-hover:text-[#58a6ff] transition-colors" title="{{ $server['name'] }}">
                                            {{ $server['name'] ?: 'Без имени' }}
                                        </h3>
                                        <div class="text-xs text-[#8b949e] font-mono mt-0.5">
                                            ID: #{{ $server['id'] }} · {{ $srvHost }}:{{ $srvPort }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    @if ($isActive)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#1f6feb]/20 border border-[#388bfd]/40 text-[#58a6ff]">
                                            Активен
                                        </span>
                                    @elseif ($isEnabled)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#2ea043]/15 border border-[#2ea043]/30 text-[#3fb950]">
                                            Готов
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#21262d] text-[#8b949e]">
                                            Отключен
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Статусы протоколов RCON и FTP --}}
                            <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
                                <div class="p-2.5 rounded-xl bg-[#0a0d14] border border-[#21262d] flex items-center justify-between">
                                    <span class="text-[#8b949e] flex items-center gap-1.5 font-medium">
                                        <x-icon name="terminal" class="w-3.5 h-3.5 text-[#58a6ff]" /> RCON:
                                    </span>
                                    @if ($rconOn)
                                        <span class="text-xs font-bold text-[#3fb950] flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span> Онлайн
                                        </span>
                                    @else
                                        <span class="text-xs text-[#f85149] font-medium">Офлайн</span>
                                    @endif
                                </div>

                                <div class="p-2.5 rounded-xl bg-[#0a0d14] border border-[#21262d] flex items-center justify-between">
                                    <span class="text-[#8b949e] flex items-center gap-1.5 font-medium">
                                        <x-icon name="hard-drive" class="w-3.5 h-3.5 text-[#d29922]" /> FTP:
                                    </span>
                                    @if ($ftpOn)
                                        <span class="text-xs font-bold text-[#3fb950] flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span> Доступен
                                        </span>
                                    @else
                                        <span class="text-xs text-[#8b949e] font-medium">Ожидание</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Активные модули и плагины --}}
                            <div class="space-y-1.5 pt-1">
                                <div class="text-[11px] text-[#8b949e] font-semibold flex items-center justify-between">
                                    <span>Включенные модули:</span>
                                    <span>{{ count($enabledPlugins) }} активных</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($enabledPlugins as $pName)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-[#161b22] border border-[#30363d] text-[#c9d1d9]">
                                            {{ $pName }}
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-slate-500 italic">Все модули отключены</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- Футер карточки с главной кнопкой ВХОД В ПАНЕЛЬ --}}
                        <div class="p-5 sm:p-6 pt-0 space-y-3">
                            @if ($isEnabled)
                                <form action="{{ route('servers.enter', $server['id']) }}" method="POST">
                                    @csrf
                                    <button type="submit" @class([
                                        'btn w-full font-bold text-sm py-3 flex items-center justify-center gap-2 cursor-pointer shadow-lg transition-all',
                                        'btn-primary shadow-emerald-500/20' => $isActive,
                                        'btn-accent shadow-[#1f6feb]/20' => !$isActive,
                                    ])>
                                        <x-icon name="zap" class="w-4 h-4" />
                                        <span>{{ $isActive ? 'Войти в панель управления' : 'Активировать и войти' }}</span>
                                        <x-icon name="arrow-right" class="w-4 h-4 ml-1" />
                                    </button>
                                </form>
                            @else
                                <div class="p-2.5 rounded-xl bg-[#161b22] border border-[#30363d] text-center text-xs text-[#8b949e]">
                                    Сервер отключен в конфигурации
                                </div>
                            @endif

                            <div class="flex items-center gap-2 pt-2 border-t border-[#21262d]">
                                <button type="button" @click="openEdit({{ $server['id'] }})"
                                        class="btn btn-sm flex-1 bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] hover:text-[#58a6ff] border-[#30363d] hover:border-[#388bfd]/50 justify-center gap-1.5 text-xs font-semibold py-2 transition-all cursor-pointer">
                                    <x-icon name="edit" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                    <span>Изменить</span>
                                </button>

                                <form action="{{ route('servers.destroy', $server['id']) }}" method="POST" class="inline"
                                      onsubmit="return confirm(@js('Вы действительно хотите удалить сервер «'.($server['name'] ?: 'Без имени').'»? Все привязанные настройки будут сброшены.'))">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger px-3.5 py-2 text-xs font-semibold justify-center gap-1.5 transition-all cursor-pointer" title="Удалить сервер">
                                        <x-icon name="trash" class="w-3.5 h-3.5" />
                                        <span>Удалить</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== 5. СИСТЕМНЫЕ МЕТРИКИ И НАБЛЮДАЕМОСТЬ ХОСТА ===== --}}
    @if (!empty($hostMetrics))
        <div class="pt-6 border-t border-[#21262d] space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                    <x-icon name="activity" class="w-3.5 h-3.5 text-[#58a6ff]" />
                    <span>Инфраструктура хоста и системные ресурсы</span>
                </h3>
                <span class="text-[11px] text-emerald-400 font-semibold flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Службы стабильны
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="p-4 rounded-2xl border border-[#21262d] bg-[#0c1017] shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#8b949e]">Загрузка CPU</div>
                        <div class="text-xl font-bold text-[#e6edf3] mt-0.5">{{ $hostMetrics['cpu']['percent'] }}%</div>
                        <div class="text-[10px] text-[#8b949e] font-mono">LA: {{ $hostMetrics['cpu']['load_1m'] }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#1f6feb]/15 border border-[#1f6feb]/30 text-[#58a6ff] flex items-center justify-center">
                        <x-icon name="cpu" class="w-5 h-5" />
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-[#21262d] bg-[#0c1017] shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#8b949e]">Память (RAM)</div>
                        <div class="text-xl font-bold text-[#e6edf3] mt-0.5">{{ $hostMetrics['ram']['percent'] }}%</div>
                        <div class="text-[10px] text-[#8b949e] font-mono">{{ $hostMetrics['ram']['used_mb'] }} / {{ $hostMetrics['ram']['total_mb'] }} МБ</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 text-[#3fb950] flex items-center justify-center">
                        <x-icon name="layers" class="w-5 h-5" />
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-[#21262d] bg-[#0c1017] shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#8b949e]">Диск (NVMe/SSD)</div>
                        <div class="text-xl font-bold text-[#e6edf3] mt-0.5">{{ $hostMetrics['disk']['percent'] }}%</div>
                        <div class="text-[10px] text-[#8b949e] font-mono">Свободно: {{ $hostMetrics['disk']['free_gb'] }} ГБ</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 text-[#d29922] flex items-center justify-center">
                        <x-icon name="hard-drive" class="w-5 h-5" />
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-[#21262d] bg-[#0c1017] shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#8b949e]">Аптайм хоста</div>
                        <div class="text-xl font-bold text-[#e6edf3] mt-0.5 truncate">{{ $hostMetrics['uptime']['formatted'] }}</div>
                        <div class="text-[10px] text-emerald-400 font-medium">Uptime OK</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 text-[#a371f7] flex items-center justify-center">
                        <x-icon name="clock" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Модальное окно создания и редактирования сервера --}}
    @include('servers._modal')

    @push('scripts')
    <script>
        window.SERVERS = @json($servers);
        window.PLUGINS_CONFIG = @json($pluginsConfig ?? []);
        window.LOLKA_CONFIG = @json($lolkaConfig ?? []);
        window.SERVER_STORE_URL = '{{ route('servers.store') }}';
        window.SERVER_UPDATE_BASE = '{{ url('servers') }}/';
    </script>
    @endpush
</div>
@endsection
