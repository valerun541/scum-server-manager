@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'RCON команды')

@section('content')
    @include('logs._nav')
    <div class="panel p-3.5 sm:p-5 space-y-4" x-data="logPoller({ url: '{{ route('logs.poll.rcon') }}', mode: 'rcon', initialLastId: {{ $maxId ?? 0 }}, ms: 3000 })">
        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#30363d]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb]/20 to-[#238636]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-lg shrink-0 shadow-inner"><x-icon name="terminal" class="w-5 h-5 text-[#58a6ff]" /></div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-bold text-base text-[#e6edf3]">История RCON команд</h2>
                        <template x-if="newCount > 0">
                            <button type="button" @click="clearNew()" class="btn btn-xs bg-[#238636] hover:bg-[#2ea043] text-white border-[#2ea043] flex items-center gap-1.5 animate-bounce">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                <span x-text="newCount + ' новых'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="text-[11px] text-[#8b949e]">Журнал всех команд, отправленных на сервер через веб-консоль или автоматизации</div>
                </div>
            </div>

            {{-- Поиск --}}
            <div class="relative flex items-center w-full sm:w-72">
                <span class="absolute left-3 text-[#8b949e] pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                <input type="text" x-model="q" @input="applyFilter()"
                       class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-lg pl-8 pr-7 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] transition-colors outline-none"
                       placeholder="Поиск по команде или ответу…">
                <button type="button" x-show="q" @click="clearQuery()" class="absolute right-2 text-[#8b949e] hover:text-[#e6edf3] text-xs p-0.5" title="Очистить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
            </div>
        </div>

        @if ($commands->isEmpty())
            <div class="text-sm text-[#8b949e] text-center py-12 px-4" x-show="lastId === 0">
                <div class="w-14 h-14 rounded-2xl bg-[#21262d]/50 border border-[#30363d] flex items-center justify-center text-2xl mx-auto mb-2 opacity-60"><x-icon name="terminal" class="w-7 h-7 text-[#58a6ff]" /></div>
                <div class="font-bold text-[#e6edf3] mb-1">Команды ещё не отправлялись</div>
                <div class="text-xs text-[#8b949e]">Отправленные через консоль RCON или сценарии автоматизации команды появятся здесь.</div>
            </div>
        @endif

        {{-- Адаптивная сетка RCON: Desktop Grid + Mobile Cards --}}
        <div class="panel overflow-hidden border border-[#30363d] bg-[#0d1117]">
            {{-- Шапка сетки на десктопе --}}
            <div class="hidden md:grid md:grid-cols-12 gap-3 px-4 py-2.5 bg-[#161b22] border-b border-[#30363d] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider items-center">
                <div class="col-span-2">Время</div>
                <div class="col-span-4">Команда</div>
                <div class="col-span-5">Ответ сервера</div>
                <div class="col-span-1 text-right">Статус</div>
            </div>

            {{-- Список строк --}}
            <div class="divide-y divide-[#21262d]" x-ref="logBody" @dblclick="clearNew()">
                @foreach ($commands as $c)
                    <div data-id="{{ $c->id }}" data-search="{{ strtolower(($c->command ?? '').' '.($c->response ?? '').' '.($c->error ?? '')) }}"
                         class="p-3 sm:px-4 sm:py-3 hover:bg-[#161b22]/70 transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center">
                        
                        {{-- 1. Время --}}
                        <div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">
                            <span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">
                                {{ Carbon::parse($c->created_at)->format('d.m.Y H:i:s') }}
                            </span>
                            <div class="md:hidden">
                                @if ($c->error)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30" title="{{ $c->error }}">
                                        <x-icon name="x" class="w-3 h-3 inline mr-1" /> Ошибка
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border bg-[#238636]/15 text-[#3fb950] border-[#238636]/30">
                                        <x-icon name="check" class="w-3 h-3 inline mr-1" /> OK
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- 2. Команда --}}
                        <div class="md:col-span-4 min-w-0">
                            <div class="flex items-center gap-1.5 group/cmd">
                                <span class="mono text-xs font-semibold text-[#58a6ff] bg-[#1f6feb]/10 border border-[#388bfd]/25 px-2.5 py-1 rounded-md break-all inline-block shadow-sm">
                                    {{ $c->command }}
                                </span>
                                <button type="button" @click="copyToClipboard('{{ addslashes($c->command) }}')"
                                        class="text-[#8b949e] hover:text-[#e6edf3] p-1 text-xs transition-colors shrink-0"
                                        title="Скопировать команду"><x-icon name="copy" class="w-3.5 h-3.5 text-[#8b949e] hover:text-[#58a6ff]" /></button>
                            </div>
                        </div>

                        {{-- 3. Ответ сервера --}}
                        <div class="md:col-span-5 min-w-0">
                            @if ($c->response)
                                <div class="mono text-xs text-[#c9d1d9] bg-[#010409] border border-[#21262d] p-2 rounded-lg max-h-24 overflow-y-auto whitespace-pre-wrap break-words leading-relaxed select-text shadow-inner"
                                     title="{{ $c->response }}">
                                    {{ $c->response }}
                                </div>
                            @elseif ($c->error)
                                <div class="mono text-xs text-[#f85149] bg-[#da3633]/10 border border-[#da3633]/20 p-2 rounded-lg whitespace-pre-wrap break-words leading-relaxed">
                                    {{ $c->error }}
                                </div>
                            @else
                                <span class="text-xs text-[#8b949e] opacity-40">—</span>
                            @endif
                        </div>

                        {{-- 4. Статус (Десктоп) --}}
                        <div class="hidden md:block md:col-span-1 md:text-right whitespace-nowrap">
                            @if ($c->error)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30" title="{{ $c->error }}">
                                    <x-icon name="x" class="w-3 h-3 inline mr-1" /> Ошибка
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border bg-[#238636]/15 text-[#3fb950] border-[#238636]/30">
                                    <x-icon name="check" class="w-3 h-3 inline mr-1" /> OK
                                </span>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex justify-center pt-2">
            {{ $commands->links('vendor.pagination.panel') }}
        </div>
    </div>
@endsection
