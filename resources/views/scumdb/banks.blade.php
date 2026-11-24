@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'SCUM.db: Банки')

@section('content')
    @include('scumdb._nav', ['active' => 'banks'])

    <div class="panel overflow-hidden" x-data="searchList()">
        {{-- Header with Search --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 sm:p-5 bg-[#0d1117] border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 text-[#2ea043] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="coins" class="w-5 h-5" /></div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-base text-[#e6edf3]">Банковские счета и балансы</h2>
                        @if ($available)
                            @php $uniqueAccounts = collect($accounts)->groupBy('accountId')->count(); @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-[#2ea043]/15 border border-[#2ea043]/30 text-[#3fb950]">
                                {{ $uniqueAccounts }} счетов
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-[#8b949e]">Реестр банковских счетов игроков, балансы наличных и золотых счетов</div>
                </div>
            </div>

            <div class="relative w-full md:w-80">
                <span class="absolute left-3 top-2.5 text-[#8b949e] text-xs pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                <input type="search" x-model="q"
                       class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#2ea043] focus:ring-1 focus:ring-[#2ea043] rounded-lg pl-8 pr-3 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none"
                       placeholder="Поиск по номеру счета, владельцу или Steam ID...">
            </div>
        </div>

        @if (!$available)
            <div class="p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center mx-auto mb-3 text-[#d29922]">
                    <x-icon name="alert-triangle" class="w-7 h-7 text-amber-400" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">SCUM.db недоступен</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">Синхронизируйте базу на главной странице SCUM.db для загрузки актуальных данных банковских счетов.</div>
                <div class="mt-4">
                    <a href="{{ route('scumdb.index') }}" class="btn btn-sm btn-accent gap-2">
                        <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Перейти к синхронизации</span>
                    </a>
                </div>
            </div>
        @elseif (count($accounts) === 0)
            <div class="text-center py-16 px-4">
                <div class="w-14 h-14 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                    <x-icon name="coins" class="w-7 h-7 opacity-60 text-[#2ea043]" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">Банковские счета не найдены</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">В базе данных сервера отсутствуют зарегистрированные банковские счета игроков.</div>
            </div>
        @else
            @php
                $grouped = collect($accounts)->groupBy('accountId');
            @endphp
            <div class="overflow-x-auto">
                <table class="table min-w-full" id="banks-table">
                    <thead>
                        <tr class="bg-[#161b22] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                            <th class="py-3 px-4">Номер счета</th>
                            <th class="py-3 px-4">Владелец счета</th>
                            <th class="py-3 px-4">Балансы валют</th>
                            <th class="py-3 px-4 text-right">Последнее сохранение</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]">
                        @foreach ($grouped as $aid => $rows)
                            @php
                                $first = $rows->first();
                                $ts = (int)($first['saveTimestamp'] ?? 0);
                                $saveLabel = $ts > 0 ? Carbon::createFromTimestamp($ts)->format('d.m.Y H:i') : '—';
                                $searchPayload = strtolower(($first['accountNumber'] ?? '') . ' ' . ($first['ownerName'] ?? '') . ' ' . ($first['ownerSteamId'] ?? '') . ' ' . $aid);
                            @endphp
                            <tr class="hover:bg-[#161b22]/70 transition-colors group" x-show="match($el)" data-search="{{ e($searchPayload) }}">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-[#0d1117] border border-[#30363d] font-mono text-xs text-[#e6edf3] font-bold">
                                            {{ $first['accountNumber'] ?? $aid }}
                                        </span>
                                        <button type="button" @click="copyToClipboard('{{ $first['accountNumber'] ?? $aid }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-xs p-1" title="Скопировать номер счета"><x-icon name="copy" class="w-3 h-3" /></button>
                                    </div>
                                    <div class="font-mono text-[10px] text-[#484f58] mt-0.5">ID: #{{ $aid }}</div>
                                </td>

                                <td class="py-3 px-4">
                                    @if (!empty($first['ownerSteamId']))
                                        <div class="space-y-0.5">
                                            <a href="{{ route('scumdb.player', $first['ownerSteamId']) }}" class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors" title="Открыть профиль игрока">
                                                {{ $first['ownerName'] ?: 'Без имени' }}
                                            </a>
                                            <div class="flex items-center gap-1">
                                                <a href="{{ route('scumdb.player', $first['ownerSteamId']) }}" class="mono text-[10px] text-[#58a6ff] hover:underline">
                                                    {{ $first['ownerSteamId'] }}
                                                </a>
                                                <button type="button" @click="copyToClipboard('{{ $first['ownerSteamId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5" title="Скопировать Steam ID"><x-icon name="copy" class="w-3 h-3" /></button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#8b949e]">— без владельца —</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-2 items-center">
                                        @foreach ($rows as $r)
                                            @if (($r['currencyType'] ?? 1) == 2)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-[#d29922]/15 text-[#d29922] border border-[#d29922]/30">
                                                    <span class="flex items-center gap-1.5"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922]" /> Золото:</span>
                                                    <span>{{ number_format($r['accountBalance'] ?? 0, 0, '.', ' ') }}</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-[#238636]/15 text-[#3fb950] border border-[#2ea043]/30">
                                                    <span class="inline-flex items-center gap-1.5"><x-icon name="coins" class="w-4 h-4 text-emerald-400" /> Деньги:</span>
                                                    <span>${{ number_format($r['accountBalance'] ?? 0, 0, '.', ' ') }}</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">
                                        {{ $saveLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-[#8b949e] text-center py-10" x-show="visible(document.getElementById('banks-table').querySelector('tbody')) === 0" x-cloak>
                Счета по запросу не найдены.
            </div>
        @endif
    </div>
@endsection
