@extends('layouts.app')

@section('title', 'Системный лог')

@section('content')
    @include('logs._nav')
    <div class="panel p-4 sm:p-5 space-y-4" x-data="logPoller({ url: '{{ route('logs.poll.system') }}', mode: 'system', initialTotal: {{ count($lines) }}, ms: 5000 })">
        {{-- Header & Controls --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb]/20 to-[#388bfd]/10 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-lg shrink-0 shadow-inner"><x-icon name="file-text" class="w-5 h-5 text-[#58a6ff]" /></div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-base text-[#e6edf3]">Системный журнал (scum.log)</h2>
                        <template x-if="newCount > 0">
                            <button type="button" @click="clearNew(); scrollToBottom()" class="btn btn-xs bg-[#238636] hover:bg-[#2ea043] text-white border-[#2ea043] flex items-center gap-1.5 animate-bounce">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                <span x-text="newCount + ' новых'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="text-[11px] text-[#8b949e]">Логи фоновых демонов (watcher, rcon, scheduler) и системных обработчиков</div>
                </div>
            </div>

            {{-- Действия & Поиск --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="scrollToBottom()" class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] border-[#30363d] text-[#c9d1d9] flex items-center gap-1" title="Прокрутить в конец">
                    <x-icon name="download" class="w-4 h-4" />
                    <span>Вниз</span>
                </button>
                <div class="relative flex items-center w-full sm:w-64">
                    <span class="absolute left-3 text-[#8b949e] pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                    <input type="text" x-model="q" @input="applyFilter()"
                           class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-8 pr-7 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] transition-colors outline-none"
                           placeholder="Поиск по логу…">
                    <button type="button" x-show="q" @click="clearQuery()" class="absolute right-2 text-[#8b949e] hover:text-[#e6edf3] text-xs p-0.5" title="Очистить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                </div>
            </div>
        </div>

        {{-- Консоль системного лога в стиле IDE Terminal --}}
        <div class="rounded-xl border border-[#30363d] bg-[#010409] p-3 sm:p-4 shadow-inner overflow-hidden">
            <div class="flex items-center justify-between text-[11px] text-[#8b949e] pb-2 mb-2 border-b border-[#21262d]">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#238636] inline-block animate-pulse"></span>
                    <span class="mono">LIVE OUTPUT</span>
                </div>
                <div class="mono text-[10px]">Всего строк: <span x-text="total || {{ count($lines) }}"></span></div>
            </div>

            <div class="mono text-xs space-y-0.5 max-h-[68vh] overflow-y-auto leading-relaxed select-text font-mono" x-ref="logBody" @dblclick="clearNew()">
                @forelse ($lines as $l)
                    <div data-search="{{ strtolower(($l['time'] ?? '').' '.($l['level'] ?? '').' '.($l['message'] ?? '')) }}"
                         class="whitespace-pre-wrap break-all px-2 py-0.5 rounded transition-colors hover:bg-[#161b22]/70 flex items-start gap-2
                        @if ($l['level'] === 'error') text-[#f85149] bg-[#da3633]/5
                        @elseif ($l['level'] === 'warning' || $l['level'] === 'warn') text-[#d29922] bg-[#d29922]/5
                        @elseif ($l['level'] === 'info') text-[#c9d1d9]
                        @else text-[#8b949e] @endif">
                        @if ($l['time'])
                            <span class="text-[#8b949e] opacity-70 shrink-0 select-none text-[11px]">[{{ $l['time'] }}]</span>
                        @endif
                        @if ($l['level'] === 'error')
                            <span class="px-1 py-0.2 rounded text-[9px] font-bold bg-[#da3633]/20 text-[#f85149] border border-[#da3633]/30 shrink-0 uppercase select-none">ERR</span>
                        @elseif ($l['level'] === 'warning' || $l['level'] === 'warn')
                            <span class="px-1 py-0.2 rounded text-[9px] font-bold bg-[#d29922]/20 text-[#d29922] border border-[#d29922]/30 shrink-0 uppercase select-none">WARN</span>
                        @endif
                        <span class="flex-1">{{ $l['message'] }}</span>
                    </div>
                @empty
                    <div class="text-sm text-[#8b949e] py-12 text-center" x-show="total === 0">
                        <div class="text-2xl mb-1 flex justify-center"><x-icon name="file-text" class="w-8 h-8 text-[#58a6ff]" /></div>
                        Системный лог пуст.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
