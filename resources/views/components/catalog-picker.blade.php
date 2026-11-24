@props([
    'type' => 'item', // item | vehicle | npc | marker
    'name' => 'item',
    'id' => null,
    'placeholder' => null,
    'model' => null,
    'limit' => 50,
])

@php
    $componentId = $id ?? 'picker_' . $type . '_' . uniqid();
    $endpoint = match($type) {
        'vehicle' => route('catalog.vehicles'),
        'npc' => route('catalog.npcs'),
        'marker' => route('catalog.markers'),
        default => route('catalog.items'),
    };
    $defaultPlaceholder = match($type) {
        'vehicle' => 'Поиск авто, мотоцикла, лодки (например: лайка, гольф)...',
        'npc' => 'Поиск существа или NPC (например: волк, медведь, зомби)...',
        'marker' => 'Поиск точки интереса (например: бункер, пещера, заправка)...',
        default => 'Поиск предмета по названию или ID (например: калаш, хлеб, топор)...',
    };
    $resolvedPlaceholder = $placeholder ?? $defaultPlaceholder;
    $typeIcon = match($type) {
        'vehicle' => 'truck',
        'npc' => 'target',
        'marker' => 'map-pin',
        default => 'package',
    };
@endphp

<div x-data="{
        isOpen: false,
        type: '{{ $type }}',
        endpoint: '{{ $endpoint }}',
        fieldName: '{{ $name }}',
        searchQuery: '',
        activeCategory: 'all',
        categories: [],
        results: [],
        selected: null,
        isLoading: false,

        init() {
            if (this.type === 'item') {
                this.loadCategories();
            } else if (this.type === 'vehicle') {
                this.categories = [
                    { key: 'car', name_ru: 'Автомобили', count: 24 },
                    { key: 'bike', name_ru: 'Мотоциклы', count: 14 },
                    { key: 'plane', name_ru: 'Самолеты', count: 3 },
                    { key: 'boat', name_ru: 'Лодки', count: 7 },
                ];
            } else if (this.type === 'marker') {
                this.categories = [
                    { key: 'bunker', name_ru: 'Бункеры', count: 68 },
                    { key: 'cave', name_ru: 'Пещеры', count: 65 },
                    { key: 'atm', name_ru: 'Банкоматы', count: 38 },
                    { key: 'gas_station', name_ru: 'Заправки', count: 23 },
                    { key: 'police', name_ru: 'Полиция', count: 19 },
                    { key: 'hospital', name_ru: 'Больницы', count: 4 },
                ];
            }
            this.search();
        },

        openDropdown() {
            this.isOpen = true;
            if (this.results.length === 0 && !this.isLoading) {
                this.search();
            }
        },

        closeDropdown() {
            this.isOpen = false;
        },

        toggleDropdown() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.$refs.comboboxInput?.focus();
                });
            }
        },

        async loadCategories() {
            try {
                const res = await fetch('{{ route('catalog.categories') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                if (json.ok && json.data) {
                    this.categories = json.data.slice(0, 10);
                }
            } catch(e) {}
        },

        setCategory(cat) {
            this.activeCategory = cat;
            this.search();
        },

        async search() {
            this.isLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery.trim()) params.append('q', this.searchQuery.trim());
                if (this.activeCategory !== 'all') {
                    if (this.type === 'vehicle') params.append('type', this.activeCategory);
                    else if (this.type === 'marker') params.append('type', this.activeCategory);
                    else params.append('category', this.activeCategory);
                }
                params.append('limit', {{ $limit }});

                const res = await fetch(`${this.endpoint}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                if (json.ok && json.data) {
                    this.results = json.data;
                }
            } catch (e) {
                this.results = [];
            } finally {
                this.isLoading = false;
            }
        },

        selectItem(item) {
            this.selected = item;
            this.isOpen = false;
            this.searchQuery = '';
            this.$dispatch('catalog-selected', { type: this.type, item: item, fieldName: this.fieldName });
        },

        clearSelection() {
            this.selected = null;
            this.searchQuery = '';
            this.isOpen = true;
            this.$dispatch('catalog-cleared', { type: this.type, fieldName: this.fieldName });
            this.$nextTick(() => {
                this.$refs.comboboxInput?.focus();
            });
            this.search();
        }
    }"
    class="relative w-full"
    @click.outside="closeDropdown()"
    @keydown.escape.window="closeDropdown()">

    <!-- Скрытый инпут формы -->
    <input type="hidden"
           :name="fieldName"
           :value="selected ? (type === 'marker' ? selected.command : selected.id) : ''">

    <!-- СОСТОЯНИЕ 1: ЭЛЕМЕНТ ВЫБРАН (АККУРАТНАЯ ПЛАШКА) -->
    <template x-if="selected">
        <div class="w-full min-h-[46px] px-3 py-1.5 rounded-xl border border-[#388bfd]/60 bg-[#121824] flex items-center justify-between gap-3 text-left shadow-sm transition-all animate-fadeIn">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-lg bg-[#0d1117] border border-[#388bfd]/50 flex items-center justify-center shrink-0 overflow-hidden p-0.5 shadow-inner">
                    <template x-if="selected.icon">
                        <img :src="'/' + selected.icon" :alt="selected.name" class="w-full h-full object-contain" loading="lazy">
                    </template>
                    <template x-if="!selected.icon">
                        <span class="text-[#58a6ff]">
                            <x-icon name="{{ $typeIcon }}" class="w-4 h-4" />
                        </span>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-bold text-white truncate" x-text="selected.name_ru || selected.name"></span>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-[#3fb950] px-1.5 py-0.5 rounded bg-[#3fb950]/15 border border-[#3fb950]/30 shrink-0">Выбрано</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] text-[#8b949e] truncate">
                        <span class="font-mono text-[#58a6ff] truncate" x-text="selected.id || (selected.x + ', ' + selected.y)"></span>
                        <span class="opacity-70 truncate" x-text="selected.category_ru || selected.type_ru || selected.type ? ('· ' + (selected.category_ru || selected.type_ru || selected.type)) : ''"></span>
                    </div>
                </div>
            </div>

            <button type="button"
                    @click="clearSelection()"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#21262d] hover:bg-[#30363d] text-[#8b949e] hover:text-[#f85149] border border-[#30363d] hover:border-[#f85149]/40 flex items-center gap-1.5 transition shrink-0 cursor-pointer"
                    title="Сменить выбор">
                <x-icon name="x" class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">Сменить</span>
            </button>
        </div>
    </template>

    <!-- СОСТОЯНИЕ 2: ПОИСКОВЫЙ КОМБОБОКС (ОДНО ЕДИНОЕ ПОЛЕ БЕЗ ДУБЛИРОВАНИЯ) -->
    <template x-if="!selected">
        <div class="relative flex items-center">
            <!-- Левая иконка поиска / спиннер -->
            <span class="absolute left-3.5 text-[#8b949e] pointer-events-none flex items-center justify-center">
                <template x-if="isLoading">
                    <div class="w-4 h-4 border-2 border-[#58a6ff] border-t-transparent rounded-full animate-spin"></div>
                </template>
                <template x-if="!isLoading">
                    <x-icon name="search" class="w-4 h-4" />
                </template>
            </span>

            <!-- ЕДИНСТВЕННЫЙ ИНПУТ ПОИСКА -->
            <input type="text"
                   x-ref="comboboxInput"
                   x-model="searchQuery"
                   @focus="openDropdown()"
                   @click="openDropdown()"
                   @input.debounce.150ms="search(); openDropdown()"
                   placeholder="{{ $resolvedPlaceholder }}"
                   class="w-full h-11 pl-10 pr-16 rounded-xl bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/20 text-xs sm:text-sm text-[#e6edf3] placeholder-[#8b949e] transition outline-none shadow-sm">

            <!-- Правые кнопки управления -->
            <div class="absolute right-2.5 flex items-center gap-1">
                <button type="button"
                        x-show="searchQuery"
                        @click.stop="searchQuery = ''; search()"
                        class="text-[#8b949e] hover:text-[#e6edf3] p-1 transition cursor-pointer"
                        title="Очистить строку">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                </button>
                <button type="button"
                        @click.stop="toggleDropdown()"
                        class="text-[#8b949e] hover:text-[#e6edf3] p-1 transition cursor-pointer">
                    <div class="w-4 h-4 flex items-center justify-center transition-transform duration-200"
                         :class="isOpen ? 'rotate-180 text-[#58a6ff]' : ''">
                        <x-icon name="chevron-down" class="w-4 h-4" />
                    </div>
                </button>
            </div>
        </div>
    </template>

    <!-- ВЫПАДАЮЩЕЕ МЕНЮ С РЕЗУЛЬТАТАМИ -->
    <div x-show="isOpen && !selected"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1.5 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-1.5 scale-98"
         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-[#161b22] border border-[#30363d] rounded-2xl shadow-2xl p-2.5 space-y-2 backdrop-blur-xl">

        <!-- Чипсы категорий (БЕЗ БЕЛОГО СКРОЛЛБАРА) -->
        <template x-if="categories.length > 0">
            <div class="no-scrollbar flex items-center gap-1.5 py-0.5 px-0.5 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                <button type="button"
                        @click="setCategory('all')"
                        :class="activeCategory === 'all' ? 'bg-[#388bfd] text-white shadow-sm' : 'bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3]'"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap transition shrink-0 cursor-pointer">
                    Все
                </button>
                <template x-for="cat in categories" :key="cat.key">
                    <button type="button"
                            @click="setCategory(cat.key)"
                            :class="activeCategory === cat.key ? 'bg-[#388bfd] text-white shadow-sm' : 'bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3]'"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span x-text="cat.name_ru"></span>
                        <span class="text-[10px] opacity-70" x-text="'(' + cat.count + ')'"></span>
                    </button>
                </template>
            </div>
        </template>

        <!-- Список элементов -->
        <div class="rounded-xl border border-[#30363d]/80 bg-[#0d1117] divide-y divide-[#21262d]/50 max-h-52 overflow-y-auto p-1 space-y-0.5 custom-scrollbar">
            <template x-if="isLoading">
                <div class="p-6 text-center text-xs text-[#8b949e] flex items-center justify-center gap-2">
                    <div class="w-4 h-4 border-2 border-[#58a6ff] border-t-transparent rounded-full animate-spin"></div>
                    <span>Поиск в каталоге...</span>
                </div>
            </template>

            <template x-if="!isLoading && results.length === 0">
                <div class="p-6 text-center text-xs text-[#8b949e] space-y-1">
                    <div class="text-[#e6edf3] font-medium">Ничего не найдено</div>
                    <div class="text-[11px]">Попробуйте другой запрос или сбросьте категорию</div>
                </div>
            </template>

            <template x-for="item in results" :key="item.id || (item.name + item.x)">
                <div @click="selectItem(item)"
                     class="w-full flex items-center justify-between gap-3 p-2 rounded-lg text-left transition cursor-pointer group hover:bg-[#1f6feb]/15 hover:border-[#388bfd]/30 border border-transparent">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <!-- Иконка -->
                        <div class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5 group-hover:border-[#58a6ff]/50 transition">
                            <template x-if="item.icon">
                                <img :src="'/' + item.icon" :alt="item.name" class="w-full h-full object-contain" loading="lazy">
                            </template>
                            <template x-if="!item.icon">
                                <span class="text-[#58a6ff]">
                                    <x-icon name="{{ $typeIcon }}" class="w-4 h-4" />
                                </span>
                            </template>
                        </div>

                        <!-- Описание -->
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors truncate" x-text="item.name_ru || item.name"></div>
                            <div class="flex items-center gap-1.5 text-[10px] text-[#8b949e] truncate">
                                <span class="font-mono text-[#58a6ff] truncate" x-text="item.id || (item.x + ', ' + item.y)"></span>
                                <span x-show="item.category_ru || item.type_ru" class="opacity-80 truncate" x-text="'· ' + (item.category_ru || item.type_ru || '')"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Стрелка выбора -->
                    <div class="flex items-center gap-1 text-[11px] font-semibold text-[#58a6ff] opacity-0 group-hover:opacity-100 transition shrink-0 pr-1">
                        <span>Выбрать</span>
                        <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                    </div>
                </div>
            </template>
        </div>

        <!-- Подвал меню -->
        <div class="flex items-center justify-between text-[10px] text-[#8b949e] px-1 pt-0.5">
            <span>Показано: <strong class="text-[#e6edf3]" x-text="results.length"></strong> позиций</span>
            <span>Esc — закрыть</span>
        </div>
    </div>
</div>
