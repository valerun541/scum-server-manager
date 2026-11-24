@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'SCUM.db: Игроки')

@section('content')
    @include('scumdb._nav', ['active' => 'players'])

    @if (!$available)
        <div class="p-5 rounded-2xl border border-[#d29922]/40 bg-[#d29922]/10 text-xs sm:text-sm text-[#e3b341] mb-6 flex items-center gap-3">
            <x-icon name="alert-triangle" class="w-5 h-5 text-amber-400" />
            <span>SCUM.db недоступен. Синхронизируйте базу на главной странице SCUM.db.</span>
        </div>
    @else
        <div class="panel overflow-hidden" x-data="searchList()">
            {{-- Header with Search & Stats --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 sm:p-5 bg-[#0d1117] border-b border-[#30363d]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="users" class="w-5 h-5" /></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-base text-[#e6edf3]">Список персонажей и игроков</h2>
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff]">
                                {{ count($players) }}
                            </span>
                        </div>
                        <div class="text-xs text-[#8b949e]">Профили, кошельки, инвентарь и статистика боевых действий</div>
                    </div>
                </div>

                <div class="relative w-full md:w-80">
                    <span class="absolute left-3 top-2.5 text-[#8b949e] text-xs pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                    <input type="search" x-model="q"
                           class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-8 pr-3 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none"
                           placeholder="Поиск по нику, Steam ID или имени...">
                </div>
            </div>

            {{-- Desktop / Tablet Table View (hidden on mobile) --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="table min-w-full" id="players-table">
                    <thead>
                        <tr class="bg-[#161b22] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                            <th class="py-3 px-4">Игрок</th>
                            <th class="py-3 px-4">Steam ID</th>
                            <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-[#3fb950]" /> Деньги</span></th>
                            <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922]" /> Золото</span></th>
                            <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="trophy" class="w-3.5 h-3.5 text-[#58a6ff]" /> Слава</span></th>
                            <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="target" class="w-3.5 h-3.5 text-[#e6edf3]" /> Убийства</span></th>
                            <th class="py-3 px-4 text-right"><span class="inline-flex items-center gap-1.5"><x-icon name="x" class="w-3.5 h-3.5 text-[#8b949e]" /> Смерти</span></th>
                            <th class="py-3 px-4">Последний вход</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]">
                        @foreach ($players as $p)
                            @php
                                $cName = $p['charName'] ?: ($p['steamName'] ?: 'Игрок');
                                $searchPayload = strtolower($cName . ' ' . ($p['steamName'] ?? '') . ' ' . $p['steamId']);
                            @endphp
                            <tr class="hover:bg-[#161b22]/70 transition-colors group" x-show="match($el)" data-search="{{ e($searchPayload) }}">
                                <td class="py-3 px-4">
                                    <a href="{{ route('scumdb.player', $p['steamId']) }}" class="flex items-center gap-3 min-w-0 group-hover:text-[#58a6ff] transition-colors" title="Открыть профиль персонажа">
                                        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 flex items-center justify-center text-xs font-bold text-[#58a6ff] shrink-0 shadow-sm transition-transform group-hover:scale-105">
                                            {{ strtoupper(mb_substr($cName, 0, 2)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors truncate">
                                                {{ $cName }}
                                            </div>
                                            @if (!empty($p['isBanned']))
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#f85149]"><span class="inline-flex items-center gap-1"><x-icon name="volume-x" class="w-3 h-3 text-red-400" /> Забанен</span></span>
                                            @elseif (!empty($p['steamName']) && $p['steamName'] !== $p['charName'])
                                                <div class="text-[10px] text-[#8b949e] truncate">{{ $p['steamName'] }}</div>
                                            @endif
                                        </div>
                                    </a>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('scumdb.player', $p['steamId']) }}" class="mono text-xs text-[#58a6ff] hover:underline whitespace-nowrap">
                                            {{ $p['steamId'] }}
                                        </a>
                                        <button type="button" @click="copyToClipboard('{{ $p['steamId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5" title="Скопировать"><x-icon name="copy" class="w-3 h-3" /></button>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#3fb950] tabular-nums whitespace-nowrap">
                                    ${{ number_format($p['money'] ?? 0, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#d29922] tabular-nums whitespace-nowrap">
                                    {{ number_format($p['gold'] ?? 0, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-xs text-[#58a6ff] tabular-nums whitespace-nowrap">
                                    +{{ number_format($p['fame'] ?? 0, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-xs text-[#e6edf3] tabular-nums whitespace-nowrap">
                                    {{ number_format($p['kills'] ?? 0, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-xs text-[#8b949e] tabular-nums whitespace-nowrap">
                                    {{ number_format($p['deaths'] ?? 0, 0, '.', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-xs text-[#8b949e] whitespace-nowrap">
                                    {{ ($p['lastLogin'] ?? null) ? Carbon::parse($p['lastLogin'])->format('d.m.Y H:i') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (displayed only on mobile < md) --}}
            <div class="md:hidden divide-y divide-[#21262d]">
                @foreach ($players as $p)
                    @php
                        $cName = $p['charName'] ?: ($p['steamName'] ?: 'Игрок');
                        $searchPayload = strtolower($cName . ' ' . ($p['steamName'] ?? '') . ' ' . $p['steamId']);
                    @endphp
                    <div class="p-3.5 space-y-2.5 hover:bg-[#161b22]/50 transition-colors" x-show="match($el)" data-search="{{ e($searchPayload) }}">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('scumdb.player', $p['steamId']) }}" class="flex items-center gap-2.5 min-w-0">
                                <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 flex items-center justify-center text-xs font-bold text-[#58a6ff] shrink-0">
                                    {{ strtoupper(mb_substr($cName, 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-[#e6edf3] truncate">{{ $cName }}</div>
                                    <div class="text-[10px] text-[#58a6ff] font-mono truncate">{{ $p['steamId'] }}</div>
                                </div>
                            </a>
                            <a href="{{ route('scumdb.player', $p['steamId']) }}" class="btn btn-xs btn-accent shrink-0">
                                <span class="flex items-center gap-1"><span>Профиль</span><x-icon name="arrow-right" class="w-3 h-3" /></span>
                            </a>
                        </div>

                        <div class="grid grid-cols-3 gap-1.5 text-center text-[11px] pt-1">
                            <div class="p-1.5 rounded-lg bg-[#0d1117] border border-[#21262d]">
                                <div class="text-[10px] text-[#8b949e]">Деньги</div>
                                <div class="font-mono font-bold text-[#3fb950]">${{ number_format($p['money'] ?? 0, 0, '.', ' ') }}</div>
                            </div>
                            <div class="p-1.5 rounded-lg bg-[#0d1117] border border-[#21262d]">
                                <div class="text-[10px] text-[#8b949e]">Золото</div>
                                <div class="font-mono font-bold text-[#d29922]">{{ number_format($p['gold'] ?? 0, 0, '.', ' ') }}</div>
                            </div>
                            <div class="p-1.5 rounded-lg bg-[#0d1117] border border-[#21262d]">
                                <div class="text-[10px] text-[#8b949e]">Слава</div>
                                <div class="font-mono font-bold text-[#58a6ff]">+{{ number_format($p['fame'] ?? 0, 0, '.', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-xs text-[#8b949e] text-center py-10" x-show="visible(document.getElementById('players-table').querySelector('tbody')) === 0" x-cloak>
                Игроки по запросу не найдены.
            </div>
        </div>
    @endif
@endsection
