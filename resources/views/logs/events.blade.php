@use('Illuminate\Support\Carbon')
@use('App\Support\LogParser')
@extends('layouts.app')

@section('title', 'События сервера')

@section('content')
    @include('logs._nav')
    <div class="panel p-3.5 sm:p-5 space-y-4" x-data="logPoller({ url: '{{ route('logs.poll.events') }}', mode: 'events', initialLastId: {{ $maxId ?? 0 }}, ms: 3000 })">
        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center shrink-0 shadow-inner"><x-icon name="activity" class="w-5 h-5" /></div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-bold text-base text-[#e6edf3]">События сервера</h2>
                        <template x-if="newCount > 0">
                            <button type="button" @click="clearNew()" class="btn btn-xs bg-[#238636] hover:bg-[#2ea043] text-white border-[#2ea043] flex items-center gap-1.5 animate-bounce">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                <span x-text="newCount + ' новых'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="text-[11px] text-[#8b949e]">Живая лента действий, входов, выходов, убийств и состояния мира</div>
                </div>
            </div>

            {{-- Поиск --}}
            <div class="relative flex items-center w-full sm:w-72">
                <span class="absolute left-3 text-[#8b949e] pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                <input type="text" x-model="q" @input="applyFilter()"
                       class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-8 pr-7 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] transition-colors outline-none"
                       placeholder="Поиск по событиям…">
                <button type="button" x-show="q" @click="clearQuery()" class="absolute right-2 text-[#8b949e] hover:text-[#e6edf3] text-xs p-0.5" title="Очистить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
            </div>
        </div>

        @if ($events->isEmpty())
            <div class="text-sm text-[#8b949e] text-center py-12 px-4" x-show="lastId === 0">
                <div class="w-14 h-14 rounded-2xl bg-[#21262d]/50 border border-[#30363d] flex items-center justify-center mx-auto mb-2 opacity-60"><x-icon name="activity" class="w-7 h-7 text-[#8b949e]" /></div>
                <div class="font-bold text-[#e6edf3] mb-1">Событий пока нет</div>
                <div class="text-xs text-[#8b949e]">События (входы, выходы, транспорт, админ-действия) будут отображаться здесь.</div>
            </div>
        @endif

        {{-- Адаптивная сетка событий: Desktop Grid + Mobile Cards --}}
        <div class="panel overflow-hidden border border-[#30363d] bg-[#0d1117]">
            {{-- Шапка сетки на десктопе --}}
            <div class="hidden md:grid md:grid-cols-12 gap-3 px-4 py-2.5 bg-[#161b22] border-b border-[#30363d] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider items-center">
                <div class="col-span-2">Время</div>
                <div class="col-span-2">Категория</div>
                <div class="col-span-3">Игрок / Источник</div>
                <div class="col-span-4">Описание события</div>
                <div class="col-span-1 text-right">Локация</div>
            </div>

            {{-- Список строк --}}
            <div class="divide-y divide-[#21262d]" x-ref="logBody" @dblclick="clearNew()">
                @foreach ($events as $e)
                    @php
                        $item = LogParser::parse($e->type, $e->message);
                        $searchString = strtolower(($e->type ?? '').' '.($item['player'] ?? '').' '.($item['steamId'] ?? '').' '.($item['details'] ?? '').' '.($e->message ?? ''));
                    @endphp
                    <div data-id="{{ $e->id }}" data-search="{{ $searchString }}"
                         class="p-3 sm:px-4 sm:py-3 hover:bg-[#161b22]/70 transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center">
                        
                        {{-- 1. Время --}}
                        <div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">
                            <span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">
                                {{ Carbon::parse($e->created_at)->format('d.m.Y H:i:s') }}
                            </span>
                            <div class="md:hidden">
                                @if ($item['badgeText'])
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $item['badgeClass'] ?? 'bg-[#21262d] text-[#c9d1d9] border-[#30363d]' }}">
                                        {{ $item['badgeText'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- 2. Категория / Бейдж (Десктоп) --}}
                        <div class="hidden md:block md:col-span-2">
                            @if ($item['badgeText'])
                                <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border {{ $item['badgeClass'] ?? 'bg-[#21262d] text-[#c9d1d9] border-[#30363d]' }}">
                                    {{ $item['badgeText'] }}
                                </span>
                            @else
                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold border bg-[#21262d] text-[#8b949e] border-[#30363d] uppercase tracking-wider">
                                    {{ $e->type }}
                                </span>
                            @endif
                        </div>

                        {{-- 3. Игрок / Источник --}}
                        <div class="md:col-span-3 min-w-0">
                            @if ($item['player'])
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-xs font-bold shrink-0 shadow-sm">
                                        {{ strtoupper(mb_substr($item['player'], 0, 2)) }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @if ($item['steamId'] && $item['steamId'] !== 'NPC')
                                                <a href="{{ route('scumdb.player', $item['steamId']) }}" class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors truncate">
                                                    {{ $item['player'] }}
                                                </a>
                                                <span class="text-[10px] text-[#8b949e] font-mono">({{ $item['steamId'] }})</span>
                                            @else
                                                <span class="font-bold text-xs text-[#e6edf3] truncate">{{ $item['player'] }}</span>
                                                @if ($item['steamId'])
                                                    <span class="text-[10px] text-[#8b949e] font-mono">({{ $item['steamId'] }})</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-xs text-[#8b949e]">
                                    <span class="w-6 h-6 rounded-md bg-[#21262d]/60 border border-[#30363d] flex items-center justify-center text-xs font-bold">{{ strtoupper(mb_substr($e->type ?? 'EV', 0, 2)) }}</span>
                                    <span class="uppercase tracking-wider font-semibold">{{ $e->type }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- 4. Описание события --}}
                        <div class="md:col-span-4 text-xs text-[#e6edf3] break-words">
                            @if ($item['parsed'] && $item['details'])
                                <div class="font-medium text-[#e6edf3] leading-relaxed">
                                    {{ $item['details'] }}
                                </div>
                                @if (!empty($item['extra']['weapon']) || !empty($item['extra']['ip']))
                                    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                        @if (!empty($item['extra']['weapon']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[11px] text-[#8b949e] font-mono">
                                                <x-icon name="crosshair" class="w-3.5 h-3.5 inline mr-1 text-red-400" /> {{ $item['extra']['weapon'] }}
                                            </span>
                                        @endif
                                        @if (!empty($item['extra']['ip']))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[10px] text-[#8b949e] font-mono">
                                                <x-icon name="server" class="w-3.5 h-3.5 inline mr-1 text-[#8b949e]" /> IP: {{ $item['extra']['ip'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="mono text-[#c9d1d9] break-all">{{ $e->message }}</span>
                            @endif
                        </div>

                        {{-- 5. Локация / Кнопка карты --}}
                        <div class="md:col-span-1 md:text-right flex items-center justify-between md:justify-end gap-2 pt-1 md:pt-0">
                            @if (!empty($item['coords']))
                                <a href="{{ route('map.index') }}?x={{ $item['coords']['x'] }}&y={{ $item['coords']['y'] }}"
                                   target="_blank"
                                   class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] border-[#30363d] text-[#58a6ff] hover:text-[#79c0ff] inline-flex items-center gap-1 shadow-sm"
                                   title="Показать точку на интерактивной карте">
                                    <x-icon name="map" class="w-3.5 h-3.5 inline mr-1 text-emerald-400" />
                                    <span class="font-mono text-[10px]">[{{ round($item['coords']['x']) }}, {{ round($item['coords']['y']) }}]</span>
                                </a>
                            @else
                                <span class="hidden md:inline text-xs text-[#8b949e] opacity-40">—</span>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex justify-center pt-2">
            {{ $events->links('vendor.pagination.panel') }}
        </div>
    </div>
@endsection
