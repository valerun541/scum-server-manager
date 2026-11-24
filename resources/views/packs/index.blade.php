@extends('layouts.app')

@section('title', 'Паки и Выдача предметов')

@section('content')
    <div x-data="{
        searchQuery: '',
        targetPack: 'starter',
        selectedItem: null,
        starterEnabled: @js((bool)($packs['starter']['enabled'] ?? false)),
        dailyEnabled: @js((bool)($packs['daily']['enabled'] ?? false)),
        starterCooldown: @js((int)($packs['starter']['cooldownHours'] ?? 0)),
        dailyCooldown: @js((int)($packs['daily']['cooldownHours'] ?? 24)),
        starterItemsText: @js(collect($packs['starter']['items'] ?? [])->map(fn($i) => '"' . $i['id'] . '" "' . ($i['name'] ?? '') . '"')->implode("\n")),
        dailyItemsText: @js(collect($packs['daily']['items'] ?? [])->map(fn($i) => '"' . $i['id'] . '" "' . ($i['name'] ?? '') . '"')->implode("\n")),
        allItems: @js($items ?? []),
        cooldownSearch: '',

        get filteredItems() {
            if (!this.searchQuery.trim()) return this.allItems.slice(0, 100);
            const q = this.searchQuery.toLowerCase().trim();
            return this.allItems.filter(i => (i.id && i.id.toLowerCase().includes(q)) || (i.name && i.name.toLowerCase().includes(q))).slice(0, 100);
        },

        addItemToPack(item, packKey) {
            const line = `&quot;${item.id}&quot; &quot;${item.name || item.id}&quot;`.replace(/&quot;/g, '\"');
            if (packKey === 'starter') {
                this.starterItemsText = (this.starterItemsText.trim() ? this.starterItemsText.trim() + '\n' : '') + line;
            } else {
                this.dailyItemsText = (this.dailyItemsText.trim() ? this.dailyItemsText.trim() + '\n' : '') + line;
            }
            if (window.toast) window.toast(`Предмет «${item.name || item.id}» добавлен в ${packKey === 'starter' ? 'Стартовый' : 'Дейли'} пак`, 'success');
        }
    }" class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#2ea043]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb]/30 to-[#388bfd]/10 border border-[#1f6feb]/40 flex items-center justify-center text-[#58a6ff] shadow-lg shadow-[#1f6feb]/10 shrink-0"><x-icon name="package" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Паки и Выдача предметов</h1>
                            @if ($rconConnected ?? false)
                                <span class="badge badge-green flex items-center gap-1.5 py-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                                    RCON подключен
                                </span>
                            @else
                                <span class="badge badge-yellow flex items-center gap-1.5 py-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#d29922]"></span>
                                    RCON отключен
                                </span>
                            @endif
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Настройка автоматических стартовых и ежедневных наборов, быстрая выдача и сброс кулдаунов.
                        </p>
                    </div>
                </div>

                {{-- Быстрые счетчики --}}
                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Стартовый:</span>
                        <span class="font-bold" :class="starterEnabled ? 'text-[#3fb950]' : 'text-[#8b949e]'" x-text="starterEnabled ? 'ВКЛ' : 'ВЫКЛ'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Дейли:</span>
                        <span class="font-bold" :class="dailyEnabled ? 'text-[#3fb950]' : 'text-[#8b949e]'" x-text="dailyEnabled ? 'ВКЛ' : 'ВЫКЛ'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Кулдаунов:</span>
                        <span class="font-bold text-[#58a6ff]">{{ count($cooldowns) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Конфигурация паков (Starter & Daily) ===== --}}
        <form action="{{ route('packs.save') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- 1. СТАРТОВЫЙ ПАК --}}
                <div class="panel p-5 md:p-6 rounded-2xl flex flex-col justify-between border-t-2 border-t-[#58a6ff]">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <x-icon name="zap" class="w-6 h-6 text-sky-400" />
                                <div>
                                    <h2 class="font-bold text-base text-[#e6edf3]">Стартовый пак</h2>
                                    <div class="text-xs text-[#8b949e]">Выдаётся новичкам по команде <code class="mono text-[#58a6ff]">!startpack</code></div>
                                </div>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" name="starter_enabled" value="1" x-model="starterEnabled">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                    Кулдаун (в часах, 0 = одноразово или без кулдауна)
                                </label>
                                <input type="number" name="starter_cooldown" x-model="starterCooldown" min="0"
                                       class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <label class="block text-xs font-semibold text-[#8b949e]">
                                        Список предметов (формат: &quot;ID&quot; &quot;Название&quot;)
                                    </label>
                                    <span class="text-[11px] text-[#8b949e]" x-text="starterItemsText.trim() ? starterItemsText.trim().split('\n').length + ' предм.' : '0 предм.'"></span>
                                </div>
                                <textarea name="starter_items" x-model="starterItemsText" rows="7"
                                          placeholder='"1H_Wooden_Club" "Деревянная дубинка"&#10;"Apple" "Яблоко"'
                                          class="input mono text-xs leading-relaxed bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full resize-y"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#8b949e]">
                        <span>Нажмите на предмет в библиотеке внизу для добавления</span>
                        <span class="badge" :class="starterEnabled ? 'badge-green' : 'badge-gray'" x-text="starterEnabled ? 'Активен' : 'Отключен'"></span>
                    </div>
                </div>

                {{-- 2. ЕЖЕДНЕВНЫЙ (ДЕЙЛИ) ПАК --}}
                <div class="panel p-5 md:p-6 rounded-2xl flex flex-col justify-between border-t-2 border-t-[#d29922]">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <x-icon name="sparkles" class="w-6 h-6 text-amber-400" />
                                <div>
                                    <h2 class="font-bold text-base text-[#e6edf3]">Ежедневный (Дейли) пак</h2>
                                    <div class="text-xs text-[#8b949e]">Выдаётся раз в сутки по команде <code class="mono text-[#d29922]">!dailypack</code></div>
                                </div>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" name="daily_enabled" value="1" x-model="dailyEnabled">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                    Кулдаун (в часах, стандартно 24 ч)
                                </label>
                                <input type="number" name="daily_cooldown" x-model="dailyCooldown" min="1"
                                       class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <label class="block text-xs font-semibold text-[#8b949e]">
                                        Список предметов (формат: &quot;ID&quot; &quot;Название&quot;)
                                    </label>
                                    <span class="text-[11px] text-[#8b949e]" x-text="dailyItemsText.trim() ? dailyItemsText.trim().split('\n').length + ' предм.' : '0 предм.'"></span>
                                </div>
                                <textarea name="daily_items" x-model="dailyItemsText" rows="7"
                                          placeholder='"MRE_Cheeseburger" "Сухпаёк Чизбургер"&#10;"Water_05l" "Вода"'
                                          class="input mono text-xs leading-relaxed bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full resize-y"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[#21262d] flex items-center justify-between text-xs text-[#8b949e]">
                        <span>Нажмите на предмет в библиотеке внизу для добавления</span>
                        <span class="badge" :class="dailyEnabled ? 'badge-yellow' : 'badge-gray'" x-text="dailyEnabled ? 'Активен' : 'Отключен'"></span>
                    </div>
                </div>

            </div>

            <div class="mt-5 flex items-center justify-end">
                <button type="submit" class="btn btn-primary font-bold py-2.5 px-6 shadow-lg shadow-[#238636]/20">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить настройки паков</span>
                </button>
            </div>
        </form>

        {{-- ===== Секция: Выдача пака игроку & Кулдауны ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- 1. Выдача пака --}}
            <div class="panel p-5 md:p-6 lg:col-span-5 rounded-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5 mb-4">
                        <x-icon name="zap" class="w-5 h-5 text-amber-400" />
                        <h2 class="font-bold text-base text-[#e6edf3]">Прямая выдача пака</h2>
                    </div>

                    <form action="{{ route('packs.give') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">SteamID игрока (17 цифр)</label>
                            <input type="text" name="steam_id" class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]"
                                   placeholder="76561198012345678" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Тип пака</label>
                            <select name="type" class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                                <option value="starter">Стартовый пак</option>
                                <option value="daily">Ежедневный (Дейли) пак</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-full py-2 font-bold mt-2">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="gift" class="w-4 h-4 text-purple-400" /> Выдать пак сейчас</span>
                        </button>
                    </form>
                </div>

                @if (!($rconConnected ?? false))
                    <div class="mt-4 p-3 rounded-xl border border-[#d29922]/40 bg-[#3a2f1a]/30 text-xs text-[#d29922] flex items-center gap-2">
                        <x-icon name="alert-triangle" class="w-4 h-4 text-amber-400" />
                        <span>RCON не подключен. Команда будет выполнена только при активном RCON.</span>
                    </div>
                @endif
            </div>

            {{-- 2. Активные кулдауны игроков --}}
            <div class="panel p-5 md:p-6 lg:col-span-7 rounded-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                        <div class="flex items-center gap-2.5">
                            <x-icon name="clock" class="w-5 h-5 text-[#8b949e]" />
                            <h2 class="font-bold text-base text-[#e6edf3]">Активные кулдауны</h2>
                        </div>
                        <span class="badge badge-blue mono text-xs">{{ count($cooldowns) }} записей</span>
                    </div>

                    {{-- Таблица кулдаунов --}}
                    <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117] max-h-60 overflow-y-auto">
                        <table class="table text-xs">
                            <thead>
                                <tr class="bg-[#101623] border-b border-[#30363d]">
                                    <th class="py-2.5 px-3">SteamID</th>
                                    <th class="py-2.5 px-3">Тип пака</th>
                                    <th class="py-2.5 px-3 text-right">Действие</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#21262d]">
                                @forelse ($cooldowns as $sid => $cds)
                                    @foreach ((array)$cds as $type => $time)
                                        <tr class="hover:bg-[#161b22]">
                                            <td class="py-2 px-3 mono text-[#e6edf3] font-semibold">{{ $sid }}</td>
                                            <td class="py-2 px-3">
                                                <span class="badge {{ $type === 'starter' ? 'badge-blue' : 'badge-yellow' }}">
                                                    {{ $type === 'starter' ? 'Стартовый' : 'Дейли' }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-right">
                                                <form action="{{ route('packs.cooldowns.reset') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="steam_id" value="{{ $sid }}">
                                                    <input type="hidden" name="type" value="{{ $type }}">
                                                    <button type="submit" class="btn btn-xs hover:border-[#3fb950] text-[#3fb950] hover:bg-[#2ea043]/10">
                                                        Сбросить
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-[#8b949e] py-6">
                                            Активных кулдаунов нет
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Форма сброса кулдауна вручную --}}
                <div class="mt-4 pt-3 border-t border-[#21262d]">
                    <form action="{{ route('packs.cooldowns.reset') }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input type="text" name="steam_id" class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] flex-1"
                               placeholder="SteamID для сброса кулдауна" required>
                        <select name="type" class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] sm:w-36">
                            <option value="">Все паки</option>
                            <option value="starter">Стартовый</option>
                            <option value="daily">Дейли</option>
                        </select>
                        <button type="submit" class="btn btn-sm shrink-0 font-semibold">Сбросить</button>
                    </form>
                </div>
            </div>

        </div>

        {{-- ===== Библиотека предметов SCUM ===== --}}
        <div class="panel p-5 md:p-6 rounded-2xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <x-icon name="file-text" class="w-5 h-5 text-purple-400" />
                    <div>
                        <h2 class="font-bold text-base text-[#e6edf3]">Библиотека предметов SCUM ({{ count($items) }})</h2>
                        <div class="text-xs text-[#8b949e]">Нажмите на предмет, чтобы мгновенно добавить его в выбранный пак</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center p-1 rounded-xl bg-[#0d1117] border border-[#30363d] text-xs">
                        <span class="text-[#8b949e] px-2">Добавлять в:</span>
                        <button type="button" @click="targetPack = 'starter'"
                                class="px-2.5 py-1 rounded-lg font-semibold transition-all cursor-pointer"
                                :class="targetPack === 'starter' ? 'bg-[#1f6feb] text-white' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                            Стартовый
                        </button>
                        <button type="button" @click="targetPack = 'daily'"
                                class="px-2.5 py-1 rounded-lg font-semibold transition-all cursor-pointer"
                                :class="targetPack === 'daily' ? 'bg-[#d29922] text-black font-bold' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                            Дейли
                        </button>
                    </div>

                    <div class="relative w-full sm:w-64">
                        <span class="absolute left-3 top-2.5 text-[#8b949e]"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                        <input type="text" x-model="searchQuery" placeholder="Поиск предмета..."
                               class="input pl-8 text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 text-xs text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>
                </div>
            </div>

            {{-- Список предметов сеткой --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 max-h-96 overflow-y-auto pr-1">
                <template x-for="item in filteredItems" :key="item.id">
                    <div @click="addItemToPack(item, targetPack)"
                         class="p-2 rounded-xl border border-[#30363d] bg-[#0d1117] hover:border-[#58a6ff] hover:bg-[#161b22] transition-all cursor-pointer group flex items-center justify-between gap-2.5 shadow-sm">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5 group-hover:border-[#58a6ff]/50 transition">
                                <template x-if="item.icon">
                                    <img :src="'/' + item.icon" :alt="item.name" class="w-full h-full object-contain" loading="lazy">
                                </template>
                                <template x-if="!item.icon">
                                    <x-icon name="package" class="w-4 h-4 text-[#58a6ff]" />
                                </template>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] truncate transition-colors" x-text="item.name || item.id"></div>
                                <div class="mono text-[10px] text-[#8b949e] truncate" x-text="item.id"></div>
                            </div>
                        </div>
                        <span class="btn btn-xs opacity-60 group-hover:opacity-100 group-hover:bg-[#1f6feb] group-hover:text-white transition-all shrink-0">
                            + В пак
                        </span>
                    </div>
                </template>
            </div>
        </div>

    </div>
@endsection