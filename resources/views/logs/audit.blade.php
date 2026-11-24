@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'Журнал аудита действий')

@section('content')
    @include('logs._nav')

    <div class="panel p-4 sm:p-6 space-y-5" x-data="{
        showModal: false,
        activePayload: null,
        activeAction: '',
        activeTime: '',
        openDetails(action, time, payload) {
            this.activeAction = action;
            this.activeTime = time;
            try {
                this.activePayload = typeof payload === 'string' ? JSON.parse(payload) : payload;
            } catch (e) {
                this.activePayload = payload;
            }
            this.showModal = true;
        }
    }">
        {{-- Header & Stats --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-[#21262d]">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#1f6feb]/25 to-[#388bfd]/10 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center shrink-0 shadow-inner">
                    <x-icon name="file-text" class="w-5 h-5 text-[#58a6ff]" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-black text-lg text-[#e6edf3] tracking-tight">Журнал аудита действий</h1>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#21262d] text-[#8b949e] border border-[#30363d]">
                            {{ $logs->total() }} записей
                        </span>
                    </div>
                    <div class="text-xs text-[#8b949e] mt-0.5">Неизменяемый реестр авторизаций, настроек безопасности, RCON-команд и изменений файлов</div>
                </div>
            </div>

            {{-- Quick Stats Pills --}}
            <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar">
                <a href="{{ route('logs.audit') }}"
                   @class([
                       'px-3 py-1.5 rounded-xl text-xs font-medium border transition-all shrink-0',
                       'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' => empty($currentCategory),
                       'bg-[#161b22] text-[#8b949e] border-[#21262d] hover:text-[#e6edf3]' => !empty($currentCategory),
                   ])>
                    Все события
                </a>
                <a href="{{ route('logs.audit', ['category' => 'auth']) }}"
                   @class([
                       'px-3 py-1.5 rounded-xl text-xs font-medium border transition-all shrink-0',
                       'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' => $currentCategory === 'auth',
                       'bg-[#161b22] text-[#8b949e] border-[#21262d] hover:text-[#e6edf3]' => $currentCategory !== 'auth',
                   ])>
                    Авторизация
                </a>
                <a href="{{ route('logs.audit', ['category' => 'security']) }}"
                   @class([
                       'px-3 py-1.5 rounded-xl text-xs font-medium border transition-all shrink-0',
                       'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/40 shadow-sm' => $currentCategory === 'security',
                       'bg-[#161b22] text-[#8b949e] border-[#21262d] hover:text-[#e6edf3]' => $currentCategory !== 'security',
                   ])>
                    Безопасность / 2FA
                </a>
                <a href="{{ route('logs.audit', ['category' => 'rcon']) }}"
                   @class([
                       'px-3 py-1.5 rounded-xl text-xs font-medium border transition-all shrink-0',
                       'bg-[#d29922]/15 text-[#e3b341] border-[#bb8009]/40 shadow-sm' => $currentCategory === 'rcon',
                       'bg-[#161b22] text-[#8b949e] border-[#21262d] hover:text-[#e6edf3]' => $currentCategory !== 'rcon',
                   ])>
                    RCON команды
                </a>
                <a href="{{ route('logs.audit', ['category' => 'config']) }}"
                   @class([
                       'px-3 py-1.5 rounded-xl text-xs font-medium border transition-all shrink-0',
                       'bg-[#a371f7]/15 text-[#bc8cff] border-[#8957e5]/40 shadow-sm' => $currentCategory === 'config',
                       'bg-[#161b22] text-[#8b949e] border-[#21262d] hover:text-[#e6edf3]' => $currentCategory !== 'config',
                   ])>
                    Конфигурации
                </a>
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        <form method="GET" action="{{ route('logs.audit') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 p-3 bg-[#0d1117] border border-[#21262d] rounded-2xl">
            <div class="sm:col-span-6 relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8b949e] pointer-events-none">
                    <x-icon name="search" class="w-4 h-4" />
                </span>
                <input type="text" name="search" value="{{ $search }}"
                       class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] rounded-xl pl-10 pr-4 py-2 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none transition-all"
                       placeholder="Поиск по действию, пользователю, IP или описанию...">
            </div>

            <div class="sm:col-span-3">
                <select name="category" onchange="this.form.submit()"
                        class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#58a6ff] rounded-xl px-3 py-2 text-xs text-[#e6edf3] outline-none">
                    <option value="">Все категории</option>
                    <option value="auth" @selected($currentCategory === 'auth')>Авторизация (auth)</option>
                    <option value="security" @selected($currentCategory === 'security')>Безопасность (security)</option>
                    <option value="rcon" @selected($currentCategory === 'rcon')>RCON команды (rcon)</option>
                    <option value="config" @selected($currentCategory === 'config')>Конфигурации (config)</option>
                    <option value="system" @selected($currentCategory === 'system')>Системные (system)</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <select name="status" onchange="this.form.submit()"
                        class="w-full bg-[#161b22] border border-[#30363d] focus:border-[#58a6ff] rounded-xl px-3 py-2 text-xs text-[#e6edf3] outline-none">
                    <option value="">Все статусы</option>
                    <option value="success" @selected($currentStatus === 'success')>Успешно (success)</option>
                    <option value="failed" @selected($currentStatus === 'failed')>Ошибка (failed)</option>
                </select>
            </div>

            <div class="sm:col-span-1 flex items-center gap-1.5">
                <button type="submit" class="btn btn-sm bg-[#1f6feb] hover:bg-[#388bfd] text-white border-[#388bfd]/50 w-full justify-center">
                    <x-icon name="filter" class="w-3.5 h-3.5" />
                </button>
                @if ($search || $currentCategory || $currentStatus)
                    <a href="{{ route('logs.audit') }}" class="btn btn-sm bg-[#21262d] hover:bg-[#30363d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d]" title="Сбросить фильтры">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                    </a>
                @endif
            </div>
        </form>

        {{-- Table Container --}}
        <div class="border border-[#21262d] rounded-2xl overflow-hidden bg-[#0c1017] shadow-sm">
            <div class="overflow-x-auto max-h-[640px] custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 z-10 bg-[#0c1017] border-b border-[#21262d] text-[#8b949e] font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-4 w-44">Время</th>
                            <th class="py-3 px-4 w-40">Пользователь</th>
                            <th class="py-3 px-4 w-36">Категория</th>
                            <th class="py-3 px-4 w-48">Действие</th>
                            <th class="py-3 px-4">Описание</th>
                            <th class="py-3 px-4 w-28 text-center">Статус</th>
                            <th class="py-3 px-4 w-24 text-right">Детали</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d]/60">
                        @forelse ($logs as $log)
                            @php
                                $badgeColor = match ($log->category) {
                                    'auth' => 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30',
                                    'security' => 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30',
                                    'rcon' => 'bg-[#d29922]/15 text-[#e3b341] border-[#bb8009]/30',
                                    'config' => 'bg-[#a371f7]/15 text-[#bc8cff] border-[#8957e5]/30',
                                    default => 'bg-[#21262d] text-[#8b949e] border-[#30363d]',
                                };
                                $isSuccess = $log->status === 'success';
                            @endphp
                            <tr class="hover:bg-[#161b22]/70 transition-colors">
                                {{-- Время --}}
                                <td class="py-3 px-4 text-[#8b949e] whitespace-nowrap font-mono text-[11px]">
                                    <div class="text-[#e6edf3] font-medium">{{ Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</div>
                                    <div class="text-[10px] text-[#8b949e]">{{ Carbon::parse($log->created_at)->diffForHumans() }}</div>
                                </td>

                                {{-- Пользователь & IP --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 font-medium text-[#e6edf3]">
                                        <x-icon name="user" class="w-3.5 h-3.5 text-[#8b949e]" />
                                        <span>{{ $log->username }}</span>
                                    </div>
                                    @if ($log->ip)
                                        <div class="font-mono text-[10px] text-[#8b949e] mt-0.5">{{ $log->ip }}</div>
                                    @endif
                                </td>

                                {{-- Категория --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border {{ $badgeColor }}">
                                        {{ $log->category }}
                                    </span>
                                </td>

                                {{-- Действие --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <code class="px-2 py-0.5 rounded bg-[#161b22] border border-[#21262d] text-[#58a6ff] text-[11px] font-mono">
                                        {{ $log->action }}
                                    </code>
                                </td>

                                {{-- Описание --}}
                                <td class="py-3 px-4 text-[#c9d1d9] max-w-md break-words">
                                    {{ $log->description }}
                                </td>

                                {{-- Статус --}}
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if ($isSuccess)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#238636]/15 text-[#3fb950] border border-[#2ea043]/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950]"></span>
                                            SUCCESS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#da3633]/15 text-[#f85149] border border-[#f85149]/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#f85149]"></span>
                                            FAILED
                                        </span>
                                    @endif
                                </td>

                                {{-- Детали / Payload --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    @if (!empty($log->payload))
                                        <button type="button"
                                                @click="openDetails('{{ $log->action }}', '{{ $log->created_at }}', {{ json_encode($log->payload) }})"
                                                class="px-2.5 py-1 rounded-lg bg-[#21262d] hover:bg-[#30363d] text-[#58a6ff] text-[11px] font-semibold border border-[#30363d] transition-colors inline-flex items-center gap-1">
                                            <x-icon name="box" class="w-3 h-3" />
                                            <span>JSON</span>
                                        </button>
                                    @else
                                        <span class="text-[#484f58]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-[#8b949e]">
                                    <div class="w-12 h-12 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-2 text-[#8b949e]">
                                        <x-icon name="file-text" class="w-6 h-6" />
                                    </div>
                                    <div class="text-sm font-semibold text-[#e6edf3]">Записей аудита не найдено</div>
                                    <div class="text-xs text-[#8b949e] mt-1">Все действия администраторов автоматически фиксируются в данном реестре.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Пагинация --}}
            @if ($logs->hasPages())
                <div class="p-3.5 border-t border-[#21262d] bg-[#0d1117] flex items-center justify-between">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>

        {{-- JSON Payload Modal --}}
        <div x-show="showModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
             @keydown.escape.window="showModal = false">
            <div class="relative w-full max-w-2xl bg-[#0c1017] border border-[#21262d] rounded-2xl shadow-2xl p-5 space-y-4"
                 @click.away="showModal = false">
                <div class="flex items-center justify-between pb-3 border-b border-[#21262d]">
                    <div class="flex items-center gap-2.5">
                        <x-icon name="box" class="w-5 h-5 text-[#58a6ff]" />
                        <div>
                            <h3 class="font-bold text-sm text-[#e6edf3]" x-text="'Детали события: ' + activeAction"></h3>
                            <div class="text-[11px] text-[#8b949e]" x-text="activeTime"></div>
                        </div>
                    </div>
                    <button type="button" @click="showModal = false" class="p-1.5 rounded-lg text-[#8b949e] hover:text-[#e6edf3] hover:bg-[#21262d] transition-colors">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                <div class="bg-[#090d14] border border-[#21262d] rounded-xl p-3.5 max-h-96 overflow-y-auto custom-scrollbar">
                    <pre class="font-mono text-xs text-[#58a6ff] whitespace-pre-wrap leading-relaxed" x-text="typeof activePayload === 'object' ? JSON.stringify(activePayload, null, 2) : activePayload"></pre>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="showModal = false" class="btn btn-sm bg-[#21262d] hover:bg-[#30363d] text-[#e6edf3] border-[#30363d]">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
