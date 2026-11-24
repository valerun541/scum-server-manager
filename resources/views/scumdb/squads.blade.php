@extends('layouts.app')

@section('title', 'SCUM.db: Отряды')

@section('content')
    @include('scumdb._nav', ['active' => 'squads'])

    @php
        $grouped = collect($squads)->groupBy('squadId');
        $totalSquads = $grouped->count();
        $totalMembers = collect($squads)->filter(fn($m) => !empty($m['memberId']))->count();
        $topSquad = $grouped->first() ? $grouped->first()->first() : null;

        $rankLabels = [
            1 => 'Лидер',
            2 => 'Офицер',
            3 => 'Участник',
            4 => 'Рекрут',
        ];

        $rankIcons = [
            1 => 'crown',
            2 => 'shield',
            3 => 'user-check',
            4 => 'user',
        ];

        $rankBadgeClasses = [
            1 => 'bg-[#d29922]/20 text-[#f2cc60] border-[#d29922]/40 shadow-sm shadow-[#d29922]/10 font-bold',
            2 => 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 font-semibold',
            3 => 'bg-[#2ea043]/15 text-[#3fb950] border-[#2ea043]/30 font-medium',
            4 => 'bg-[#21262d] text-[#8b949e] border-[#30363d] font-normal',
        ];
    @endphp

    <div x-data="{
        search: '',
        matchesSquad(squadName, squadId, members) {
            if (!this.search || !this.search.trim()) return true;
            const q = this.search.toLowerCase().trim();
            if (String(squadName || '').toLowerCase().includes(q)) return true;
            if (String(squadId || '').includes(q)) return true;
            for (let m of members) {
                if (String(m.memberName || '').toLowerCase().includes(q)) return true;
                if (String(m.memberSteamId || '').includes(q)) return true;
            }
            return false;
        }
    }" class="space-y-6">

        {{-- ===== 1. HERO HEADER ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d]/80 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-5 sm:p-7 shadow-2xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#a371f7]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-20 w-72 h-72 rounded-full bg-[#1f6feb]/10 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-14 h-14 shrink-0 rounded-2xl bg-gradient-to-br from-[#a371f7] via-[#8957e5] to-[#1f6feb] p-[2px] shadow-lg shadow-[#a371f7]/25">
                        <div class="w-full h-full rounded-[14px] bg-[#0d1117] flex items-center justify-center text-[#58a6ff]"><x-icon name="shield" class="w-6 h-6" /></div>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-[#e6edf3] tracking-tight">
                                Клановые отряды и сквады
                            </h1>
                            <span class="badge badge-purple">SCUM.db Squads</span>
                        </div>
                        <p class="text-xs sm:text-sm text-[#8b949e]">
                            Иерархия рангов, списки участников, очки славы и балансы отрядов
                        </p>
                    </div>
                </div>

                @if ($available)
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0 pt-2 lg:pt-0">
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 text-[#d2a8ff] text-xs font-semibold">
                            <x-icon name="shield" class="w-4 h-4 text-[#58a6ff]" />
                            <span>Всего: {{ $totalSquads }} отрядов ({{ $totalMembers }} игроков)</span>
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== 2. KPI СТАТИСТИКА ===== --}}
        @if ($available)
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="panel p-4 flex items-center gap-3.5 bg-[#121824]/90 border-[#30363d]/80">
                    <div class="w-11 h-11 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center shrink-0 text-[#d2a8ff]"><x-icon name="shield" class="w-5 h-5" /></div>
                    <div class="min-w-0">
                        <div class="text-2xl font-extrabold text-[#e6edf3]">{{ $totalSquads }}</div>
                        <div class="text-xs text-[#8b949e] font-medium truncate">Отрядов в мире</div>
                    </div>
                </div>

                <div class="panel p-4 flex items-center gap-3.5 bg-[#121824]/90 border-[#30363d]/80">
                    <div class="w-11 h-11 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center shrink-0 text-[#58a6ff]"><x-icon name="users" class="w-5 h-5" /></div>
                    <div class="min-w-0">
                        <div class="text-2xl font-extrabold text-[#58a6ff]">{{ $totalMembers }}</div>
                        <div class="text-xs text-[#8b949e] font-medium truncate">Игроков в кланах</div>
                    </div>
                </div>

                <div class="panel p-4 flex items-center gap-3.5 bg-[#121824]/90 border-[#30363d]/80">
                    <div class="w-11 h-11 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center shrink-0 text-[#f2cc60]"><x-icon name="trophy" class="w-5 h-5" /></div>
                    <div class="min-w-0">
                        <div class="text-lg font-bold text-[#f2cc60] truncate">
                            {{ $topSquad ? ($topSquad['squadName'] ?: 'ID #'.$topSquad['squadId']) : '—' }}
                        </div>
                        <div class="text-xs text-[#8b949e] font-medium">Топ-1 отряд</div>
                    </div>
                </div>

                <div class="panel p-4 flex items-center gap-3.5 bg-[#121824]/90 border-[#30363d]/80">
                    <div class="w-11 h-11 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center text-xl shrink-0 text-[#3fb950]">
                        <x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-2xl font-extrabold text-[#3fb950]">
                            {{ $topSquad ? number_format((float)($topSquad['score'] ?? 0), 0, '.', ' ') : 0 }}
                        </div>
                        <div class="text-xs text-[#8b949e] font-medium">Макс. очки отряда</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== 3. ПОИСК / ФИЛЬТР ===== --}}
        @if ($available && $totalSquads > 0)
            <div class="flex items-center gap-3 bg-[#121824]/80 border border-[#30363d]/70 rounded-xl p-3">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#8b949e]"><x-icon name="search" class="w-4 h-4" /></span>
                    <input type="text" x-model="search" placeholder="Поиск отряда по имени, ID, имени игрока или SteamID..."
                           class="input pl-8 py-1.5 text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                    <button type="button" x-show="search" @click="search = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                </div>
            </div>
        @endif

        {{-- ===== 4. СОСТОЯНИЯ (ОШИБКА / ПУСТО / СПИСОК) ===== --}}
        @if (!$available)
            <div class="panel p-8 text-center border-dashed border-[#30363d]">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mx-auto mb-3 text-amber-400"><x-icon name="alert-triangle" class="w-6 h-6" /></div>
                <h3 class="text-base font-bold text-[#e6edf3] mb-1">SCUM.db временно недоступен</h3>
                <p class="text-xs text-[#8b949e] w-full mb-4">
                    Синхронизируйте базу данных на главной странице SCUM.db, чтобы просмотреть составы отрядов.
                </p>
                <a href="{{ route('scumdb.index') }}" class="btn btn-accent btn-sm">Перейти к синхронизации</a>
            </div>
        @elseif ($totalSquads === 0)
            <div class="panel p-8 text-center border-dashed border-[#30363d]">
                <div class="w-12 h-12 rounded-2xl bg-[#58a6ff]/10 border border-[#58a6ff]/30 flex items-center justify-center mx-auto mb-3 text-[#58a6ff]"><x-icon name="shield" class="w-6 h-6" /></div>
                <h3 class="text-base font-bold text-[#e6edf3] mb-1">Отряды не найдены</h3>
                <p class="text-xs text-[#8b949e]">На сервере ещё не создано ни одного кланового отряда.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($grouped as $sid => $members)
                    @php
                        $first = $members->first();
                        $realMembers = $members->filter(fn($m) => !empty($m['memberId']));
                        $leader = $realMembers->firstWhere('memberRank', 1) ?? $realMembers->firstWhere('memberRank', 2);
                        $squadJson = $realMembers->values()->toArray();
                    @endphp

                    <div x-show="matchesSquad(@js($first['squadName']), @js($sid), @js($squadJson))"
                         class="panel overflow-hidden border border-[#30363d]/90 bg-[#121824]/90 shadow-xl rounded-2xl transition-all">
                        
                        {{-- Шапка отряда --}}
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-4 sm:p-5 bg-[#0e131f] border-b border-[#21262d]">
                            <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#a371f7]/30 to-[#1f6feb]/20 border border-[#a371f7]/40 flex items-center justify-center shrink-0 shadow-md shadow-[#a371f7]/15 text-[#d2a8ff]"><x-icon name="shield" class="w-6 h-6" /></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-extrabold text-base sm:text-lg text-[#e6edf3] truncate" title="{{ $first['squadName'] }}">
                                            {{ $first['squadName'] ?: 'Без названия' }}
                                        </h3>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-[#21262d] border border-[#30363d] text-[#8b949e]">
                                            ID #{{ $sid }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-[#8b949e] flex-wrap">
                                        @if ($leader)
                                            <span class="flex items-center gap-1">
                                                <x-icon name="crown" class="w-4 h-4 text-amber-400" />
                                                <span class="text-[#8b949e]">Лидер:</span>
                                                <span class="text-[#e6edf3] font-semibold">{{ $leader['memberName'] ?: '—' }}</span>
                                            </span>
                                            <span>•</span>
                                        @endif
                                        <span>Лимит состава: <b class="text-[#e6edf3] font-mono">{{ $first['memberLimit'] ?? 0 }}</b></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold bg-[#d29922]/15 border border-[#d29922]/30 text-[#f2cc60]">
                                    <x-icon name="trophy" class="w-3.5 h-3.5 text-amber-400" />
                                    <span>{{ number_format((float)($first['score'] ?? 0), 0, '.', ' ') }} очков</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-bold bg-[#238636]/15 border border-[#2ea043]/30 text-[#3fb950]">
                                    <x-icon name="users" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                    <span>{{ $realMembers->count() }} участников</span>
                                </span>
                            </div>
                        </div>

                        {{-- Сообщение отряда (если есть) --}}
                        @if (!empty($first['message']) || !empty($first['information']))
                            <div class="px-5 py-2.5 bg-[#090d14] border-b border-[#21262d] text-xs text-[#8b949e] flex items-center gap-2 italic">
                                <x-icon name="message-square" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                <span>{{ $first['message'] ?: $first['information'] }}</span>
                            </div>
                        @endif

                        {{-- Список участников: Мобильный вид (карточки) --}}
                        <div class="block lg:hidden divide-y divide-[#21262d]">
                            @forelse ($realMembers as $m)
                                <div class="p-4 space-y-3 bg-[#0d1117]/60">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs border {{ $rankBadgeClasses[$m['memberRank']] ?? 'bg-[#21262d] text-[#8b949e] border-[#30363d]' }}">
                                                <x-icon :name="$rankIcons[$m['memberRank']] ?? 'user'" class="w-3.5 h-3.5 shrink-0" />
                                                <span>{{ $rankLabels[$m['memberRank']] ?? 'Ранг '.$m['memberRank'] }}</span>
                                            </span>
                                            <a href="{{ route('scumdb.player', $m['memberSteamId']) }}" class="font-bold text-sm text-[#e6edf3] hover:text-[#58a6ff] transition-colors truncate">
                                                {{ $m['memberName'] ?: 'Без имени' }}
                                            </a>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between text-xs font-mono text-[#8b949e] bg-[#161b22] p-2 rounded-lg border border-[#30363d]/60">
                                        <span>{{ $m['memberSteamId'] }}</span>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="copyToClipboard('{{ $m['memberSteamId'] }}')" class="btn btn-xs px-1.5 py-0.5" title="Скопировать SteamID"><x-icon name="copy" class="w-3.5 h-3.5 text-[#8b949e] hover:text-[#58a6ff]" /></button>
                                            <a href="{{ route('players.whois', $m['memberSteamId']) }}" class="btn btn-xs text-[#58a6ff] px-1.5 py-0.5" title="Whois"><x-icon name="terminal" class="w-3.5 h-3.5 text-[#58a6ff]" /></a>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-xs font-mono pt-1">
                                        <div class="p-2 rounded-lg bg-[#090d14] border border-[#21262d]">
                                            <span class="text-[10px] text-[#8b949e] block">Слава:</span>
                                            <span class="text-[#58a6ff] font-bold">+{{ number_format((float)($m['famePoints'] ?? 0), 0, '.', ' ') }}</span>
                                        </div>
                                        <div class="p-2 rounded-lg bg-[#090d14] border border-[#21262d]">
                                            <span class="text-[10px] text-[#8b949e] block">Баланс:</span>
                                            <span class="text-[#3fb950] font-bold">${{ number_format((float)($m['moneyBalance'] ?? 0), 0, '.', ' ') }}</span>
                                            @if (!empty($m['goldBalance']))
                                                <span class="text-[#f2cc60] text-[11px] block"><x-icon name="coins" class="w-3 h-3 text-[#f2cc60] inline-block mr-1" />{{ number_format((float)$m['goldBalance'], 0, '.', ' ') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-[#8b949e]">
                                    В этом отряде пока нет активных участников
                                </div>
                            @endforelse
                        </div>

                        {{-- Список участников: Десктопный вид (таблица) --}}
                        <div class="hidden lg:block overflow-x-auto">
                            <table class="table min-w-full">
                                <thead>
                                    <tr class="bg-[#090d14] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                                        <th class="py-3 px-4 w-36">Ранг</th>
                                        <th class="py-3 px-4">Игрок</th>
                                        <th class="py-3 px-4">Steam ID</th>
                                        <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="trophy" class="w-3.5 h-3.5 text-amber-400" /> Очки славы</span></th>
                                        <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Баланс денег</span></th>
                                        <th class="py-3 px-4 text-right">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#21262d] bg-[#0d1117]/60">
                                    @forelse ($realMembers as $m)
                                        <tr class="hover:bg-[#161d2d] transition-colors group">
                                            {{-- Ранг --}}
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs border {{ $rankBadgeClasses[$m['memberRank']] ?? 'bg-[#21262d] text-[#8b949e] border-[#30363d]' }}">
                                                    <x-icon :name="$rankIcons[$m['memberRank']] ?? 'user'" class="w-3.5 h-3.5 shrink-0" />
                                                    <span>{{ $rankLabels[$m['memberRank']] ?? 'Ранг '.$m['memberRank'] }}</span>
                                                </span>
                                            </td>

                                            {{-- Имя игрока --}}
                                            <td class="py-3 px-4">
                                                @if (!empty($m['memberSteamId']))
                                                    <a href="{{ route('scumdb.player', $m['memberSteamId']) }}" class="flex items-center gap-2.5 min-w-0 group-hover:text-[#58a6ff] transition-colors" title="Открыть профиль игрока">
                                                        <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-xs font-bold shrink-0">
                                                            {{ $m['memberName'] ? strtoupper(mb_substr($m['memberName'], 0, 2)) : '?' }}
                                                        </span>
                                                        <span class="font-bold text-xs text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors truncate">
                                                            {{ $m['memberName'] ?: '—' }}
                                                        </span>
                                                    </a>
                                                @else
                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                        <span class="w-7 h-7 rounded-lg bg-[#21262d] text-[#8b949e] flex items-center justify-center text-xs font-bold shrink-0">?</span>
                                                        <span class="font-medium text-xs text-[#8b949e] truncate">{{ $m['memberName'] ?: '—' }}</span>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Steam ID --}}
                                            <td class="py-3 px-4">
                                                @if (!empty($m['memberSteamId']))
                                                    <div class="flex items-center gap-1.5">
                                                        <a href="{{ route('scumdb.player', $m['memberSteamId']) }}" class="mono text-xs text-[#58a6ff] hover:underline whitespace-nowrap">
                                                            {{ $m['memberSteamId'] }}
                                                        </a>
                                                        <button type="button" @click="copyToClipboard('{{ $m['memberSteamId'] }}')" class="btn btn-xs px-1.5 py-0.5 text-[10px]" title="Скопировать SteamID"><x-icon name="copy" class="w-3.5 h-3.5 text-[#8b949e] hover:text-[#58a6ff]" /></button>
                                                    </div>
                                                @else
                                                    <span class="text-xs text-[#8b949e]">—</span>
                                                @endif
                                            </td>

                                            {{-- Очки славы --}}
                                            <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#58a6ff] tabular-nums whitespace-nowrap">
                                                +{{ number_format((float)($m['famePoints'] ?? 0), 0, '.', ' ') }}
                                            </td>

                                            {{-- Баланс денег --}}
                                            <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#3fb950] tabular-nums whitespace-nowrap">
                                                ${{ number_format((float)($m['moneyBalance'] ?? 0), 0, '.', ' ') }}
                                                @if (!empty($m['goldBalance']))
                                                    <div class="text-[11px] text-[#f2cc60] font-normal"><x-icon name="coins" class="w-3 h-3 text-[#f2cc60] inline-block mr-1" />{{ number_format((float)$m['goldBalance'], 0, '.', ' ') }} gold</div>
                                                @endif
                                            </td>

                                            {{-- Действия --}}
                                            <td class="py-3 px-4 text-right">
                                                @if (!empty($m['memberSteamId']))
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <a href="{{ route('scumdb.player', $m['memberSteamId']) }}" class="btn btn-xs px-2 py-1 text-xs" title="Открыть профиль">
                                                            <span class="inline-flex items-center gap-1"><x-icon name="user" class="w-3 h-3 text-[#8b949e]" /> Профиль</span>
                                                        </a>
                                                        <a href="{{ route('players.whois', $m['memberSteamId']) }}" class="btn btn-xs btn-accent px-2 py-1 text-xs" title="Whois">
                                                            <span class="inline-flex items-center gap-1"><x-icon name="terminal" class="w-3 h-3 text-[#58a6ff]" /> Whois</span>
                                                        </a>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-6 text-xs text-[#8b949e]">
                                                В этом отряде пока нет активных участников
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
