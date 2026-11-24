@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'SCUM.db: Флаги')

@section('content')
    @include('scumdb._nav', ['active' => 'flags'])

    <div class="panel overflow-hidden" x-data="searchList()">
        {{-- Header with Search --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 sm:p-5 bg-[#0d1117] border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#bc4c9a]/15 border border-[#bc4c9a]/30 text-[#bc4c9a] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="flag" class="w-5 h-5" /></div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-base text-[#e6edf3]">Флаги и базы игроков</h2>
                        @if ($available)
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-[#bc4c9a]/15 border border-[#bc4c9a]/30 text-[#bc4c9a]">
                                {{ count($flags) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-[#8b949e]">Захваченные территории, владельцы баз, здоровье флагов и овертейки</div>
                </div>
            </div>

            <div class="relative w-full md:w-80">
                <span class="absolute left-3 top-2.5 text-[#8b949e] text-xs pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                <input type="search" x-model="q"
                       class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#bc4c9a] focus:ring-1 focus:ring-[#bc4c9a] rounded-lg pl-8 pr-3 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none"
                       placeholder="Поиск по базе, нику или Steam ID...">
            </div>
        </div>

        @if (!$available)
            <div class="p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center mx-auto mb-3 text-[#d29922]">
                    <x-icon name="alert-triangle" class="w-7 h-7 text-amber-400" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">SCUM.db недоступен</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">Синхронизируйте базу на главной странице SCUM.db для загрузки актуальных данных баз и территорий.</div>
                <div class="mt-4">
                    <a href="{{ route('scumdb.index') }}" class="btn btn-sm btn-accent gap-2">
                        <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Перейти к синхронизации</span>
                    </a>
                </div>
            </div>
        @elseif (count($flags) === 0)
            <div class="text-center py-16 px-4">
                <div class="w-14 h-14 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                    <x-icon name="flag" class="w-7 h-7 opacity-60 text-[#bc4c9a]" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">Флаги не найдены</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">На сервере нет установленных флагов или захваченных территорий.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table min-w-full" id="flags-table">
                    <thead>
                        <tr class="bg-[#161b22] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                            <th class="py-3 px-4">Флаг / База</th>
                            <th class="py-3 px-4">Владелец базы</th>
                            <th class="py-3 px-4">Координаты</th>
                            <th class="py-3 px-4 w-44">Здоровье</th>
                            <th class="py-3 px-4">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]">
                        @foreach ($flags as $f)
                            @php
                                $health = $f['health'] !== null ? (float)$f['health'] : null;
                                $healthPct = $health !== null ? round($health * 100) : null;
                                $healthColor = $healthPct === null ? '#484f58' : ($healthPct >= 75 ? '#3fb950' : ($healthPct >= 40 ? '#d29922' : '#f85149'));
                                $overtakeTs = (int)($f['overtakeEndTime'] ?? 0);
                                $isOvertake = $overtakeTs > 0;
                                $overtakeLabel = null;
                                if ($isOvertake) {
                                    try { $overtakeLabel = Carbon::createFromTimestamp($overtakeTs)->format('d.m.Y H:i'); } catch (\Throwable $e) { $overtakeLabel = gmdate('d.m.Y H:i', $overtakeTs); }
                                }
                                $cx = $f['ex'] ?? $f['x'] ?? $f['bx'] ?? null;
                                $cy = $f['ey'] ?? $f['y'] ?? $f['by'] ?? null;
                                $hasCoords = $cx !== null && $cy !== null && abs((float)$cx) > 1 && abs((float)$cy) > 1;
                                $searchPayload = strtolower(($f['ownerName'] ?? '') . ' ' . ($f['ownerSteamId'] ?? '') . ' ' . ($f['baseName'] ?? '') . ' ' . ($f['squadName'] ?? ''));
                            @endphp
                            <tr class="hover:bg-[#161b22]/70 transition-colors group" x-show="match($el)" data-search="{{ e($searchPayload) }}">
                                <td class="py-3 px-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="flag" class="w-4 h-4 text-red-400" />
                                            <span class="font-bold text-xs text-[#e6edf3]">#{{ $f['elementId'] }}</span>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-mono bg-[#21262d] border border-[#30363d] text-[#8b949e]">
                                                base #{{ $f['baseId'] ?? '—' }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-[#8b949e] font-mono">
                                            {{ $f['asset'] ?: ($f['baseName'] ?: 'Базовый флаг') }}
                                        </div>
                                        @if (($f['expandedElements'] ?? 0) > 0)
                                            <span class="text-[10px] text-[#58a6ff]">+{{ $f['expandedElements'] }} элементов застройки</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    @php
                                        $baseOwnerSid = $f['baseOwnerSteamId'] ?? null;
                                        $elemOwnerSid = $f['elementOwnerSteamId'] ?? null;
                                        $showElemDiff = !empty($baseOwnerSid) && !empty($elemOwnerSid) && $baseOwnerSid !== $elemOwnerSid;
                                    @endphp
                                    @if (!empty($f['ownerSteamId']))
                                        <div class="space-y-1">
                                            <a href="{{ route('scumdb.player', $f['ownerSteamId']) }}" class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors" title="Открыть профиль владельца">
                                                {{ $f['ownerName'] ?: 'Без имени' }}
                                            </a>
                                            @if (!empty($f['squadName']))
                                                <div>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.2 rounded-md text-[10px] font-semibold bg-[#a371f7]/15 text-[#a371f7] border border-[#a371f7]/30">
                                                        <x-icon name="shield" class="w-3.5 h-3.5 inline mr-1 text-purple-400" /> {{ $f['squadName'] }}
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1">
                                                <a href="{{ route('scumdb.player', $f['ownerSteamId']) }}" class="mono text-[10px] text-[#58a6ff] hover:underline">
                                                    {{ $f['ownerSteamId'] }}
                                                </a>
                                                <button type="button" @click="copyToClipboard('{{ $f['ownerSteamId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5" title="Скопировать"><x-icon name="copy" class="w-3 h-3" /></button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#8b949e]">— нет владельца —</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @if ($hasCoords)
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                            <div class="font-mono text-xs text-[#e6edf3] bg-[#0d1117] border border-[#30363d] px-2 py-1 rounded-lg whitespace-nowrap">
                                                <span>{{ round((float)$cx) }}</span>
                                                <span class="text-[#484f58] mx-1">/</span>
                                                <span>{{ round((float)$cy) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <a href="{{ route('map.index') }}?x={{ (float)$cx }}&y={{ (float)$cy }}"
                                                   target="_blank"
                                                   class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#58a6ff] border-[#30363d] flex items-center gap-1"
                                                   title="Показать на карте">
                                                    <x-icon name="map" class="w-3.5 h-3.5 text-emerald-400" />
                                                    <span>Карта</span>
                                                </a>
                                                <button type="button"
                                                        @click="copyToClipboard('{{ round((float)$cx) }} {{ round((float)$cy) }}')"
                                                        class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d]"
                                                        title="Скопировать"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#484f58]">—</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @if ($healthPct !== null)
                                        <div class="space-y-1.5">
                                            <div class="flex items-center justify-between text-xs font-mono font-bold">
                                                <span style="color: {{ $healthColor }}">{{ $healthPct }}%</span>
                                                <span class="text-[10px] text-[#8b949e]">{{ number_format($health, 2, '.', '') }}</span>
                                            </div>
                                            <div class="h-2 w-full bg-[#0d1117] border border-[#30363d] rounded-full overflow-hidden p-0.5">
                                                <div class="h-full rounded-full transition-all duration-300"
                                                     style="width: {{ $healthPct }}%; background: {{ $healthColor }}"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#484f58]">—</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @if (!empty($f['overtakerSteamId']) || $isOvertake)
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#da3633]/15 text-[#f85149] border border-[#f85149]/30">
                                                <span class="inline-flex items-center gap-1 text-red-400"><x-icon name="crosshair" class="w-3 h-3" /> Захват</span>
                                            </span>
                                            @if (!empty($f['overtakerSteamId']))
                                                <div>
                                                    <a href="{{ route('scumdb.player', $f['overtakerSteamId']) }}" class="text-xs text-[#f85149] hover:underline font-bold">
                                                        {{ $f['overtakerName'] ?: $f['overtakerSteamId'] }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if ($overtakeLabel)
                                                <div class="text-[10px] text-[#8b949e]">до {{ $overtakeLabel }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#238636]/15 text-[#3fb950] border border-[#2ea043]/30">
                                            <span class="inline-flex items-center gap-1 text-emerald-400"><x-icon name="shield" class="w-3 h-3" /> Защищён</span>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-[#8b949e] text-center py-10" x-show="visible(document.getElementById('flags-table').querySelector('tbody')) === 0" x-cloak>
                Флаги по запросу не найдены.
            </div>
        @endif
    </div>
@endsection
