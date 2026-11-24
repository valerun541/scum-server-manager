@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'SCUM.db: Транспорт')

@section('content')
    @include('scumdb._nav', ['active' => 'vehicles'])

    <div class="panel overflow-hidden" x-data="searchList()">
        {{-- Header with Search --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 sm:p-5 bg-[#0d1117] border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 text-[#d29922] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="car" class="w-5 h-5" /></div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-base text-[#e6edf3]">Транспортные средства</h2>
                        @if ($available)
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-[#d29922]/15 border border-[#d29922]/30 text-[#d29922]">
                                {{ count($vehicles) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-[#8b949e]">Вся техника на сервере, координаты на карте, состояние и активность</div>
                </div>
            </div>

            <div class="relative w-full md:w-80">
                <span class="absolute left-3 top-2.5 text-[#8b949e] text-xs pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                <input type="search" x-model="q"
                       class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#d29922] focus:ring-1 focus:ring-[#d29922] rounded-lg pl-8 pr-3 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none"
                       placeholder="Поиск по модели, ID, владельцу или алиасу...">
            </div>
        </div>

        @if (!$available)
            <div class="p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center mx-auto mb-3 text-[#d29922]">
                    <x-icon name="alert-triangle" class="w-7 h-7 text-amber-400" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">SCUM.db недоступен</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">Синхронизируйте базу на главной странице SCUM.db для загрузки актуальных данных транспорта.</div>
                <div class="mt-4">
                    <a href="{{ route('scumdb.index') }}" class="btn btn-sm btn-accent gap-2">
                        <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Перейти к синхронизации</span>
                    </a>
                </div>
            </div>
        @elseif (count($vehicles) === 0)
            <div class="text-center py-16 px-4">
                <div class="w-14 h-14 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                    <x-icon name="car" class="w-7 h-7 opacity-60 text-[#d29922]" />
                </div>
                <div class="text-sm font-semibold text-[#e6edf3]">Транспорт не найден</div>
                <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">На сервере в данный момент нет зарегистрированных транспортных средств.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table min-w-full" id="vehicles-table">
                    <thead>
                        <tr class="bg-[#161b22] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider">
                            <th class="py-3 px-4">Модель / ID</th>
                            <th class="py-3 px-4">Владелец</th>
                            <th class="py-3 px-4">Координаты на карте</th>
                            <th class="py-3 px-4">Состояние</th>
                            <th class="py-3 px-4">Последний доступ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]">
                        @foreach ($vehicles as $v)
                            @php
                                $hasCoords = $v['x'] !== null && $v['y'] !== null;
                                $zVal = $v['z'] ?? null;
                                $searchPayload = strtolower(($v['asset'] ?? '') . ' ' . ($v['alias'] ?? '') . ' ' . ($v['entityId'] ?? '') . ' ' . ($v['entityClass'] ?? '') . ' ' . ($v['ownerName'] ?? '') . ' ' . ($v['ownerSteamId'] ?? ''));
                            @endphp
                            <tr class="hover:bg-[#161b22]/70 transition-colors group" x-show="match($el)" data-search="{{ e($searchPayload) }}">
                                <td class="py-3 px-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-[#e6edf3]">
                                                {{ str_replace(['BP_', '_ES', '_C'], '', $v['asset'] ?? 'Транспорт') }}
                                            </span>
                                            @if (!empty($v['alias']))
                                                <span class="text-xs text-[#d29922] font-semibold">«{{ $v['alias'] }}»</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded font-mono text-[10px] bg-[#21262d] border border-[#30363d] text-[#8b949e]">
                                                #{{ $v['entityId'] }}
                                            </span>
                                            <button type="button" @click="copyToClipboard('{{ $v['entityId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5" title="Скопировать Entity ID"><x-icon name="copy" class="w-3 h-3" /></button>
                                            <span class="font-mono text-[10px] text-[#484f58] truncate max-w-xs">{{ $v['asset'] }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    @if (!empty($v['ownerSteamId']) || !empty($v['ownerName']))
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <x-icon name="user" class="w-3.5 h-3.5 text-[#8b949e]" />
                                                @if (!empty($v['ownerSteamId']))
                                                    <a href="{{ route('scumdb.player', $v['ownerSteamId']) }}"
                                                       class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors"
                                                       title="Открыть карточку игрока">
                                                        {{ $v['ownerName'] ?: 'Владелец' }}
                                                    </a>
                                                @else
                                                    <span class="font-bold text-xs text-[#e6edf3]">
                                                        {{ $v['ownerName'] }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if (!empty($v['ownerSteamId']))
                                                <div class="flex items-center gap-1">
                                                    <a href="{{ route('scumdb.player', $v['ownerSteamId']) }}"
                                                       class="font-mono text-[10px] text-[#58a6ff] hover:underline">
                                                        {{ $v['ownerSteamId'] }}
                                                    </a>
                                                    <button type="button"
                                                            @click="copyToClipboard('{{ $v['ownerSteamId'] }}')"
                                                            class="text-[#8b949e] hover:text-[#e6edf3] text-[10px] p-0.5"
                                                            title="Скопировать Steam ID"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                                </div>
                                            @endif

                                            @if (!empty($v['ownerDbId']))
                                                <div>
                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded font-mono text-[10px] bg-[#21262d] border border-[#30363d] text-[#8b949e]">
                                                        db id #{{ $v['ownerDbId'] }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-[#8b949e] flex items-center gap-1">
                                            <span class="text-[#484f58]">—</span>
                                            <span class="text-[10px] text-[#484f58]">нет владельца</span>
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    @if ($hasCoords)
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                            <div class="font-mono text-xs text-[#e6edf3] bg-[#0d1117] border border-[#30363d] px-2.5 py-1 rounded-lg">
                                                <span>X: {{ round((float)$v['x']) }}</span>
                                                <span class="text-[#484f58] mx-1">·</span>
                                                <span>Y: {{ round((float)$v['y']) }}</span>
                                                @if($zVal !== null)
                                                    <span class="text-[#484f58] mx-1">·</span>
                                                    <span class="text-[#8b949e]">Z: {{ round((float)$zVal) }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <a href="{{ route('map.index') }}?x={{ (float)$v['x'] }}&y={{ (float)$v['y'] }}"
                                                   target="_blank"
                                                   class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#58a6ff] border-[#30363d] flex items-center gap-1 shadow-sm"
                                                   title="Открыть местоположение на карте">
                                                    <x-icon name="map" class="w-3.5 h-3.5 text-emerald-400" />
                                                    <span>Карта</span>
                                                </a>
                                                <button type="button"
                                                        @click="copyToClipboard('{{ round((float)$v['x']) }} {{ round((float)$v['y']) }} {{ round((float)($zVal ?? 0)) }}')"
                                                        class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d]"
                                                        title="Скопировать координаты XYZ"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-[#484f58]">—</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1.5 items-center">
                                        @if (($v['functional'] ?? true) === false)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#da3633]/15 text-[#f85149] border border-[#f85149]/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#f85149]"></span>
                                                Сломан
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#238636]/15 text-[#3fb950] border border-[#2ea043]/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950]"></span>
                                                На ходу
                                            </span>
                                        @endif

                                        @if (($v['automatic'] ?? 0) == 1)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#1f6feb]/15 text-[#58a6ff] border border-[#388bfd]/30">
                                                Авто-спавн
                                            </span>
                                        @endif

                                        @if ((int)($v['forbiddenZoneSeconds'] ?? 0) > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#d29922]/15 text-[#e3b341] border border-[#d29922]/30" title="Время в запретной зоне">
                                                <span class="inline-flex items-center gap-1"><x-icon name="alert-triangle" class="w-3 h-3 text-amber-400" /> ЗЗ</span> {{ (int)$v['forbiddenZoneSeconds'] }}с
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="text-xs text-[#8b949e] whitespace-nowrap">
                                        {{ $v['lastAccess'] ? Carbon::parse($v['lastAccess'])->format('d.m.Y H:i') : '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-[#8b949e] text-center py-10" x-show="visible(document.getElementById('vehicles-table').querySelector('tbody')) === 0" x-cloak>
                Транспорт по запросу не найден.
            </div>
        @endif
    </div>
@endsection
