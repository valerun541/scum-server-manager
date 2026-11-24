@extends('layouts.app')

@section('title', 'Плагин: VIP статус')

@section('content')
    <div x-data="{
        searchQuery: '',
        players: @js($players ?? []),
        presetDays(n) {
            document.getElementById('vip-days-input').value = n;
        }
    }" class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#d29922]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#a371f7]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#d29922]/30 to-[#f0883e]/20 border border-[#d29922]/40 flex items-center justify-center text-[#d29922] shadow-lg shadow-[#d29922]/10 shrink-0"><x-icon name="crown" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Плагин: VIP система</h1>
                            <x-ui.badge variant="yellow"><x-icon name="crown" class="w-3.5 h-3.5 text-amber-400" /> Привилегии и Бонусы</x-ui.badge>
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Настройка ежедневных и стартовых бонусов (валюта, слава, лут) и управление активными VIP игроками.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">VIP игроков:</span>
                        <span class="font-bold text-[#d29922]">{{ count($players) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Конфигурация бонусов VIP ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- 1. Настройки бонусов --}}
            <div class="panel p-5 md:p-6 lg:col-span-6 rounded-2xl flex flex-col justify-between space-y-5">
                <form action="{{ route('plugins.vip.save') }}" method="POST" class="space-y-5">
                    @csrf
                    <div class="flex items-center justify-between pb-3 border-b border-[#30363d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="settings" class="w-5 h-5 text-sky-400" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Бонусы VIP статуса</h2>
                        </div>
                    </div>

                    {{-- Стартовый бонус VIP --}}
                    <div class="p-4 rounded-xl border border-[#30363d] bg-[#0d1117] space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-xs text-[#58a6ff] flex items-center gap-1.5">
                                <x-icon name="zap" class="w-4 h-4 text-amber-400" /> Стартовый бонус при получении VIP
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <label class="block text-[#8b949e] mb-1"><span class="inline-flex items-center gap-1"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Деньги ($)</span></label>
                                <input type="number" step="any" name="starter_money" value="{{ $vip['starterBonus']['money'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-[#8b949e] mb-1"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /> Золото</label>
                                <input type="number" step="any" name="starter_gold" value="{{ $vip['starterBonus']['gold'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-[#8b949e] mb-1"><x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1" /> Слава</label>
                                <input type="number" step="any" name="starter_fame" value="{{ $vip['starterBonus']['fame'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Предметы стартового бонуса (&quot;ID&quot; &quot;Название&quot;)</label>
                            <textarea name="starter_items" rows="3" placeholder='"M4A1" "Винтовка M4A1"'
                                      class="input mono text-xs leading-relaxed bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] w-full">{{ $starterItemsText }}</textarea>
                        </div>
                    </div>

                    {{-- Дейли бонус VIP --}}
                    <div class="p-4 rounded-xl border border-[#30363d] bg-[#0d1117] space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-xs text-[#d29922] flex items-center gap-1.5">
                                <x-icon name="sparkles" class="w-4 h-4 text-amber-400" /> Ежедневный VIP бонус
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <label class="block text-[#8b949e] mb-1"><span class="inline-flex items-center gap-1"><x-icon name="coins" class="w-3.5 h-3.5 text-emerald-400" /> Деньги ($)</span></label>
                                <input type="number" step="any" name="daily_money" value="{{ $vip['dailyBonus']['money'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-[#8b949e] mb-1"><x-icon name="coins" class="w-3.5 h-3.5 text-[#d29922] inline-block mr-1" /> Золото</label>
                                <input type="number" step="any" name="daily_gold" value="{{ $vip['dailyBonus']['gold'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-[#8b949e] mb-1"><x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1" /> Слава</label>
                                <input type="number" step="any" name="daily_fame" value="{{ $vip['dailyBonus']['fame'] ?? 0 }}"
                                       class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Предметы ежедневного бонуса (&quot;ID&quot; &quot;Название&quot;)</label>
                            <textarea name="daily_items" rows="3" placeholder='"Weapon_Ammunition_556x45_AP" "Патроны 5.56 AP"'
                                      class="input mono text-xs leading-relaxed bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] w-full">{{ $dailyItemsText }}</textarea>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn btn-primary font-bold py-2 px-6">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить бонусы</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. Управление VIP игроками --}}
            <div class="panel p-5 md:p-6 lg:col-span-6 rounded-2xl flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-3 pb-3 border-b border-[#30363d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="users" class="w-5 h-5 text-amber-400" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Список VIP игроков</h2>
                        </div>
                        <x-ui.badge variant="yellow" class="mono text-xs">{{ count($players) }} игроков</x-ui.badge>
                    </div>

                    {{-- Форма добавления VIP игрока --}}
                    <form action="{{ route('plugins.vip.add') }}" method="POST" class="p-3.5 rounded-xl border border-[#30363d] bg-[#0d1117] space-y-2.5 mb-4">
                        @csrf
                        <label class="block text-xs font-semibold text-[#8b949e]">Выдать VIP игроку:</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" name="steam_id" class="input mono text-xs py-2 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] flex-1"
                                   placeholder="SteamID (17 цифр)" required>
                            <input type="number" name="days" id="vip-days-input" class="input text-xs py-2 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] sm:w-24"
                                   value="30" min="1" placeholder="Дней">
                            <button type="submit" class="btn btn-primary text-xs py-2 px-4 whitespace-nowrap font-bold flex items-center justify-center gap-1.5 shadow-md shadow-[#238636]/20">
                                <x-icon name="plus" class="w-3.5 h-3.5" />
                                <span>Выдать VIP</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap text-[11px] text-[#8b949e]">
                            <span>Быстрый срок:</span>
                            <button type="button" @click="presetDays(7)" class="btn btn-xs">7 дней</button>
                            <button type="button" @click="presetDays(30)" class="btn btn-xs">30 дней</button>
                            <button type="button" @click="presetDays(90)" class="btn btn-xs">90 дней</button>
                            <button type="button" @click="presetDays(365)" class="btn btn-xs">1 год</button>
                        </div>
                    </form>

                    {{-- Список VIP игроков --}}
                    @if (count($players) === 0)
                        <div class="text-center py-12 px-4 rounded-xl border border-[#21262d] bg-[#0c1017]/80">
                            <div class="w-12 h-12 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                                <x-icon name="crown" class="w-6 h-6 opacity-60 text-amber-400" />
                            </div>
                            <div class="text-sm font-semibold text-[#e6edf3]">VIP игроков пока нет</div>
                            <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">
                                Добавьте первого игрока с привилегиями через форму выше по Steam ID.
                            </div>
                        </div>
                    @else
                        <div class="space-y-2 max-h-[380px] overflow-y-auto pr-1">
                            @foreach ($players as $p)
                                @php
                                    $expiresAt = $p['expiresAt'] ?? 0;
                                    $diff = $expiresAt - time();
                                    $expired = $diff < 0;
                                    $daysLeft = max(0, floor($diff / 86400));
                                    $hoursLeft = max(0, floor(($diff % 86400) / 3600));
                                @endphp
                                <div class="p-3 rounded-xl border border-[#30363d] bg-[#0d1117] hover:border-[#484f58] transition-all flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg bg-[#21262d] border border-[#30363d] flex items-center justify-center text-base shrink-0">
                                            <x-icon name="crown" class="w-4 h-4 text-amber-400 inline" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="mono text-xs font-bold text-[#e6edf3]">{{ $p['steamId'] }}</span>
                                                @if ($expired)
                                                    <span class="badge badge-red text-[10px]">Истёк</span>
                                                @else
                                                    <span class="badge badge-green text-[10px]">Активен</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-[#8b949e] mt-0.5">
                                                До: <span class="text-[#e6edf3] font-medium">{{ date('d.m.Y H:i', $expiresAt) }}</span>
                                                @if (!$expired)
                                                    <span class="text-[#58a6ff]">({{ $daysLeft }} дн. {{ $hoursLeft }} ч.)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="navigator.clipboard.writeText('{{ $p['steamId'] }}'); window.toast && window.toast('SteamID скопирован', 'info')"
                                                class="btn btn-xs" title="Скопировать SteamID"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                        <form action="{{ route('plugins.vip.remove') }}" method="POST" class="inline"
                                              onsubmit="return confirm('Удалить VIP статус у игрока {{ $p['steamId'] }}?')">
                                            @csrf
                                            <input type="hidden" name="steam_id" value="{{ $p['steamId'] }}">
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                Удалить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>
@endsection