@extends('layouts.app')

@section('title', 'Плагин: SaveHome (Точки домов)')

@section('content')
    <div x-data="{
        searchQuery: '',
        homes: @js($homes ?? [])
    }" class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#2ea043]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#2ea043]/30 to-[#3fb950]/20 border border-[#2ea043]/40 flex items-center justify-center text-[#3fb950] shadow-lg shadow-[#2ea043]/10 shrink-0"><x-icon name="home" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Плагин: SaveHome</h1>
                            <span class="badge badge-green">Команды !sethome и !home</span>
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Позволяет игрокам сохранять координаты своей базы или дома и телепортироваться к ним.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Игроков с домами:</span>
                        <span class="font-bold text-[#3fb950]">{{ count($homes) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Конфигурация & Таблица домов ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- 1. Настройки лимитов и цен --}}
            <div class="panel p-5 md:p-6 lg:col-span-4 rounded-2xl flex flex-col justify-between space-y-4">
                <form action="{{ route('plugins.savehome.save') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="pb-3 border-b border-[#30363d]">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="settings" class="w-4 h-4 text-sky-400" /> Параметры SaveHome</span>
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Лимит точек на обычного игрока
                            </label>
                            <input type="number" name="max_locations" min="1" max="10"
                                   value="{{ $saveHome['maxLocations'] ?? 1 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">Сколько домов может сохранить стандартный игрок</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Лимит точек для VIP игроков
                            </label>
                            <input type="number" name="vip_max_locations" min="1" max="20"
                                   value="{{ $saveHome['vipMaxLocations'] ?? 3 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">Дополнительный лимит для обладателей VIP</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Стоимость телепортации ($)
                            </label>
                            <input type="number" step="any" name="teleport_price" min="0"
                                   value="{{ $saveHome['teleportPrice'] ?? 0 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">0 = бесплатно для всех игроков</div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-full py-2 font-bold shadow-md shadow-[#238636]/20">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить параметры</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. Таблица сохранённых домов игроков --}}
            <div class="panel p-5 md:p-6 lg:col-span-8 rounded-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                        <div class="flex items-center gap-2">
                            <x-icon name="map" class="w-5 h-5 text-emerald-400" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Сохранённые дома игроков</h2>
                        </div>
                    </div>

                    @if (count($homes) === 0)
                        <div class="text-center py-12 px-4 rounded-xl border border-[#21262d] bg-[#0c1017]/80">
                            <div class="w-12 h-12 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                                <x-icon name="home" class="w-6 h-6 opacity-60 text-[#3fb950]" />
                            </div>
                            <div class="text-sm font-semibold text-[#e6edf3]">Сохранённых домов пока нет</div>
                            <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">
                                Игроки могут сохранять точки баз прямо в чате игры через команду <code class="mono text-[#58a6ff] bg-[#161b22] px-1.5 py-0.5 rounded border border-[#21262d]">!sethome</code>.
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117] max-h-[460px] overflow-y-auto">
                            <table class="table text-xs">
                                <thead>
                                    <tr class="bg-[#101623] border-b border-[#30363d]">
                                        <th class="py-2.5 px-3">Игрок (SteamID)</th>
                                        <th class="py-2.5 px-3">Название</th>
                                        <th class="py-2.5 px-3">Координаты</th>
                                        <th class="py-2.5 px-3 text-right">Действие</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#21262d]">
                                    @foreach ($homes as $sid => $locs)
                                        @foreach ((array)$locs as $loc)
                                            <tr class="hover:bg-[#161b22] transition-colors">
                                                <td class="py-2.5 px-3 font-semibold text-[#e6edf3]">
                                                    <a href="{{ route('scumdb.player', ['steamId' => $sid]) }}" class="mono text-[#58a6ff] hover:underline">
                                                        {{ $sid }}
                                                    </a>
                                                </td>
                                                <td class="py-2.5 px-3 font-medium text-[#e6edf3]">
                                                    {{ $loc['name'] ?? 'Дом' }}
                                                </td>
                                                <td class="py-2.5 px-3 mono text-[#8b949e]">
                                                    X: {{ round($loc['x'] ?? 0) }} | Y: {{ round($loc['y'] ?? 0) }} | Z: {{ round($loc['z'] ?? 0) }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right">
                                                    <button type="button" @click="navigator.clipboard.writeText('#teleport {{ round($loc['x'] ?? 0) }} {{ round($loc['y'] ?? 0) }} {{ round($loc['z'] ?? 0) }}'); window.toast && window.toast('RCON телепорт скопирован', 'success')"
                                                            class="btn btn-xs flex items-center gap-1 inline-flex" title="Скопировать RCON команду">
                                                        <span class="inline-flex items-center gap-1.5"><x-icon name="terminal" class="w-3.5 h-3.5 text-[#58a6ff]" /> #teleport</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>
@endsection