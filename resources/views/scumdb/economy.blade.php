@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'SCUM.db: Экономика')

@section('content')
    @include('scumdb._nav', ['active' => 'economy'])

    <div class="space-y-6">
        {{-- Header --}}
        <div class="panel p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#f0883e]/15 border border-[#f0883e]/30 text-[#f0883e] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="sparkles" class="w-5 h-5" /></div>
                <div>
                    <h2 class="font-bold text-base text-[#e6edf3]">Рейтинг богатства и экономика сервера</h2>
                    <div class="text-xs text-[#8b949e]">Топ игроков по общему балансу денег в банке и запасам золота</div>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-[#f0883e]/15 border border-[#f0883e]/30 text-[#f0883e] self-start md:self-auto shrink-0">
                Топ {{ count($leaderboard) }} игроков
            </span>
        </div>

        @if (!$available)
            <div class="p-5 text-sm text-[#d29922]">SCUM.db недоступен. Синхронизируйте базу на главной странице SCUM.db.</div>
        @elseif (count($leaderboard) === 0)
            <div class="panel p-12 text-sm text-[#8b949e] text-center">Данные о балансах не найдены.</div>
        @else
            {{-- ========================================================================= --}}
            {{-- TOP 3 PODIUM CARDS                                                        --}}
            {{-- ========================================================================= --}}
            @if (count($leaderboard) >= 3)
                @php
                    $first = $leaderboard[0] ?? null;
                    $second = $leaderboard[1] ?? null;
                    $third = $leaderboard[2] ?? null;
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    
                    {{-- 2nd Place (Silver) --}}
                    @if ($second)
                        <div class="order-2 md:order-1 card p-5 bg-[#0d1117]/90 border border-[#8b949e]/40 hover:border-[#c9d1d9] transition-all rounded-2xl shadow-lg relative overflow-hidden group">
                            <div class="absolute -top-12 -right-12 w-32 h-32 bg-[#c9d1d9]/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="p-2 rounded-xl bg-[#21262d] text-[#8b949e] inline-flex items-center justify-center" title="2 место"><x-icon name="award" class="w-6 h-6 text-[#8b949e]" /></span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#21262d] border border-[#30363d] text-[#c9d1d9]">2 МЕСТО</span>
                            </div>
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#c9d1d9]/20 to-[#8b949e]/20 border border-[#c9d1d9]/40 text-[#c9d1d9] flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ strtoupper(mb_substr($second['name'] ?? '?', 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('scumdb.player', $second['steamId']) }}" class="font-bold text-sm text-[#e6edf3] hover:text-[#58a6ff] truncate block">
                                        {{ $second['name'] ?: 'Игрок' }}
                                    </a>
                                    <span class="text-[10px] text-[#8b949e] font-mono">{{ $second['steamId'] }}</span>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-[#21262d] space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e]">Деньги:</span>
                                    <span class="font-mono font-bold text-[#3fb950]">${{ number_format($second['walletBalance'] ?? 0, 0, '.', ' ') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e]">Золото:</span>
                                    <span class="font-mono font-bold text-[#d29922]">{{ number_format($second['goldBalance'] ?? 0, 0, '.', ' ') }} <x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /></span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- 1st Place (Gold / Champion) --}}
                    @if ($first)
                        <div class="order-1 md:order-2 card p-6 bg-gradient-to-b from-[#161b22] to-[#0d1117] border-2 border-[#d29922]/70 hover:border-[#e3b341] transition-all rounded-2xl shadow-2xl shadow-[#d29922]/10 relative overflow-hidden group md:-translate-y-2">
                            <div class="absolute -top-12 -right-12 w-36 h-36 bg-[#d29922]/20 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="p-2.5 rounded-2xl bg-[#e3b341]/15 border border-[#e3b341]/30 text-[#e3b341] inline-flex items-center justify-center shadow-lg shadow-[#e3b341]/10" title="1 место — Лидер"><x-icon name="trophy" class="w-8 h-8 text-[#e3b341]" /></span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-[#d29922]/20 border border-[#d29922]/50 text-[#e3b341] tracking-wider">
                                    ЧЕМПИОН
                                </span>
                            </div>
                            <div class="flex items-center gap-3.5 mb-4">
                                <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-[#d29922] to-[#f0883e] text-[#0d1117] flex items-center justify-center text-lg font-black shrink-0 shadow-lg shadow-[#d29922]/30">
                                    {{ strtoupper(mb_substr($first['name'] ?? '?', 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('scumdb.player', $first['steamId']) }}" class="font-black text-base text-[#e6edf3] hover:text-[#58a6ff] truncate block">
                                        {{ $first['name'] ?: 'Игрок' }}
                                    </a>
                                    <span class="text-xs text-[#d29922] font-mono">{{ $first['steamId'] }}</span>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-[#30363d] space-y-1.5 bg-[#0d1117]/60 p-3 rounded-xl">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e] font-semibold">Баланс банка:</span>
                                    <span class="font-mono font-black text-sm text-[#3fb950]">${{ number_format($first['walletBalance'] ?? 0, 0, '.', ' ') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e] font-semibold">Золото:</span>
                                    <span class="font-mono font-black text-sm text-[#d29922]">{{ number_format($first['goldBalance'] ?? 0, 0, '.', ' ') }} <x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /></span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- 3rd Place (Bronze) --}}
                    @if ($third)
                        <div class="order-3 card p-5 bg-[#0d1117]/90 border border-[#f0883e]/40 hover:border-[#f0883e] transition-all rounded-2xl shadow-lg relative overflow-hidden group">
                            <div class="absolute -top-12 -right-12 w-32 h-32 bg-[#f0883e]/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="p-2 rounded-xl bg-[#21262d] text-[#d29922] inline-flex items-center justify-center" title="3 место"><x-icon name="award" class="w-6 h-6 text-[#d29922]" /></span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#21262d] border border-[#30363d] text-[#f0883e]">3 МЕСТО</span>
                            </div>
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#f0883e]/20 to-[#bc8c5f]/20 border border-[#f0883e]/40 text-[#f0883e] flex items-center justify-center text-sm font-bold shrink-0">
                                    {{ strtoupper(mb_substr($third['name'] ?? '?', 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('scumdb.player', $third['steamId']) }}" class="font-bold text-sm text-[#e6edf3] hover:text-[#58a6ff] truncate block">
                                        {{ $third['name'] ?: 'Игрок' }}
                                    </a>
                                    <span class="text-[10px] text-[#8b949e] font-mono">{{ $third['steamId'] }}</span>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-[#21262d] space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e]">Деньги:</span>
                                    <span class="font-mono font-bold text-[#3fb950]">${{ number_format($third['walletBalance'] ?? 0, 0, '.', ' ') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[#8b949e]">Золото:</span>
                                    <span class="font-mono font-bold text-[#d29922]">{{ number_format($third['goldBalance'] ?? 0, 0, '.', ' ') }} <x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /></span>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            @endif

            {{-- Full Leaderboard Table --}}
            <div class="panel overflow-hidden">
                <div class="px-5 py-3.5 bg-[#161b22] border-b border-[#30363d] flex items-center justify-between">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-[#8b949e]">Полная таблица лидеров</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table min-w-full">
                        <thead>
                            <tr class="bg-[#101623] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                                <th class="py-3 px-4 w-14 text-center">#</th>
                                <th class="py-3 px-4">Игрок</th>
                                <th class="py-3 px-4">Steam ID</th>
                                <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5 justify-end"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Деньги в банке</span></th>
                                <th class="py-3 px-4 text-right"><span class="inline-flex items-center justify-end gap-1"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922]" /> Золото</span></th>
                                <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5 justify-end"><x-icon name="sparkles" class="w-3.5 h-3.5 text-amber-400" /> Слава</span></th>
                                <th class="py-3 px-4 text-right">Последний вход</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#21262d]">
                            @foreach ($leaderboard as $i => $p)
                                @php
                                    $lastLoginRaw = $p['lastLogin'] ?? null;
                                    $lastLabel = '—';
                                    if ($lastLoginRaw) {
                                        try {
                                            if (is_numeric($lastLoginRaw)) {
                                                $ts = (int)$lastLoginRaw;
                                                $lastLabel = $ts > 1000000000 ? Carbon::createFromTimestamp($ts)->format('d.m.Y H:i') : Carbon::parse($lastLoginRaw)->format('d.m.Y H:i');
                                            } else {
                                                $lastLabel = Carbon::parse($lastLoginRaw)->format('d.m.Y H:i');
                                            }
                                        } catch (\Throwable $e) { $lastLabel = (string)$lastLoginRaw; }
                                    }
                                @endphp
                                <tr class="hover:bg-[#161b22]/70 transition-colors group">
                                    <td class="py-3 px-4 text-center">
                                        @if ($i === 0)
                                            <x-icon name="trophy" class="w-4 h-4 text-[#e3b341] inline-block" title="1 место" />
                                        @elseif ($i === 1)
                                            <x-icon name="award" class="w-4 h-4 text-[#8b949e] inline-block" title="2 место" />
                                        @elseif ($i === 2)
                                            <x-icon name="award" class="w-4 h-4 text-[#d29922] inline-block" title="3 место" />
                                        @else
                                            <span class="font-mono text-xs text-[#8b949e] font-bold">{{ $i + 1 }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <a href="{{ route('scumdb.player', $p['steamId']) }}" class="flex items-center gap-2.5 min-w-0 group-hover:text-[#58a6ff] transition-colors" title="Открыть профиль">
                                            <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-xs font-bold shrink-0">
                                                {{ strtoupper(mb_substr($p['name'] ?? '?', 0, 2)) }}
                                            </span>
                                            <span class="font-bold text-xs text-[#e6edf3] group-hover:text-[#58a6ff] truncate">
                                                {{ $p['name'] ?: '—' }}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('scumdb.player', $p['steamId']) }}" class="mono text-xs text-[#58a6ff] hover:underline">
                                                {{ $p['steamId'] }}
                                            </a>
                                            <button type="button" @click="copyToClipboard('{{ $p['steamId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5" title="Скопировать"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#3fb950] tabular-nums whitespace-nowrap">
                                        ${{ number_format($p['walletBalance'] ?? 0, 0, '.', ' ') }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#d29922] tabular-nums whitespace-nowrap">
                                        {{ number_format($p['goldBalance'] ?? 0, 0, '.', ' ') }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#58a6ff] tabular-nums whitespace-nowrap">
                                        +{{ number_format($p['famePoints'] ?? 0, 0, '.', ' ') }}
                                    </td>
                                    <td class="py-3 px-4 text-right text-xs text-[#8b949e] whitespace-nowrap">
                                        {{ $lastLabel }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
