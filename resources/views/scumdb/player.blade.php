@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'Профиль игрока: ' . ($player['name'] ?? $steamId))



@section('content')
    <div class="flex items-center justify-between gap-3 mb-6 flex-wrap">
        <div class="flex-1 min-w-0">
            @include('scumdb._nav', ['active' => 'players'])
        </div>
        <div class="shrink-0 -mt-6 sm:mt-0 mb-6">
            <a href="{{ route('scumdb.players') }}" class="btn btn-sm bg-[#161b22] border-[#30363d] hover:border-[#58a6ff]/40 text-xs text-[#8b949e] hover:text-[#e6edf3] gap-2 transition-colors">
                <x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
                <span>Все игроки</span>
            </a>
        </div>
    </div>

    @php
        $tabs = [
            'inventory' => ['icon' => 'box', 'label' => 'Инвентарь', 'count' => isset($inventory) ? count($inventory) : null],
            'attributes' => ['icon' => 'activity', 'label' => 'Атрибуты', 'count' => null],
            'skills' => ['icon' => 'award', 'label' => 'Навыки', 'count' => isset($skills) ? count($skills) : null],
            'quick' => ['icon' => 'zap', 'label' => 'Быстрый доступ', 'count' => isset($quickSlots) ? count($quickSlots) : null],
        ];
        $lastLogin = !empty($player['lastLogin']) ? Carbon::parse($player['lastLogin']) : null;
        $lastLogout = !empty($player['lastLogout']) ? Carbon::parse($player['lastLogout']) : null;
        $neverPlayed = !$lastLogin;
        $online = !$neverPlayed && (!$lastLogout || $lastLogin->greaterThan($lastLogout));
        $initials = $player ? strtoupper(mb_substr($player['name'] ?? 'P', 0, 2)) : '??';
        $playerName = $player['name'] ?? 'Неизвестный игрок';
    @endphp

    <div class="space-y-6">
        <!-- ===== HERO: КАРТОЧКА ПРОФИЛЯ ИГРОКА ===== -->
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 sm:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/20 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-[#2ea043]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <!-- Аватар и базовая инфа -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start lg:items-center gap-5 w-full lg:w-auto">
                    <div class="relative shrink-0">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-gradient-to-br from-[#1f6feb] via-[#388bfd] to-[#2ea043] shadow-xl shadow-[#1f6feb]/25 ring-2 ring-[#58a6ff]/40 flex items-center justify-center overflow-hidden">
                            <span class="text-3xl sm:text-4xl font-black text-white tracking-wider">{{ $initials }}</span>
                            @if (!empty($steamAvatar))
                                <img src="{{ $steamAvatar }}" alt="{{ $playerName }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                            @endif
                        </div>
                        <div class="absolute -bottom-2 -right-2">
                            @if ($neverPlayed)
                                <span class="badge badge-gray px-2 py-0.5 text-[11px] shadow-md border border-[#30363d]">ОФФЛАЙН</span>
                            @elseif ($online)
                                <span class="badge badge-green px-2 py-0.5 text-[11px] shadow-md border border-[#2ea043]/50 animate-pulse">В ИГРЕ</span>
                            @else
                                <span class="badge badge-gray px-2 py-0.5 text-[11px] shadow-md border border-[#30363d]">ОФФЛАЙН</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
                            <h1 class="text-2xl sm:text-3xl font-black text-[#e6edf3] tracking-tight truncate max-w-full">{{ $playerName }}</h1>
                            @if (!empty($player['prisonerId']))
                                <span class="badge badge-blue text-[11px]">Prisoner #{{ $player['prisonerId'] }}</span>
                            @endif
                            @if (!empty($player['profileId']))
                                <span class="badge badge-gray text-[11px]">Profile #{{ $player['profileId'] }}</span>
                            @endif
                        </div>

                        <div class="mt-2.5 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#161b22] border border-[#30363d] mono text-xs text-[#58a6ff]">
                                <span>{{ $steamId }}</span>
                                <button type="button" @click="copyToClipboard(@js($steamId), $el)" class="text-[#8b949e] hover:text-[#e6edf3] transition-colors ml-1" title="Скопировать Steam ID"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                            </div>
                            <a href="https://steamcommunity.com/profiles/{{ $steamId }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#21262d] border border-[#30363d] text-xs text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] transition-colors" title="Открыть профиль Steam">
                                <span class="flex items-center gap-1.5"><x-icon name="server" class="w-3.5 h-3.5" /> Steam</span>
                            </a>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-x-5 gap-y-1.5 text-xs text-[#8b949e]">
                            @if ($lastLogin)
                                <span><span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-3 h-3" /> Вход:</span> <span class="text-[#e6edf3] font-medium">{{ $lastLogin->format('d.m.Y H:i') }}</span></span>
                            @else
                                <span><span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-3 h-3" /> Вход:</span> <span class="text-[#8b949e]">нет данных</span></span>
                            @endif
                            @if ($lastLogout)
                                <span><span class="inline-flex items-center gap-1"><x-icon name="logout" class="w-3 h-3" /> Выход:</span> <span class="text-[#e6edf3] font-medium">{{ $lastLogout->format('d.m.Y H:i') }}</span></span>
                            @endif
                            @if ($online)
                                <span class="text-[#3fb950] font-semibold flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-ping"></span>
                                    Сессия активна
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Быстрые балансы (Карточки) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto lg:min-w-[420px]">
                    <div class="rounded-xl border border-[#d29922]/30 bg-[#d29922]/10 p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-xs text-[#d29922] font-semibold mb-1.5">
                            <span>Золото (банк)</span>
                            <x-icon name="coins" class="w-4 h-4 text-[#d29922]" />
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-[#e6edf3] tabular-nums">
                            {{ number_format($wallet['goldBalance'] ?? 0, 0, ',', ' ') }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#2ea043]/30 bg-[#2ea043]/10 p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-xs text-[#3fb950] font-semibold mb-1.5">
                            <span>Деньги (банк)</span>
                            <x-icon name="coins" class="w-4 h-4 text-[#3fb950]" />
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-[#e6edf3] tabular-nums">
                            {{ number_format($wallet['normalBalance'] ?? 0, 0, ',', ' ') }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#a371f7]/30 bg-[#a371f7]/10 p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-xs text-[#a371f7] font-semibold mb-1.5">
                            <span>Слава (Fame)</span>
                            <x-icon name="trophy" class="w-4 h-4 text-[#a371f7]" />
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-[#e6edf3] tabular-nums">
                            {{ number_format($player['famePoints'] ?? 0, 0, ',', ' ') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== БЛОК ДЕЙСТВИЙ (RCON & ПАНЕЛЬ УПРАВЛЕНИЯ) ===== -->
        @php
            $btnAction = 'inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border transition-all shadow-sm active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none';
            $btnNeutral = $btnAction.' border-[#30363d] bg-[#161b22] text-[#e6edf3] hover:bg-[#21262d] hover:border-[#58a6ff] hover:text-[#58a6ff]';
            $btnGreen = $btnAction.' border-[#2ea043]/50 bg-[#2ea043]/20 text-[#3fb950] hover:bg-[#2ea043]/30 hover:border-[#2ea043]';
            $btnAmber = $btnAction.' border-[#d29922]/50 bg-[#d29922]/20 text-[#d29922] hover:bg-[#d29922]/30 hover:border-[#d29922]';
            $btnRed = $btnAction.' border-[#f85149]/50 bg-[#f85149]/20 text-[#f85149] hover:bg-[#f85149]/30 hover:border-[#f85149]';
            $btnPurple = $btnAction.' border-[#a371f7]/50 bg-[#a371f7]/20 text-[#a371f7] hover:bg-[#a371f7]/30 hover:border-[#a371f7]';
            $btnBlue = $btnAction.' border-[#388bfd]/50 bg-[#1f6feb]/20 text-[#58a6ff] hover:bg-[#1f6feb]/30 hover:border-[#388bfd]';
        @endphp

        <div class="panel overflow-hidden" x-data="playerActions('{{ $steamId }}', '{{ route('players.action') }}', '{{ csrf_token() }}', '{{ route('players.vehicles', $steamId) }}')"
             @keydown.escape.window="open = ''">
            <div class="flex items-center justify-between px-5 py-4 bg-[#0d1117] border-b border-[#30363d]">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#2ea043]/15 border border-[#2ea043]/40 flex items-center justify-center text-[#3fb950] shrink-0"><x-icon name="zap" class="w-4 h-4" /></div>
                    <div>
                        <div class="text-sm font-bold text-[#e6edf3] leading-tight">Панель действий</div>
                        <div class="text-[11px] text-[#8b949e]">RCON команды, выдача ресурсов, телепорт и администрирование</div>
                    </div>
                </div>
                <div x-show="busy" x-cloak class="flex items-center gap-1.5 text-xs text-[#58a6ff]">
                    <span class="w-3.5 h-3.5 border-2 border-[#58a6ff] border-t-transparent rounded-full animate-spin"></span>
                    <span>Выполняется…</span>
                </div>
            </div>

            <div class="p-5 space-y-5">
                <!-- Статус выполнения команды -->
                <div x-show="notice" x-cloak class="rounded-xl border px-4 py-3 text-sm flex items-center justify-between gap-3 shadow-md"
                     :class="notice && notice.ok === null ? 'border-[#388bfd]/50 bg-[#1f6feb]/10 text-[#58a6ff]' : (notice && notice.ok ? 'border-[#2ea043]/60 bg-[#2ea043]/15 text-[#3fb950]' : 'border-[#f85149]/60 bg-[#f85149]/15 text-[#f85149]')">
                    <div class="flex items-center gap-2 font-medium" x-text="notice && notice.text"></div>
                    <button type="button" @click="notice = null" class="text-xs opacity-70 hover:opacity-100"><x-icon name="x" class="w-4 h-4" /></button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <!-- Категория 1: Информация -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#58a6ff] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="file-text" class="w-4 h-4 text-[#58a6ff]" /> Информация
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="{{ $btnBlue }}" onclick="openModal(@js(route('players.whois', $steamId)), @js('Whois: '.($player['name'] ?? $steamId)), 3)"><x-icon name="terminal" class="w-3.5 h-3.5 inline mr-1 text-[#58a6ff]" /> Whois</button>
                            <button type="button" class="{{ $btnBlue }}" onclick="openModal(@js(route('plugins.rewards.player', $steamId)), @js('Рейтинг: '.($player['name'] ?? $steamId)))"><x-icon name="trophy" class="w-3.5 h-3.5 inline mr-1 text-amber-400" /> Рейтинг</button>
                            <button type="button" class="{{ $btnNeutral }}" onclick="openModal(@js(route('wargm.deliveries', $steamId)), @js('WARGM выдачи: '.($player['name'] ?? $steamId)))"><x-icon name="package" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Выдачи</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('wargm_check')" :disabled="busy"><x-icon name="refresh-cw" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> WARGM чек</button>
                        </div>
                    </div>

                    <!-- Категория 2: Выдача ресурсов -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#3fb950] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="gift" class="w-4 h-4 text-[#3fb950]" /> Выдача ресурсов
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('give_money')"><x-icon name="coins" class="w-3.5 h-3.5 inline mr-1 text-[#3fb950]" /> Деньги</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('give_gold')"><x-icon name="award" class="w-3.5 h-3.5 inline mr-1 text-amber-400" /> Золото</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('give_fame')"><x-icon name="sparkles" class="w-3.5 h-3.5 inline mr-1 text-yellow-400" /> Слава</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('spawn_item')"><x-icon name="box" class="w-3.5 h-3.5 inline mr-1 text-[#3fb950]" /> Предмет</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('chest_full')"><x-icon name="package" class="w-3.5 h-3.5 inline mr-1 text-[#3fb950]" /> Ящик</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('spawn_vehicle')"><x-icon name="car" class="w-3.5 h-3.5 inline mr-1 text-[#3fb950]" /> Транспорт</button>
                            <button type="button" class="{{ $btnGreen }}" @click="openAction('spawn_npc')"><x-icon name="target" class="w-3.5 h-3.5 inline mr-1 text-[#3fb950]" /> NPC / Звери</button>
                        </div>
                        @if (!empty($packsEnabled))
                            <button type="button" class="{{ $btnGreen }} w-full mt-1" @click="run('starter_pack')" :disabled="busy"><x-icon name="gift" class="w-4 h-4 inline mr-1 text-[#3fb950]" /> Выдать стартовый набор</button>
                        @endif
                    </div>

                    <!-- Категория 3: Статус и Режимы -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#d29922] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="shield" class="w-4 h-4 text-[#d29922]" /> Режимы игрока
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="{{ $btnAmber }}" @click="run('god_mode', { enabled: true })" :disabled="busy"><x-icon name="shield" class="w-3.5 h-3.5 inline mr-1 text-[#d29922]" /> Godmode ВКЛ</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('god_mode', { enabled: false })" :disabled="busy"><x-icon name="shield" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Godmode ВЫКЛ</button>
                            <button type="button" class="{{ $btnAmber }}" @click="run('immortality', { enabled: true })" :disabled="busy"><x-icon name="zap" class="w-3.5 h-3.5 inline mr-1 text-[#d29922]" /> Бессмертие ВКЛ</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('immortality', { enabled: false })" :disabled="busy"><x-icon name="zap" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Бессмертие ВЫКЛ</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('show_nameplates')" :disabled="busy"><x-icon name="eye" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Ники игроков</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('show_player_info')" :disabled="busy"><x-icon name="info" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Инфо игрока</button>
                        </div>
                    </div>

                    <!-- Категория 4: Управление и телепорт -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#f85149] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="crosshair" class="w-4 h-4 text-[#f85149]" /> Управление & Телепорт
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <button type="button" class="{{ $btnRed }}" @click="run('suicide')" :disabled="busy"><x-icon name="skull" class="w-3.5 h-3.5 inline mr-1 text-[#f85149]" /> Убить</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('unstuck')" :disabled="busy"><x-icon name="life-buoy" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Unstuck</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="run('silence')" :disabled="busy"><x-icon name="volume-x" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Silence</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="openAction('knockout')"><x-icon name="bell" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Нокаут</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="openAction('teleport_to')"><x-icon name="map" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> Телепорт XYZ</button>
                            <button type="button" class="{{ $btnNeutral }}" @click="openAction('teleport_to_vehicle')"><x-icon name="car" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> К транспорту</button>
                        </div>
                    </div>

                    <!-- Категория 5: Связь и Чат -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#58a6ff] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="message-square" class="w-4 h-4 text-[#58a6ff]" /> Сообщения & Уведомления
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" class="{{ $btnBlue }}" @click="openAction('announce')"><x-icon name="broadcast" class="w-3.5 h-3.5 inline mr-1 text-[#58a6ff]" /> Анонс</button>
                            <button type="button" class="{{ $btnBlue }}" @click="openAction('notify')"><x-icon name="bell" class="w-3.5 h-3.5 inline mr-1 text-[#58a6ff]" /> Нотис</button>
                            <button type="button" class="{{ $btnBlue }}" @click="openAction('chat')"><x-icon name="message-square" class="w-3.5 h-3.5 inline mr-1 text-[#58a6ff]" /> Чат</button>
                        </div>
                    </div>

                    <!-- Категория 6: Дропы -->
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 p-3.5 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#a371f7] uppercase tracking-wider pb-1 border-b border-[#21262d]">
                            <x-icon name="parachute" class="w-4 h-4 text-[#a371f7]" /> Дропы
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="{{ $btnPurple }}" @click="run('airdrop_at')" :disabled="busy"><x-icon name="parachute" class="w-3.5 h-3.5 inline mr-1 text-[#a371f7]" /> Аирдроп</button>
                            <button type="button" class="{{ $btnPurple }}" @click="run('cargo_drop')" :disabled="busy"><x-icon name="package" class="w-3.5 h-3.5 inline mr-1 text-[#a371f7]" /> Cargo drop</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Инлайн-модалка действий -->
            <template x-if="open">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4" x-cloak>
                    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="close()"></div>
                    <div class="relative w-full max-w-xl flex flex-col rounded-2xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-visible transition-all duration-200"
                         :class="['spawn_item', 'chest_full', 'spawn_vehicle', 'spawn_npc', 'teleport_to'].includes(open) ? 'min-h-[500px] sm:min-h-[520px]' : ''">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-[#30363d] bg-[#0d1117] rounded-t-2xl">
                            <div class="font-bold text-[#e6edf3] text-base" x-text="title()">…</div>
                            <button type="button" class="w-8 h-8 rounded-lg bg-[#21262d] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] flex items-center justify-center" @click="close()"><x-icon name="x" class="w-4 h-4" /></button>
                        </div>
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <template x-if="open === 'spawn_item'">
                                <div class="space-y-1.5" @catalog-selected.stop="item = $event.detail.item.id">
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Выберите предмет из каталога</label>
                                    <x-catalog-picker type="item" name="item" model="item" placeholder="Поиск предмета по названию или ID..." />
                                </div>
                            </template>
                            <template x-if="open === 'give_money' || open === 'give_gold' || open === 'give_fame' || open === 'spawn_item'">
                                <div>
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5" x-text="open === 'spawn_item' ? 'Количество предметов' : 'Сумма'"></label>
                                    <input type="number" x-model="amount" class="input w-full" step="any" min="1">
                                </div>
                            </template>
                            <template x-if="open === 'chest_full'">
                                <div class="space-y-3" @catalog-selected.stop="item = $event.detail.item.id">
                                    <div>
                                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Тип ящика</label>
                                        <select x-model="chestItem" class="input w-full">
                                            <option value="Improvised_Metal_Chest">Металлический ящик (Improvised Metal Chest)</option>
                                            <option value="Improvised_Wooden_Chest">Деревянный ящик (Improvised Wooden Chest)</option>
                                            <option value="Improvised_Wardrobe">Шкаф (Improvised Wardrobe)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Предмет для наполнения ящика</label>
                                        <x-catalog-picker type="item" name="item" model="item" placeholder="Выберите предмет из каталога..." />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Количество предметов</label>
                                        <input type="number" x-model="count" class="input w-full" min="1" max="100">
                                    </div>
                                </div>
                            </template>
                            <template x-if="open === 'spawn_vehicle'">
                                <div class="space-y-1.5" @catalog-selected.stop="vehicle = $event.detail.item.id">
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Выберите транспорт из каталога</label>
                                    <x-catalog-picker type="vehicle" name="vehicle" model="vehicle" placeholder="Поиск авто, мотоцикла, лодки..." />
                                </div>
                            </template>
                            <template x-if="open === 'spawn_npc'">
                                <div class="space-y-1.5" @catalog-selected.stop="item = $event.detail.item.id">
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Выберите существо или NPC</label>
                                    <x-catalog-picker type="npc" name="item" model="item" placeholder="Поиск медведя, волка, охранника, бродяги..." />
                                </div>
                            </template>
                            <template x-if="open === 'knockout'">
                                <div>
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Длительность нокаута (секунд)</label>
                                    <input type="number" x-model="seconds" class="input w-full" min="1" max="3600">
                                </div>
                            </template>
                            <template x-if="open === 'announce' || open === 'notify' || open === 'chat'">
                                <div>
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5" x-text="open === 'announce' ? 'Текст глобального объявления' : 'Текст сообщения'"></label>
                                    <textarea x-model="message" rows="3" class="input mono w-full" placeholder="Введите текст сообщения…"></textarea>
                                </div>
                            </template>
                            <template x-if="open === 'notify'">
                                <div>
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Тип уведомления</label>
                                    <select x-model="type" class="input w-full">
                                        <option :value="1">1 — Стандартное</option>
                                        <option :value="2">2 — Системное</option>
                                        <option :value="3">3 — Предупреждение</option>
                                        <option :value="4">4 — Критическое</option>
                                    </select>
                                </div>
                            </template>
                            <template x-if="open === 'chat'">
                                <div>
                                    <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Цвет текста в чате</label>
                                    <select x-model="color" class="input w-full">
                                        <option value="White">Белый</option>
                                        <option value="Yellow">Жёлтый</option>
                                        <option value="Red">Красный</option>
                                        <option value="Green">Зелёный</option>
                                        <option value="Blue">Синий</option>
                                        <option value="Orange">Оранжевый</option>
                                    </select>
                                </div>
                            </template>
                            <template x-if="open === 'teleport_to'">
                                <div class="space-y-3" @catalog-selected.stop="x = $event.detail.item.x; y = $event.detail.item.y; z = $event.detail.item.z">
                                    <div>
                                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Выбрать точку на карте (POI / Бункер / Город)</label>
                                        <x-catalog-picker type="marker" name="marker" placeholder="Поиск бункера, пещеры, банкомата, города..." />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">Координаты назначения (X, Y, Z)</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <input type="number" x-model="x" class="input mono" placeholder="X" step="any">
                                            <input type="number" x-model="y" class="input mono" placeholder="Y" step="any">
                                            <input type="number" x-model="z" class="input mono" placeholder="Z" step="any">
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="open === 'teleport_to_vehicle'">
                                <div>
                                    <div class="text-xs text-[#8b949e] mb-2" x-text="vehiclesLoading ? 'Загрузка транспорта…' : (vehicleList.length ? vehicleList.length + ' ТС найдено у игрока' : '')"></div>
                                    <div class="max-h-64 overflow-y-auto space-y-2 pr-1" x-show="!vehiclesLoading" x-cloak>
                                        <template x-for="v in vehicleList" :key="v.entityId">
                                            <button type="button"
                                                    class="w-full text-left rounded-xl border border-[#30363d] bg-[#0d1117] hover:bg-[#21262d] hover:border-[#58a6ff] p-3 transition-all disabled:opacity-50"
                                                    @click="teleportToVehicle(v)" :disabled="busy">
                                                <div class="text-sm text-[#e6edf3] font-semibold" x-text="v.name">…</div>
                                                <div class="text-[11px] text-[#8b949e] mono mt-0.5" x-text="'ID #' + v.entityId + ' · X ' + Math.round(v.x) + ' Y ' + Math.round(v.y) + ' Z ' + Math.round(v.z)">…</div>
                                            </button>
                                        </template>
                                        <div x-show="!vehiclesLoading && vehicleList.length === 0" class="text-sm text-[#f85149] text-center py-6 flex items-center justify-center gap-1.5"><x-icon name="alert-triangle" class="w-4 h-4" /><span>У игрока не найден транспорт</span></div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="open !== 'teleport_to_vehicle'">
                                <button type="button" class="btn btn-primary w-full justify-center py-2.5 text-sm inline-flex items-center gap-2" @click="submitModal()" :disabled="busy"><x-icon name="zap" class="w-4 h-4" /><span x-text="busy ? 'Отправка команды…' : 'Выполнить команду'">Выполнить команду</span></button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ===== ВКЛАДКИ НАВИГАЦИИ ===== -->
        <div class="overflow-x-auto pb-1 -mx-2 px-2 scrollbar-thin">
            <div class="flex items-center gap-1.5 bg-[#161b22]/90 border border-[#30363d] rounded-xl p-1.5 min-w-max shadow-sm">
                @foreach ($tabs as $key => $t)
                    <a href="{{ route('scumdb.player', ['steamId' => $steamId, 'tab' => $key]) }}"
                       @class([
                           'flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs sm:text-sm font-semibold transition-all whitespace-nowrap',
                           'bg-[#1f6feb] text-white shadow-md shadow-[#1f6feb]/30' => $tab === $key,
                           'text-[#8b949e] hover:text-[#e6edf3] hover:bg-[#21262d]' => $tab !== $key
                       ])>
                        <x-icon :name="$t['icon']" class="w-4 h-4 shrink-0" />
                        <span>{{ $t['label'] }}</span>
                        @if ($t['count'] !== null)
                            <span @class([
                                'px-1.5 py-0.5 rounded-full text-[10px] font-bold',
                                'bg-white/20 text-white' => $tab === $key,
                                'bg-[#0d1117] text-[#8b949e] border border-[#30363d]' => $tab !== $key
                            ])>{{ $t['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <!-- ===== TAB 1: ИНВЕНТАРЬ ===== -->
        @if ($tab === 'inventory')
            <div class="panel p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-[#21262d]">
                    <div>
                        <h2 class="text-lg font-bold text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="backpack" class="w-5 h-5 text-[#3fb950]" />
                            <span>Инвентарь персонажа</span>
                        </h2>
                        <div class="text-xs text-[#8b949e] mt-0.5">Все экипированные и переносимые предметы из базы SCUM.db</div>
                    </div>
                    <span class="badge badge-blue text-xs font-semibold px-3 py-1">Всего предметов: {{ count($inventory) }}</span>
                </div>

                @if (empty($inventory))
                    <div class="text-center py-16">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-[#30363d]/40 flex items-center justify-center text-[#8b949e]"><x-icon name="backpack" class="w-8 h-8 opacity-40" /></div>
                        <div class="text-base font-semibold text-[#e6edf3]">Инвентарь персонажа пуст</div>
                        <div class="text-xs text-[#8b949e] mt-1">Персонаж не экипирован предметами или данные ещё не синхронизированы из SCUM.db.</div>
                    </div>
                @else
                    @php
                        $groupMeta = [
                            'equipped' => ['shirt', 'Экипировка на теле', 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30'],
                            'hands' => ['user', 'В руках персонажа', 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30'],
                            'root' => ['backpack', 'Снаряжение и рюкзак', 'bg-[#3fb950]/15 text-[#3fb950] border-[#2ea043]/30'],
                            'container' => ['box', 'Внутри контейнеров и карманов', 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30'],
                        ];
                    @endphp
                    <div class="space-y-7">
                        @foreach (['equipped', 'hands', 'root', 'container'] as $gk)
                            @php
                                $group = collect($inventory)->filter(fn($i) => ($i['slotKind'] ?? 'container') === $gk)->values();
                            @endphp
                            @if ($group->isEmpty())
                                @continue
                            @endif
                            <div>
                                <div class="flex items-center gap-2.5 mb-3.5">
                                    <span class="w-7 h-7 rounded-lg flex items-center justify-center border {{ $groupMeta[$gk][2] }}"><x-icon :name="$groupMeta[$gk][0]" class="w-3.5 h-3.5" /></span>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#e6edf3]">{{ $groupMeta[$gk][1] }}</h3>
                                    <span class="badge badge-gray text-[11px]">{{ $group->count() }} шт.</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    @foreach ($group as $item)
                                        @php
                                            $id = $item['itemId'] ?: ($item['itemClass'] ?: '?');
                                            $where = $item['depth'] > 0
                                                ? 'слот ' . $item['slot'] . ' (глубина ' . $item['depth'] . ')'
                                                : ($item['slotKind'] === 'equipped' ? 'экипировано'
                                                    : ($item['slotKind'] === 'hands' ? 'в руках'
                                                        : 'слот ' . $item['slot']));
                                            $count = collect($item['components'] ?? [])->firstWhere('name', 'Count');
                                        @endphp
                                        <div class="rounded-xl border border-[#30363d] bg-[#0d1117] hover:border-[#58a6ff]/70 hover:bg-[#161b22] p-3.5 transition-all shadow-sm flex flex-col justify-between group">
                                            <div>
                                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                                    <div class="min-w-0">
                                                        <div class="mono text-xs sm:text-sm font-bold text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors break-words leading-tight" title="{{ $id }}">
                                                            {{ $id }}
                                                        </div>
                                                    </div>
                                                    @if (!empty($item['hasInventory']))
                                                        <span class="badge badge-blue text-[10px] shrink-0 inline-flex items-center gap-1" title="Содержит вложенные предметы"><x-icon name="box" class="w-3 h-3" /><span>Контейнер</span></span>
                                                    @endif
                                                </div>

                                                <div class="text-[11px] text-[#8b949e] flex items-center gap-1.5 flex-wrap">
                                                    <span>{{ $where }}</span>
                                                    @if ($count && $count['value'] !== null)
                                                        <span class="font-bold text-[#3fb950]">×{{ $count['value'] }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            @if (!empty($item['components']))
                                                <div class="mt-3 flex flex-wrap gap-1.5 border-t border-[#21262d] pt-2">
                                                    @foreach (collect($item['components'])->filter(fn($c) => $c['value'] !== null) as $c)
                                                        @if ($c['name'] === 'Count')
                                                            @continue
                                                        @endif
                                                        <span class="badge badge-gray text-[10px] mono">
                                                            {{ $c['name'] }}: <strong class="text-[#e6edf3]">{{ $c['value'] }}</strong>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- ===== TAB 2: АТРИБУТЫ ===== -->
        @if ($tab === 'attributes')
            <div class="panel p-5 sm:p-7">
                @php
                    $attrKeys = ['strength', 'constitution', 'dexterity', 'intelligence'];
                    $attrMax = ($attributeMax ?? []) + ['strength' => 8, 'constitution' => 5, 'dexterity' => 5, 'intelligence' => 5];
                    $disp = [];
                    $exact = [];
                    foreach ($attrKeys as $__k) {
                        $raw = (float)($attributes[$__k] ?? 0);
                        $exact[$__k] = $raw;
                        $disp[$__k] = min($attrMax[$__k], max(1, (int)floor($raw)));
                    }
                    $gender = (int)($attributes['gender'] ?? 0);
                    $genderLabel = $gender === 1 ? 'Женский' : ($gender === 2 ? 'Мужской' : 'Не задан');
                @endphp

                <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-[#21262d]">
                    <div>
                        <h2 class="text-lg font-bold text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="activity" class="w-4 h-4 text-[#58a6ff] inline-block mr-1.5" /> Характеристики и атрибуты
                        </h2>
                        <div class="text-xs text-[#8b949e] mt-0.5">Точные значения параметров персонажа из базы SCUM.db с возможностью редактирования</div>
                    </div>
                    <span class="badge badge-blue text-xs font-semibold px-3 py-1">Лимиты: Сила ≤ {{ $attrMax['strength'] }}, ост. ≤ {{ $attrMax['constitution'] }}</span>
                </div>

                @if (!empty($attributes['error']))
                    <div class="rounded-xl border border-[#f85149]/50 bg-[#f85149]/10 p-4 text-sm text-[#f85149]">
                        {{ $attributes['error'] }} (hex: {{ $attributes['hex'] ?? '' }})
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach ([
                            'strength' => ['dumbbell', 'Сила', 'Strength', '#3fb950'],
                            'constitution' => ['activity', 'Выносливость', 'Constitution', '#d29922'],
                            'dexterity' => ['zap', 'Ловкость', 'Dexterity', '#58a6ff'],
                            'intelligence' => ['cpu', 'Интеллект', 'Intelligence', '#a371f7'],
                        ] as $key => [$icon, $label, $en, $color])
                            @php
                                $maxV = $attrMax[$key];
                                $rawVal = $exact[$key];
                                $pct = min(100, max(0, round(($rawVal / $maxV) * 100, 1)));
                            @endphp
                            <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 flex flex-col justify-between shadow-sm hover:border-[#58a6ff]/50 transition-all">
                                <div>
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-inner" style="background:{{ $color }}1f; border:1px solid {{ $color }}55;"><x-icon :name="$icon" class="w-5 h-5" style="color: {{ $color }}" /></div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-bold text-[#e6edf3] leading-tight truncate">{{ $label }}</div>
                                                <div class="text-[11px] text-[#8b949e] mono">{{ $en }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-xl font-black tabular-nums leading-none" style="color: {{ $color }}">{{ number_format($rawVal, 2) }}<span class="text-xs text-[#8b949e] font-normal">/{{ $maxV }}</span></div>
                                            <div class="text-[10px] text-[#8b949e] mono mt-1" title="Точное значение из базы">Точно: {{ number_format($rawVal, 2) }}</div>
                                        </div>
                                    </div>

                                    <!-- Прогресс-бар -->
                                    <div class="h-2 rounded-full bg-[#21262d] overflow-hidden mb-4">
                                        <div class="h-full rounded-full transition-all duration-300" style="width: {{ $pct }}%; background: {{ $color }}"></div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('scumdb.attributes', $steamId) }}" class="flex items-center gap-1.5 pt-2 border-t border-[#21262d]">
                                    @csrf
                                    <input type="number" name="{{ $key }}" value="{{ number_format($rawVal, 2, '.', '') }}" min="0.5" max="{{ $maxV }}"
                                           step="0.01" inputmode="decimal" class="input w-full text-center mono font-bold text-sm"
                                           aria-label="{{ $label }}">
                                    <button type="submit" class="btn btn-sm btn-accent whitespace-nowrap shrink-0 inline-flex items-center gap-1.5"><x-icon name="check" class="w-3.5 h-3.5" /><span>Сохранить</span></button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <!-- Доп параметры -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                        <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 text-center">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center text-[#d29922]"><x-icon name="calendar" class="w-5 h-5" /></div>
                            <div class="text-xs text-[#8b949e] uppercase tracking-wider font-semibold">Возраст персонажа</div>
                            <div class="text-xl font-black text-[#e6edf3] mt-1">{{ $attributes['age'] ?? 0 }} лет</div>
                        </div>

                        <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 text-center">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-[#58a6ff]/15 border border-[#58a6ff]/30 flex items-center justify-center text-[#58a6ff]"><x-icon name="user" class="w-5 h-5" /></div>
                            <div class="text-xs text-[#8b949e] uppercase tracking-wider font-semibold">Пол персонажа</div>
                            <div class="text-xl font-black text-[#e6edf3] mt-1">{{ $genderLabel }}</div>
                        </div>

                        <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 text-center">
                            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center text-[#a371f7]"><x-icon name="target" class="w-5 h-5" /></div>
                            <div class="text-xs text-[#8b949e] uppercase tracking-wider font-semibold">Точные значения (Float)</div>
                            <div class="mono text-xs font-bold text-[#58a6ff] mt-2">
                                {{ implode(' · ', array_map(fn ($k) => number_format($exact[$k], 2), $attrKeys)) }}
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#30363d] bg-[#161b22]/70 p-4 text-xs text-[#8b949e] mt-6 leading-relaxed flex items-start gap-2.5">
                        <x-icon name="info" class="w-4 h-4 text-[#58a6ff] shrink-0 mt-0.5" />
                        <div>
                            Изменения отправляются на игровой сервер мгновенно командой RCON <code class="mono text-[#58a6ff]">SetAttributes</code>. Если RCON временно недоступен — атрибуты записываются в локальную копию <code class="mono text-[#58a6ff]">SCUM.db</code> и применяются при следующем рестарте игрового сервера.
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- ===== TAB 3: НАВЫКИ ===== -->
        @if ($tab === 'skills')
            @php $maxS = $maxSkillLevel ?? 4; @endphp
            <div class="panel p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-[#21262d]">
                    <div>
                        <h2 class="text-lg font-bold text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="book-open" class="w-5 h-5 text-[#a371f7]" />
                            <span>Навыки персонажа</span>
                        </h2>
                        <div class="text-xs text-[#8b949e] mt-0.5">Точные уровни прокачки и очки опыта навыков из SCUM.db</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="badge badge-blue text-xs font-semibold px-3 py-1.5">Всего навыков: {{ count($skills) }} (макс. {{ $maxS }})</span>
                        <form method="POST" action="{{ route('scumdb.skills.max', $steamId) }}">
                            @csrf
                            <input type="hidden" name="level" value="{{ $maxS }}">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold border border-[#a371f7]/60 bg-gradient-to-r from-[#a371f7]/20 to-[#8957e5]/20 text-[#d2a8ff] hover:from-[#a371f7]/30 hover:to-[#8957e5]/30 hover:border-[#a371f7] hover:shadow-md hover:shadow-[#a371f7]/20 transition-all active:scale-[0.98] cursor-pointer">
                                <x-icon name="zap" class="w-4 h-4 text-[#d2a8ff]" />
                                <span>Прокачать все навыки (MAX {{ $maxS }})</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3.5">
                    @foreach ($skills as $s)
                        @php
                            $xp = (float)($s['skillExperience'] ?? 0);
                            $lvl = min($maxS, max(0, (int)($s['skillLevel'] ?? 0)));
                            if ($xp >= 10000000) {
                                $lvl = 4;
                            }
                            $levelLabels = [0 => 'Нет', 1 => 'Базовый', 2 => 'Средний', 3 => 'Продвинутый', 4 => 'Эксперт'];

                            // Точный процент прогресса по шкале SCUM
                            if ($lvl >= 4 || $xp >= 10000000) {
                                $tierPct = 100;
                                $totalPct = 100;
                            } elseif ($lvl === 3) {
                                $tierPct = min(100, max(0, round((($xp - 1000000) / 9000000) * 100, 1)));
                                $totalPct = min(100, max(0, round(75 + ($tierPct * 0.25), 1)));
                            } elseif ($lvl === 2) {
                                $tierPct = min(100, max(0, round((($xp - 100000) / 900000) * 100, 1)));
                                $totalPct = min(100, max(0, round(50 + ($tierPct * 0.25), 1)));
                            } elseif ($lvl === 1) {
                                $tierPct = min(100, max(0, round((($xp - 10000) / 90000) * 100, 1)));
                                $totalPct = min(100, max(0, round(25 + ($tierPct * 0.25), 1)));
                            } else {
                                $tierPct = min(100, max(0, round(($xp / 10000) * 100, 1)));
                                $totalPct = min(100, max(0, round($tierPct * 0.25, 1)));
                            }
                        @endphp
                        <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 flex flex-col justify-between hover:border-[#58a6ff]/50 transition-all shadow-sm">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-[#e6edf3] truncate" title="{{ $s['skillName'] }}">
                                            {{ $s['skillLabel'] ?? $s['skillName'] }}
                                        </div>
                                        <div class="text-[11px] text-[#8b949e] mono truncate">{{ $s['skillEn'] ?? $s['skillName'] }}</div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="badge badge-blue text-[10px] font-bold">{{ $lvl }} / {{ $maxS }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-[#8b949e] mb-1.5">
                                    <span class="font-medium text-[#c9d1d9]">{{ $levelLabels[$lvl] ?? 'Уровень ' . $lvl }}</span>
                                    <span class="mono font-bold text-[#58a6ff]">{{ number_format($xp, 2, '.', ' ') }} XP</span>
                                </div>

                                <div class="h-2 bg-[#21262d] rounded-full overflow-hidden mb-3">
                                    <div class="h-full rounded-full bg-gradient-to-r from-[#1f6feb] to-[#58a6ff] transition-all duration-300" style="width: {{ $totalPct }}%"></div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('scumdb.skill', $steamId) }}" class="flex items-center gap-1.5 pt-2.5 border-t border-[#21262d]">
                                @csrf
                                <input type="hidden" name="name" value="{{ $s['skillName'] }}">
                                <div class="flex items-center gap-1 flex-1">
                                    @for ($i = 0; $i <= $maxS; $i++)
                                        <button type="submit" name="level" value="{{ $i }}"
                                                @class([
                                                    'flex-1 py-1 rounded text-xs font-bold transition-colors border',
                                                    'bg-[#1f6feb] border-[#388bfd] text-white shadow-sm' => $lvl === $i,
                                                    'bg-[#161b22] border-[#30363d] text-[#8b949e] hover:bg-[#21262d] hover:text-[#e6edf3]' => $lvl !== $i
                                                ])>{{ $i }}</button>
                                    @endfor
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-[#30363d] bg-[#161b22]/70 p-4 text-xs text-[#8b949e] mt-6 flex items-start gap-2.5">
                    <x-icon name="info" class="w-4 h-4 text-[#58a6ff] shrink-0 mt-0.5" />
                    <div>
                        Максимальный уровень навыка в игре SCUM — <strong>{{ $maxS }}</strong> (Эксперт). Нажмите на цифру 0, 1, 2, 3 или 4 под нужным навыком для мгновенного изменения уровня через RCON (<code class="mono text-[#58a6ff]">SetSkillLevel</code>), либо используйте кнопку <strong>«Прокачать все навыки (MAX {{ $maxS }})»</strong> в заголовке для мгновенной прокачки всех навыков персонажа.
                    </div>
                </div>
            </div>
        @endif

        <!-- ===== TAB 4: БЫСТРЫЙ ДОСТУП ===== -->
        @if ($tab === 'quick')
            <div class="panel p-5 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-[#21262d]">
                    <div>
                        <h2 class="text-lg font-bold text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="zap" class="w-5 h-5 text-[#d29922]" />
                            <span>Слоты быстрого доступа (Quick Slots)</span>
                        </h2>
                        <div class="text-xs text-[#8b949e] mt-0.5">Предметы, назначенные на горячие клавиши 1-9 и 0</div>
                    </div>
                    <span class="badge badge-blue text-xs font-semibold px-3 py-1">Назначено: {{ count($quickSlots) }}</span>
                </div>

                @if (empty($quickSlots))
                    <div class="text-center py-16">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-[#30363d]/40 flex items-center justify-center text-[#8b949e]"><x-icon name="zap" class="w-8 h-8 opacity-40" /></div>
                        <div class="text-base font-semibold text-[#e6edf3]">Горячие клавиши не назначены</div>
                        <div class="text-xs text-[#8b949e] mt-1">У персонажа нет предметов в панели быстрого доступа.</div>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-10 gap-3">
                        @foreach ($quickSlots as $q)
                            @php $id = $q['itemId'] ?: ($q['itemClass'] ?: '?'); @endphp
                            <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-3.5 text-center hover:border-[#58a6ff] hover:bg-[#161b22] transition-all shadow-sm">
                                <div class="w-8 h-8 rounded-lg bg-[#d29922]/15 border border-[#d29922]/30 text-[#d29922] font-black text-sm flex items-center justify-center mx-auto mb-2">
                                    {{ $q['slotIndex'] }}
                                </div>
                                <div class="mono text-xs font-semibold text-[#58a6ff] break-all leading-tight">{{ $id }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection