@extends('layouts.app')

@section('title', 'Плагин: Награды и Рейтинг (Rewards)')

@section('content')
    <div class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#d29922]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#d29922]/30 to-[#f0883e]/20 border border-[#d29922]/40 flex items-center justify-center text-[#d29922] shadow-lg shadow-[#d29922]/10 shrink-0"><x-icon name="trophy" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Плагин: Награды и Рейтинг</h1>
                            <span class="badge badge-yellow">Автоматические выплаты</span>
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Поощрение игроков за проведенное время онлайн и периодические награды топ-игрокам сервера.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Всего онлайн:</span>
                        <span class="font-bold text-[#58a6ff]">{{ gmdate('Hч iм', $totalOnlineSeconds) }}</span>
                    </div>
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">В рейтинге:</span>
                        <span class="font-bold text-[#3fb950]">{{ count($leaderboard) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Конфигурация: Часовые + Топ награды ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- 1. Часовые награды онлайн --}}
            <div class="panel p-5 md:p-6 rounded-2xl flex flex-col justify-between space-y-4 border-t-2 border-t-[#58a6ff]">
                <form action="{{ route('plugins.rewards.save') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="flex items-center justify-between pb-3 border-b border-[#30363d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="clock" class="w-4 h-4 text-[#58a6ff]" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Часовые награды (Онлайн)</h2>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="hourly_enabled" id="rw-hourly" value="1"
                                   @checked(($rewards['hourlyEnabled'] ?? false))>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5 text-xs">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><span class="inline-flex items-center gap-1"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Деньги / час</span></label>
                            <input type="number" step="any" name="hourly_money" value="{{ $rewards['hourlyMoney'] ?? 0 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /> Золото / час</label>
                            <input type="number" step="any" name="hourly_gold" value="{{ $rewards['hourlyGold'] ?? 0 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1" /> Слава / час</label>
                            <input type="number" step="any" name="hourly_fame" value="{{ $rewards['hourlyFame'] ?? 0 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить</span>
                        </button>
                    </div>
                </form>

                <div class="pt-3 border-t border-[#21262d] flex items-center justify-between gap-2">
                    <form action="{{ route('plugins.rewards.hourly') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-accent btn-sm">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="zap" class="w-4 h-4 text-amber-400" /> Выдать часовые сейчас</span>
                        </button>
                    </form>
                    <span class="text-[11px] text-[#8b949e]">Авто-выдача раз в минуту</span>
                </div>
            </div>

            {{-- 2. Топ-награды за период --}}
            <div class="panel p-5 md:p-6 rounded-2xl flex flex-col justify-between space-y-4 border-t-2 border-t-[#d29922]">
                <form action="{{ route('plugins.rewards.save') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="flex items-center justify-between pb-3 border-b border-[#30363d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="trophy" class="w-5 h-5 text-[#e3b341]" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Награды лидерборда (Топ)</h2>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="top_enabled" id="rw-top" value="1"
                                   @checked(($rewards['topEnabled'] ?? false))>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">Период (дней)</label>
                            <input type="number" name="top_interval_days" value="{{ $rewards['topIntervalDays'] ?? 1 }}" min="1"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">Кол-во мест в топе</label>
                            <input type="number" name="top_count" value="{{ $rewards['topCount'] ?? 3 }}" min="1" max="10"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5 text-xs">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><span class="inline-flex items-center gap-1"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Деньги</span></label>
                            <input type="number" step="any" name="top_money" value="{{ $rewards['topMoney'] ?? 1000 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /> Золото</label>
                            <input type="number" step="any" name="top_gold" value="{{ $rewards['topGold'] ?? 100 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1"><x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1" /> Слава</label>
                            <input type="number" step="any" name="top_fame" value="{{ $rewards['topFame'] ?? 0 }}"
                                   class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <button type="submit" class="btn btn-primary text-xs py-2 px-4 font-semibold">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить</span>
                        </button>
                    </div>
                </form>

                <div class="pt-3 border-t border-[#21262d] flex items-center justify-between gap-2 flex-wrap text-xs">
                    <form action="{{ route('plugins.rewards.top') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-accent btn-sm">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="zap" class="w-4 h-4 text-amber-400" /> Выдать топ сейчас</span>
                        </button>
                    </form>
                    <span class="text-[11px] text-[#8b949e]">
                        Посл. выплата: <strong class="text-[#e6edf3]">{{ ($data['lastTopRewardTime'] ?? 0) ? date('d.m.Y H:i', $data['lastTopRewardTime']) : 'ещё не было' }}</strong>
                    </span>
                </div>
            </div>

        </div>

        {{-- ===== Лидерборд игроков ===== --}}
        <div class="panel p-5 md:p-6 rounded-2xl">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <x-icon name="trophy" class="w-6 h-6 text-amber-400" />
                    <h2 class="font-bold text-base text-[#e6edf3]">Лидерборд игроков по онлайну</h2>
                </div>
                <span class="badge badge-gray mono text-xs">{{ count($leaderboard) }} участников</span>
            </div>

            @if (count($leaderboard) === 0)
                <div class="text-sm text-[#8b949e] text-center py-12 bg-[#0d1117]/50 rounded-xl border border-[#30363d]/50">
                    Рейтинг пуст. Данные автоматически собираются демоном слежения логов.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117]">
                    <table class="table text-xs">
                        <thead>
                            <tr class="bg-[#101623] border-b border-[#30363d]">
                                <th class="py-3 px-3 w-12 text-center">Ранг</th>
                                <th class="py-3 px-3">Игрок</th>
                                <th class="py-3 px-3">SteamID</th>
                                <th class="py-3 px-3">Время в игре</th>
                                <th class="py-3 px-3">Баланс ($)</th>
                                <th class="py-3 px-3">Золото</th>
                                <th class="py-3 px-3">Слава</th>
                                <th class="py-3 px-3 text-right">Действие</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#21262d]">
                            @foreach ($leaderboard as $i => $p)
                                <tr class="hover:bg-[#161b22] transition-colors">
                                    <td class="py-2.5 px-3 text-center font-bold">
                                        @if ($i === 0)
                                            <x-icon name="trophy" class="w-4 h-4 text-[#e3b341] inline-block" />
                                        @elseif ($i === 1)
                                            <x-icon name="award" class="w-4 h-4 text-[#8b949e] inline-block" />
                                        @elseif ($i === 2)
                                            <x-icon name="award" class="w-4 h-4 text-[#d29922] inline-block" />
                                        @else
                                            <span class="text-[#8b949e]">#{{ $i + 1 }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-[#e6edf3]">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="user" class="w-4 h-4 text-sky-400" />
                                            <span>{{ $p['playerName'] ?: 'Неизвестный' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-[#e6edf3]">
                                        <a href="{{ route('scumdb.player', ['steamId' => $p['steamId']]) }}" class="mono text-[#58a6ff] hover:underline">
                                            {{ $p['steamId'] }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-[#3fb950]">
                                        {{ gmdate('Hч iм', $p['onlineSeconds'] ?? 0) }}
                                    </td>
                                    <td class="py-2.5 px-3 mono text-[#e6edf3]">
                                        ${{ number_format($p['money'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="py-2.5 px-3 mono text-[#d29922]">
                                        <x-icon name="coins" class="w-3 h-3 text-[#d29922] inline-block mr-1" />{{ number_format($p['gold'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="py-2.5 px-3 mono text-[#a371f7]">
                                        <x-icon name="star" class="w-3 h-3 text-[#e3b341] inline-block mr-1" />{{ number_format($p['fame'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <button type="button" class="btn btn-xs" onclick="openModal(@js(route('plugins.rewards.player', $p['steamId'])), @js('Рейтинг: '.$p['playerName']))">
                                            Детали
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ===== Чёрный список рейтинга ===== --}}
        <div class="panel p-5 md:p-6 rounded-2xl">
            <div class="flex items-center gap-2.5 mb-3">
                <x-icon name="volume-x" class="w-5 h-5 text-red-400" />
                <h2 class="font-bold text-base text-[#e6edf3]">Чёрный список рейтинга</h2>
            </div>
            <p class="text-xs text-[#8b949e] mb-3">
                Игроки из этого списка (например, администраторы сервера) исключаются из начисления топ-наград и лидерборда.
            </p>
            <form action="{{ route('plugins.rewards.blacklist') }}" method="POST" class="space-y-3">
                @csrf
                <textarea name="blacklist" rows="3" class="input mono text-xs leading-relaxed bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full"
                          placeholder="76561198012345678, 76561198099999999">{{ implode("\n", $blacklist) }}</textarea>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary text-xs py-2 px-5 font-semibold">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить</span> чёрный список
                    </button>
                </div>
            </form>
        </div>

    </div>
@endsection