@extends('layouts.app')

@section('title', 'Плагин: Кастомный Airdrop')

@section('content')
    <div class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#f0883e]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#f0883e]/30 to-[#d29922]/20 border border-[#f0883e]/40 flex items-center justify-center text-[#f0883e] shadow-lg shadow-[#f0883e]/10 shrink-0"><x-icon name="parachute" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Плагин: Кастомный Airdrop</h1>
                            @if ($airdrop['autoDropEnabled'] ?? false)
                                <span class="badge badge-green flex items-center gap-1 py-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                                    Авто-дроп включен
                                </span>
                            @else
                                <span class="badge badge-gray">Авто-дроп выключен</span>
                            @endif
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Автоматический и ручной сброс наградных ящиков с лутом в откалиброванные точки карты.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Калибровка:</span>
                        <span class="font-bold text-[#58a6ff]">{{ $calibration['recorded'] }}/{{ $calibration['total'] }}</span>
                    </div>
                    <a href="{{ route('map.index') }}" class="btn btn-sm flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="map" class="w-4 h-4 text-emerald-400" /> Карта</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ===== 3 Колонки настроек ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- 1. Конфигурация дропа (4 колонки) --}}
            <div class="panel p-5 md:p-6 lg:col-span-4 rounded-2xl flex flex-col justify-between space-y-4">
                <form action="{{ route('plugins.airdrop.save') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="pb-3 border-b border-[#30363d]">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="settings" class="w-4 h-4 text-sky-400" /> Настройки сундука</span>
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Модель сундука (Item ID)</label>
                            <input type="text" name="chest_item" class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]"
                                   value="{{ $airdrop['chestItem'] ?? 'Improvised_Metal_Chest' }}">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Мин. лута</label>
                                <input type="number" name="min_items" min="1" max="50" class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]"
                                       value="{{ $airdrop['minItems'] ?? 3 }}">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Макс. лута</label>
                                <input type="number" name="max_items" min="1" max="100" class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]"
                                       value="{{ $airdrop['maxItems'] ?? 8 }}">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Кулдаун между дропами (мин)</label>
                            <input type="number" name="cooldown_minutes" min="1" class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]"
                                   value="{{ $airdrop['cooldownMinutes'] ?? 60 }}">
                        </div>

                        {{-- Авто-сброс по таймеру --}}
                        <div class="p-3.5 rounded-xl border border-[#30363d] bg-[#0d1117] space-y-3 pt-3">
                            <div class="flex items-center justify-between">
                                <label for="ad-auto" class="text-xs font-bold text-[#e6edf3] cursor-pointer">Авто-сброс по таймеру</label>
                                <label class="toggle">
                                    <input type="checkbox" name="auto_drop_enabled" id="ad-auto" value="1"
                                           @checked(($airdrop['autoDropEnabled'] ?? false))>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] mb-1">Интервал (мин)</label>
                                    <input type="number" name="auto_drop_interval" class="input text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]"
                                           value="{{ $airdrop['autoDropIntervalMinutes'] ?? 30 }}">
                                </div>
                                <div>
                                    <label class="block text-[11px] text-[#8b949e] mb-1">Мин. игроков</label>
                                    <input type="number" name="auto_drop_min_players" class="input text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]"
                                           value="{{ $airdrop['autoDropMinPlayers'] ?? 2 }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-full py-2 font-bold shadow-md shadow-[#238636]/20">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить настройки</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. Ручной сброс аирдропа (3 колонки) --}}
            <div class="panel p-5 md:p-6 lg:col-span-3 rounded-2xl flex flex-col justify-between space-y-4">
                <div>
                    <div class="pb-3 border-b border-[#30363d]">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="target" class="w-4 h-4 text-amber-400" /> Ручной сброс</span>
                        </h2>
                    </div>

                    <form action="{{ route('plugins.airdrop.drop') }}" method="POST" class="space-y-3 mt-3">
                        @csrf
                        <div class="text-xs text-[#8b949e]">
                            Укажите координаты или оставьте пустыми для выбора случайной точки на карте.
                        </div>

                        <div class="space-y-2">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">X координата (опц.)</label>
                                <input type="text" name="x" class="input mono text-xs py-1.5 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]" placeholder="Случайно">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Y координата (опц.)</label>
                                <input type="text" name="y" class="input mono text-xs py-1.5 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]" placeholder="Случайно">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Z высота (опц.)</label>
                                <input type="text" name="z" class="input mono text-xs py-1.5 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]" placeholder="0">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-accent w-full py-2 font-bold mt-2 shadow-md shadow-[#1f6feb]/20">
                            <x-icon name="parachute" class="w-4 h-4 inline-block mr-1.5" /> Сбросить аирдроп
                        </button>
                    </form>
                </div>

                <div class="p-3 rounded-xl border border-[#30363d] bg-[#0d1117] text-[11px] text-[#8b949e] leading-snug">
                    <x-icon name="info" class="w-4 h-4 inline mr-1 text-amber-400" /> При сбросе игрокам в чат отправляются координаты и сектор выпадения ящика.
                </div>
            </div>

            {{-- 3. Калибровка высот карты (5 колонок) --}}
            <div class="panel p-5 md:p-6 lg:col-span-5 rounded-2xl flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-[#30363d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="crosshair" class="w-5 h-5 text-sky-400" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Калибровка высот (Z)</h2>
                        </div>
                        @if ($calibration['remaining'] > 0)
                            <span class="badge badge-yellow text-xs">Осталось: {{ $calibration['remaining'] }}</span>
                        @else
                            <span class="badge badge-green text-xs">Завершена 100%</span>
                        @endif
                    </div>

                    {{-- Прогресс-бар калибровки --}}
                    <div class="mt-3 space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-[#8b949e]">Прогресс:</span>
                            <span class="text-[#e6edf3]">{{ $calibration['recorded'] }} из {{ $calibration['total'] }} точек</span>
                        </div>
                        <div class="w-full h-2.5 bg-[#0d1117] border border-[#30363d] rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-[#1f6feb] to-[#2ea043] transition-all"
                                 style="width: {{ $calibration['total'] ? round($calibration['recorded'] / $calibration['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    {{-- Таблица точек --}}
                    <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117] max-h-52 overflow-y-auto mt-3">
                        <table class="table text-xs">
                            <thead>
                                <tr class="bg-[#101623] border-b border-[#30363d]">
                                    <th class="py-2 px-2.5">#</th>
                                    <th class="py-2 px-2.5">Сектор</th>
                                    <th class="py-2 px-2.5">X, Y, Z</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#21262d]">
                                @forelse ($calibration['points'] as $i => $p)
                                    <tr class="hover:bg-[#161b22]">
                                        <td class="py-1.5 px-2.5 mono text-[#58a6ff]">{{ $i }}</td>
                                        <td class="py-1.5 px-2.5 font-semibold text-[#e6edf3]">{{ $p['sector'] ?? '—' }}</td>
                                        <td class="py-1.5 px-2.5 mono text-[#8b949e]">
                                            {{ round($p['x']) }}, {{ round($p['y']) }}, <span class="{{ ($p['z'] ?? 0) != 0 ? 'text-[#3fb950] font-bold' : '' }}">{{ round($p['z']) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-6 text-xs text-[#8b949e]">
                                            Точки калибровки не найдены
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Форма записи Z высоты --}}
                <div class="pt-3 border-t border-[#21262d] flex flex-col sm:flex-row gap-2">
                    <form action="{{ route('plugins.airdrop.calibrate.record') }}" method="POST" class="flex gap-2 flex-1">
                        @csrf
                        <input type="number" name="index" class="input mono text-xs py-1.5 bg-[#0d1117] border-[#30363d] w-16" placeholder="#" min="0" required>
                        <input type="number" name="z" class="input mono text-xs py-1.5 bg-[#0d1117] border-[#30363d] flex-1" placeholder="Z высота" step="any" required>
                        <button type="submit" class="btn btn-sm shrink-0 font-semibold flex items-center gap-1.5">
                            <x-icon name="check" class="w-3.5 h-3.5 text-[#3fb950]" />
                            <span>Записать Z</span>
                        </button>
                    </form>
                    <form action="{{ route('plugins.airdrop.calibrate.reset') }}" method="POST" onsubmit="return confirm('Сбросить калибровку всех 125 точек?')">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm shrink-0 flex items-center gap-1.5">
                            <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                            <span>Сброс</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>
@endsection