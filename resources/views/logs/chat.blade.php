@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'Логи чата')

@section('header-actions')
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.location.reload()" class="btn btn-sm flex items-center gap-1.5" title="Обновить страницу">
            <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
            <span>Обновить</span>
        </button>
    </div>
@endsection

@section('content')
    @include('logs._nav')

    <div class="space-y-5" x-data="logPoller({ url: '{{ route('logs.poll.chat') }}', mode: 'chat', initialLastId: {{ $maxId ?? 0 }}, ms: 3000 })">
        
        {{-- 1. ПУЛЬТ ОТПРАВКИ СООБЩЕНИЙ В ИГРОВОЙ ЧАТ (RCON CHAT BROADCASTER) --}}
        <div class="rounded-2xl border border-slate-800 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-4 sm:p-5 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800/80 mb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#1f6feb]/20 to-[#388bfd]/10 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center shrink-0 shadow-inner">
                        <x-icon name="message-square" class="w-5 h-5 text-[#58a6ff]" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-base text-[#e6edf3]">Игровой чат сервера</h2>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 border border-emerald-500/30 text-emerald-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Live Sync (3 сек)
                            </span>
                        </div>
                        <div class="text-[11px] text-[#8b949e]">Двусторонняя связь: мониторинг сообщений игроков и отправка анонсов через RCON</div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <template x-if="newCount > 0">
                        <button type="button" @click="clearNew()" class="btn btn-xs bg-[#238636] hover:bg-[#2ea043] text-white border-[#2ea043] flex items-center gap-1.5 animate-bounce">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            <span x-text="newCount + ' новых'"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Форма отправки сообщения в чат --}}
            <form action="{{ route('logs.chat.send') }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                @csrf
                <div class="shrink-0 sm:w-44">
                    <select name="type" class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] rounded-xl px-3 py-2 text-xs text-[#e6edf3] font-medium outline-none cursor-pointer">
                        <option value="announce">Анонс (#announce)</option>
                        <option value="say">Общий чат (#say)</option>
                    </select>
                </div>

                <div class="relative flex-1">
                    <input type="text" name="message" required maxlength="500"
                           class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-xl px-3.5 py-2 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none transition-all"
                           placeholder="Напишите текст сообщения или оповещения для игроков на сервере...">
                </div>

                <button type="submit" class="btn btn-accent btn-sm px-4 py-2 text-xs font-semibold shrink-0 flex items-center justify-center gap-1.5 shadow-md shadow-[#1f6feb]/20">
                    <x-icon name="send" class="w-3.5 h-3.5" />
                    <span>Отправить в чат</span>
                </button>
            </form>
        </div>

        {{-- 2. ЛЕНТА СООБЩЕНИЙ ЧАТА --}}
        <div class="panel p-4 sm:p-5 space-y-4">
            
            {{-- Фильтр и строка поиска --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#30363d]">
                <div class="text-xs text-[#8b949e]">
                    Всего сообщений в базе: <strong class="text-[#e6edf3]">{{ $events->total() }}</strong>
                </div>

                <div class="relative flex items-center w-full sm:w-80">
                    <span class="absolute left-3 text-[#8b949e] pointer-events-none"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                    <input type="text" x-model="q" @input="applyFilter()"
                           class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-xl pl-8 pr-7 py-1.5 text-xs text-[#e6edf3] placeholder-[#8b949e] transition-colors outline-none"
                           placeholder="Фильтр по нику, каналу или тексту...">
                    <button type="button" x-show="q" @click="clearQuery()" class="absolute right-2 text-[#8b949e] hover:text-[#e6edf3] text-xs p-0.5" title="Очистить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                </div>
            </div>

            @if ($events->isEmpty())
                <div class="text-sm text-[#8b949e] text-center py-12 px-4" x-show="lastId === 0">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#1f6feb]/10 to-[#388bfd]/5 border border-[#388bfd]/25 flex items-center justify-center mx-auto mb-3 text-[#58a6ff]">
                        <x-icon name="message-square" class="w-8 h-8" />
                    </div>
                    <div class="font-bold text-base text-[#e6edf3] mb-1">Ожидание сообщений чата</div>
                    <p class="text-xs text-[#8b949e] max-w-md mx-auto mb-4">
                        Служба <code class="mono text-[#58a6ff]">scum:watch</code> активна и синхронизирует чат. Как только игроки напишут в глобальный, локальный или отрядный чат, реплики появятся здесь в реальном времени.
                    </p>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Вы можете отправить первое тестовое сообщение через форму вверху</span>
                    </div>
                </div>
            @endif

            {{-- Лента сообщений --}}
            <div class="space-y-2.5" x-ref="logBody" @dblclick="clearNew()">
                @foreach ($events as $e)
                    @php
                        $ch = strtolower(trim((string)($e->channel ?? 'global')));
                        $isAnnounce = str_contains($ch, 'announce');
                        $isAdmin = str_contains($ch, 'admin');
                        $isSquad = str_contains($ch, 'squad');
                        $isLocal = str_contains($ch, 'local');
                    @endphp
                    <div data-id="{{ $e->id }}" class="card p-3 sm:p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:border-[#484f58] transition-colors group"
                         data-search="{{ strtolower(($e->player_name ?? '').' '.($e->channel ?? '').' '.($e->message ?? '')) }}">
                        <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                            <div @class([
                                'w-9 h-9 rounded-xl border flex items-center justify-center text-xs font-bold shrink-0 shadow-sm',
                                'bg-[#1f6feb]/20 border-[#388bfd]/40 text-[#58a6ff]' => !$isAnnounce && !$isAdmin,
                                'bg-amber-500/20 border-amber-500/40 text-amber-300' => $isAnnounce,
                                'bg-purple-500/20 border-purple-500/40 text-purple-300' => $isAdmin,
                            ])>
                                {{ strtoupper(mb_substr($e->player_name ?? '?', 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-xs text-[#e6edf3] truncate">{{ $e->player_name ?? 'Неизвестный' }}</span>
                                    
                                    {{-- Бейдж канала --}}
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                                        'bg-blue-500/10 border-blue-500/30 text-blue-400' => !$isAnnounce && !$isAdmin && !$isSquad && !$isLocal,
                                        'bg-amber-500/15 border-amber-500/30 text-amber-400' => $isAnnounce,
                                        'bg-purple-500/15 border-purple-500/30 text-purple-400' => $isAdmin,
                                        'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' => $isSquad,
                                        'bg-slate-800 border-slate-700 text-slate-300' => $isLocal,
                                    ])>
                                        {{ $e->channel ?: 'Global' }}
                                    </span>

                                    @if (!empty($e->steam_id))
                                        <span class="text-[11px] text-[#8b949e] font-mono hidden md:inline">({{ $e->steam_id }})</span>
                                    @endif

                                    <span class="text-[11px] text-[#8b949e] mono ml-auto">
                                        {{ Carbon::parse($e->created_at)->format('d.m.Y H:i:s') }}
                                    </span>
                                </div>
                                <div class="text-sm text-[#e6edf3] mt-1 break-words font-sans selection:bg-[#1f6feb]/30">{{ $e->message }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex justify-center pt-2">
                {{ $events->links('vendor.pagination.panel') }}
            </div>
        </div>
    </div>
@endsection
