@extends('layouts.app')

@section('title', 'WARGM Магазин & Автовыдача')

@section('content')
<div x-data="cardForm(@js($cards))" class="space-y-6">

    {{-- ===== 1. HERO BANNER ===== --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d]/80 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-5 sm:p-7 shadow-2xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#a371f7]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-20 w-72 h-72 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
        
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-14 h-14 shrink-0 rounded-2xl bg-gradient-to-br from-[#a371f7] via-[#8957e5] to-[#1f6feb] p-[2px] shadow-lg shadow-[#a371f7]/25">
                    <div class="w-full h-full rounded-[14px] bg-[#0d1117] flex items-center justify-center text-[#a371f7]">
                        <x-icon name="shopping-cart" class="w-7 h-7" />
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap mb-1">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-[#e6edf3] tracking-tight">
                            WARGM Магазин & Автовыдача
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border {{ $stats['apiConnected'] ? 'bg-[#2ea043]/15 text-[#3fb950] border-[#2ea043]/40' : 'bg-[#f85149]/15 text-[#f85149] border-[#f85149]/40' }}">
                            <span class="w-2 h-2 rounded-full {{ $stats['apiConnected'] ? 'bg-[#3fb950] animate-pulse' : 'bg-[#f85149]' }}"></span>
                            {{ $stats['apiConnected'] ? 'API подключено' : 'API отключено' }}
                        </span>
                    </div>
                    <div class="text-xs sm:text-sm text-[#8b949e] flex items-center gap-2 flex-wrap">
                        <span>Выдача покупок игрокам в игре по команде в чате <code class="px-2 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[#a371f7] font-mono text-xs font-bold">!варгм</code></span>
                        <span>·</span>
                        <span>ShopID: <span class="mono text-[#e6edf3] font-semibold">{{ $settings['shopId'] ?: 'Не указан' }}</span></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <button type="button" @click="openCreateModal()" class="btn btn-primary px-4 py-2.5 shadow-lg shadow-[#1f6feb]/20 flex items-center gap-2 text-xs sm:text-sm font-semibold cursor-pointer">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span>Создать карточку</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ===== 2. СТАТИСТИКА ===== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#a371f7]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="package" class="w-6 h-6 text-[#a371f7]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#e6edf3] tabular-nums">{{ $stats['totalCards'] }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Всего карточек</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#2ea043]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="check" class="w-6 h-6 text-[#3fb950]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#3fb950] tabular-nums">{{ $stats['enabledCards'] }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Активно в магазине</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#d29922]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="gift" class="w-6 h-6 text-[#d29922]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#d29922] tabular-nums">{{ $stats['itemTypes'] }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Типов наград</div>
            </div>
        </div>

        <div class="panel p-4 sm:p-5 flex items-center gap-3.5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl hover:border-[#1f6feb]/40 transition-all">
            <div class="w-12 h-12 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center shrink-0 shadow-inner">
                <x-icon name="file-text" class="w-6 h-6 text-[#58a6ff]" />
            </div>
            <div class="min-w-0">
                <div class="text-2xl sm:text-3xl font-extrabold text-[#58a6ff] tabular-nums">{{ $stats['deliveryCount'] }}</div>
                <div class="text-xs text-[#8b949e] font-medium truncate">Выдач игрокам</div>
            </div>
        </div>
    </div>

    {{-- ===== 3. ВКЛАДКИ НАВИГАЦИИ ===== --}}
    <div class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-[#121824]/90 border border-[#30363d]/80 shadow-md overflow-x-auto no-scrollbar">
        <button type="button" @click="setTab('cards')"
                :class="activeTab === 'cards' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold shadow-sm' : 'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]'"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm border transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer">
            <x-icon name="shopping-cart" class="w-4 h-4" />
            <span>Карточки товаров</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-[#21262d] border border-[#30363d] text-[#e6edf3]">{{ $stats['totalCards'] }}</span>
        </button>

        <button type="button" @click="setTab('deliveries')"
                :class="activeTab === 'deliveries' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold shadow-sm' : 'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]'"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm border transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer">
            <x-icon name="clock" class="w-4 h-4" />
            <span>История доставок</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-[#21262d] border border-[#30363d] text-[#e6edf3]">{{ $stats['deliveryCount'] }}</span>
        </button>

        <button type="button" @click="setTab('check')"
                :class="activeTab === 'check' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold shadow-sm' : 'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]'"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm border transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer">
            <x-icon name="search" class="w-4 h-4" />
            <span>Ручная проверка</span>
        </button>

        <button type="button" @click="setTab('settings')"
                :class="activeTab === 'settings' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold shadow-sm' : 'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]'"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm border transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer">
            <x-icon name="settings" class="w-4 h-4" />
            <span>Настройки API</span>
        </button>

        <button type="button" @click="setTab('import')"
                :class="activeTab === 'import' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-bold shadow-sm' : 'text-[#8b949e] border-transparent hover:text-[#e6edf3] hover:bg-[#161b22]'"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm border transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer">
            <x-icon name="download" class="w-4 h-4" />
            <span>Импорт / Экспорт</span>
        </button>
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 1: КАРТОЧКИ ТОВАРОВ                                                   --}}
    {{-- ========================================================================= --}}
    <div x-show="activeTab === 'cards'" x-transition.opacity class="space-y-4">
        
        {{-- Toolbar: Поиск и управление --}}
        <div class="panel p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl">
            <div class="relative flex-1 max-w-md">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8b949e] pointer-events-none">
                    <x-icon name="search" class="w-4 h-4" />
                </span>
                <input type="text" x-model="searchQuery" @input="filterCards()"
                       class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/20 rounded-xl pl-10 pr-8 py-2 text-xs sm:text-sm text-[#e6edf3] placeholder-[#8b949e] transition-all outline-none"
                       placeholder="Поиск по названию карточки, ShopItemID или предмету...">
                <button type="button" x-show="searchQuery" @click="clearSearch()"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#8b949e] hover:text-[#e6edf3] p-1 cursor-pointer">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                </button>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="openCreateModal()" class="btn btn-primary px-3.5 py-2 text-xs font-semibold flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/20 cursor-pointer">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span>Добавить карточку</span>
                </button>
            </div>
        </div>

        {{-- Сетка карточек товаров --}}
        @if (empty($cards))
            <div class="panel p-12 text-center rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 space-y-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center text-[#a371f7] shadow-inner">
                    <x-icon name="shopping-cart" class="w-8 h-8" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-[#e6edf3]">Карточки товаров ещё не созданы</h3>
                    <p class="text-xs text-[#8b949e] max-w-md mx-auto">
                        Создайте свою первую карточку для выдачи оружия, транспорта, денег или VIP-статуса по команде !варгм.
                    </p>
                </div>
                <button type="button" @click="openCreateModal()" class="btn btn-primary shadow-lg shadow-[#1f6feb]/20 px-5 py-2 text-xs font-semibold cursor-pointer">
                    <x-icon name="plus" class="w-4 h-4" />
                    <span>Создать первую карточку</span>
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5">
                @foreach ($cards as $card)
                    @php
                        $searchPayload = strtolower($card['name'].' '.$card['shopItemId'].' '.collect($card['items'])->map(function($i) {
                            return ($i['type'] ?? '').' '.json_encode($i['data'] ?? []);
                        })->implode(' '));
                    @endphp
                    <div data-card-item
                         data-search="{{ e($searchPayload) }}"
                         class="p-4 sm:p-5 flex flex-col justify-between rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 hover:border-[#388bfd]/50 hover:shadow-xl hover:shadow-[#1f6feb]/5 transition-all group shadow-md">
                        
                        <div>
                            {{-- Верхняя строка: ShopItemID и статус --}}
                            <div class="flex items-start justify-between gap-2 mb-3 pb-2.5 border-b border-[#21262d]">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-[#0d1117] border border-[#30363d] text-[#58a6ff]">
                                        <span class="text-[#8b949e]">ID:</span>
                                        <span>{{ $card['shopItemId'] }}</span>
                                    </span>
                                    <button type="button" @click="copyToClipboard('{{ $card['shopItemId'] }}')"
                                            class="text-[#8b949e] hover:text-[#58a6ff] transition-colors p-1 cursor-pointer"
                                            title="Скопировать ShopItemID">
                                        <x-icon name="copy" class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $card['enabled'] ? 'bg-[#2ea043]/15 text-[#3fb950] border-[#2ea043]/30' : 'bg-[#21262d] text-[#8b949e] border-[#30363d]' }} shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $card['enabled'] ? 'bg-[#3fb950]' : 'bg-[#8b949e]' }}"></span>
                                    {{ $card['enabled'] ? 'Активна' : 'Отключена' }}
                                </span>
                            </div>

                            {{-- Название карточки --}}
                            <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors line-clamp-2 mb-3">
                                {{ $card['name'] }}
                            </h3>

                            {{-- Список предметов с визуальными иконками --}}
                            <div class="space-y-1.5 mb-4">
                                <div class="text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider flex items-center justify-between pb-1">
                                    <span>Состав награды</span>
                                    <span class="text-xs text-[#58a6ff] font-mono font-bold">{{ count($card['items']) }} поз.</span>
                                </div>

                                <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1 custom-scrollbar">
                                    @foreach ($card['items'] as $it)
                                        @php
                                            $t = $it['type'] ?? 'item';
                                            $d = $it['data'] ?? [];
                                            $itemInfo = null;
                                            $vehicleInfo = null;
                                            $iconUrl = null;
                                            $displayName = 'Предмет';

                                            if ($t === 'item' && !empty($d['itemName'])) {
                                                $key = mb_strtolower(trim($d['itemName']));
                                                $itemInfo = $catalogItems[$key] ?? null;
                                                $displayName = !empty($itemInfo['name_ru']) ? $itemInfo['name_ru'] : $d['itemName'];
                                                $iconUrl = !empty($itemInfo['icon']) ? '/' . $itemInfo['icon'] : null;
                                            } elseif ($t === 'vehicle' && !empty($d['vehicleName'])) {
                                                $key = mb_strtolower(trim($d['vehicleName']));
                                                $vehicleInfo = $catalogVehicles[$key] ?? null;
                                                $displayName = !empty($vehicleInfo['name_ru']) ? $vehicleInfo['name_ru'] : str_replace(['_ES', '_C', 'BP_', 'BPC_'], '', $d['vehicleName']);
                                                $iconUrl = !empty($vehicleInfo['icon']) ? '/' . $vehicleInfo['icon'] : null;
                                            }
                                        @endphp

                                        <div class="flex items-center justify-between gap-2 p-1.5 rounded-xl bg-[#0d1117] border border-[#21262d] text-xs">
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                {{-- Иконка награды --}}
                                                <div class="w-7 h-7 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5">
                                                    @if ($iconUrl)
                                                        <img src="{{ $iconUrl }}" alt="{{ $displayName }}" class="w-full h-full object-contain" loading="lazy">
                                                    @elseif ($t === 'item')
                                                        <x-icon name="package" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                                    @elseif ($t === 'vehicle')
                                                        <x-icon name="truck" class="w-3.5 h-3.5 text-[#d29922]" />
                                                    @elseif ($t === 'money')
                                                        <span class="text-[#3fb950] font-bold text-xs">$</span>
                                                    @elseif ($t === 'gold')
                                                        <x-icon name="coins" class="w-3.5 h-3.5 text-[#f0883e]" />
                                                    @elseif ($t === 'fame')
                                                        <x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341]" />
                                                    @elseif ($t === 'skill')
                                                        <x-icon name="zap" class="w-3.5 h-3.5 text-[#a371f7]" />
                                                    @elseif ($t === 'vip')
                                                        <x-icon name="crown" class="w-3.5 h-3.5 text-[#d2a8ff]" />
                                                    @elseif ($t === 'insurance')
                                                        <x-icon name="shield" class="w-3.5 h-3.5 text-[#3fb950]" />
                                                    @elseif ($t === 'cargo_drop')
                                                        <x-icon name="parachute" class="w-3.5 h-3.5 text-[#bc8cff]" />
                                                    @else
                                                        <x-icon name="gift" class="w-3.5 h-3.5 text-[#8b949e]" />
                                                    @endif
                                                </div>

                                                {{-- Название и параметры --}}
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-semibold text-[#e6edf3] truncate">
                                                        @if ($t === 'item')
                                                            {{ $displayName }}
                                                        @elseif ($t === 'vehicle')
                                                            {{ $displayName }}
                                                        @elseif ($t === 'money')
                                                            ${{ number_format($d['amount'] ?? 0, 0, '.', ' ') }}
                                                        @elseif ($t === 'gold')
                                                            {{ $d['amount'] ?? 0 }} Золота
                                                        @elseif ($t === 'fame')
                                                            +{{ $d['amount'] ?? 0 }} Славы
                                                        @elseif ($t === 'skill')
                                                            {{ $d['skillName'] ?? 'Навык' }} (ур. {{ $d['level'] ?? 1 }})
                                                        @elseif ($t === 'vip')
                                                            VIP ({{ $d['days'] ?? 30 }} дн.)
                                                        @elseif ($t === 'attributes')
                                                            Атрибуты ({{ $d['strength'] ?? 5 }}/{{ $d['stamina'] ?? 5 }}/{{ $d['dexterity'] ?? 5 }}/{{ $d['intellect'] ?? 5 }})
                                                        @elseif ($t === 'cargo_drop')
                                                            Серверный аирдроп
                                                        @elseif ($t === 'insurance')
                                                            Страховка ТС ({{ $d['claims'] ?? 1 }} возвр., {{ $d['days'] ?? 30 }} дн.)
                                                        @else
                                                            {{ $t }}
                                                        @endif
                                                    </div>
                                                    @if ($t === 'item' && !empty($d['itemName']))
                                                        <div class="text-[10px] font-mono text-[#58a6ff] truncate">{{ $d['itemName'] }}</div>
                                                    @elseif ($t === 'vehicle' && !empty($d['vehicleName']))
                                                        <div class="text-[10px] font-mono text-[#d29922] truncate">{{ $d['vehicleName'] }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($t === 'item' && !empty($d['amount']) && (int)$d['amount'] >= 1)
                                                <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-lg bg-[#161b22] border border-[#30363d] text-[#58a6ff] shrink-0">
                                                    x{{ $d['amount'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Кнопки действий карточки --}}
                        <div class="flex items-center gap-2 pt-3 border-t border-[#21262d] mt-2">
                            <button type="button" @click="editCard({{ $card['id'] }})"
                                    class="btn btn-sm flex-1 bg-[#161b22] hover:bg-[#21262d] text-[#e6edf3] hover:text-[#58a6ff] border-[#30363d] hover:border-[#388bfd]/50 justify-center gap-1.5 shadow-sm cursor-pointer">
                                <x-icon name="edit" class="w-3.5 h-3.5" />
                                <span>Изменить</span>
                            </button>

                            <form action="{{ route('wargm.cards.duplicate', $card['id']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-sm bg-[#161b22] hover:bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d] px-2.5 cursor-pointer" title="Дублировать карточку">
                                    <x-icon name="copy" class="w-3.5 h-3.5" />
                                </button>
                            </form>

                            <form action="{{ route('wargm.cards.delete', $card['id']) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Вы уверены, что хотите удалить карточку «{{ addslashes($card['name']) }}»?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger px-2.5 cursor-pointer" title="Удалить карточку">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 2: ИСТОРИЯ ДОСТАВОК                                                   --}}
    {{-- ========================================================================= --}}
    <div x-show="activeTab === 'deliveries'" x-transition.opacity class="space-y-4">
        <div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-4 border-b border-[#21262d]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#a371f7]/15 border border-[#a371f7]/30 flex items-center justify-center shrink-0 shadow-inner">
                        <x-icon name="file-text" class="w-5 h-5 text-[#a371f7]" />
                    </div>
                    <div>
                        <h2 class="font-bold text-base text-[#e6edf3]">История выдачи покупок игрокам</h2>
                        <div class="text-xs text-[#8b949e]">Журнал всех успешных доставок по команде !варгм с точными координатами</div>
                    </div>
                </div>

                <form action="{{ route('wargm.index') }}" method="GET" class="flex gap-2 w-full md:w-auto">
                    <div class="relative flex-1 md:w-72">
                        <span class="absolute left-3 top-2.5 text-[#8b949e] pointer-events-none">
                            <x-icon name="search" class="w-3.5 h-3.5" />
                        </span>
                        <input type="text" name="delivery_search" value="{{ request('delivery_search') }}"
                               placeholder="Поиск по нику, Steam ID или товару..."
                               class="w-full bg-[#0d1117] border border-[#30363d] focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/20 rounded-xl pl-8 pr-7 py-2 text-xs text-[#e6edf3] placeholder-[#8b949e] outline-none">
                    </div>
                    <button type="submit" class="btn btn-sm btn-accent whitespace-nowrap px-4 cursor-pointer">Найти</button>
                    @if(request('delivery_search'))
                        <a href="{{ route('wargm.index') }}#deliveries" class="btn btn-sm flex items-center justify-center cursor-pointer"><x-icon name="x" class="w-3.5 h-3.5" /></a>
                    @endif
                </form>
            </div>

            {{-- Сетка истории выдачи --}}
            <div class="overflow-hidden rounded-xl border border-[#21262d]">
                {{-- Заголовок на Desktop --}}
                <div class="hidden md:grid md:grid-cols-12 gap-3 px-4 py-3 bg-[#0e131f] border-b border-[#21262d] text-xs font-semibold text-[#8b949e] uppercase tracking-wider items-center">
                    <div class="col-span-2">Время</div>
                    <div class="col-span-3">Игрок / Steam ID</div>
                    <div class="col-span-3">Карточка товара</div>
                    <div class="col-span-3">Выданные предметы</div>
                    <div class="col-span-1 text-right">Локация</div>
                </div>

                {{-- Строки таблицы --}}
                <div class="divide-y divide-[#21262d] bg-[#0d1117]/80">
                    @forelse ($deliveries as $deliv)
                        <div class="p-3 sm:px-4 sm:py-3 hover:bg-[#161d2d] transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center">
                            
                            {{-- 1. Время --}}
                            <div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">
                                <span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">
                                    {{ $deliv['deliveredAt'] }}
                                </span>
                                <div class="md:hidden">
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold border bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30">
                                        {{ $deliv['cardName'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- 2. Игрок --}}
                            <div class="md:col-span-3 min-w-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @php
                                        $pName = trim($deliv['playerName'] ?? '');
                                        $hasValidName = !empty($pName) && $pName !== '—' && $pName !== 'Игрок';
                                        $initials = $hasValidName ? strtoupper(mb_substr($pName, 0, 2)) : mb_substr($deliv['steamId'], -2);
                                    @endphp
                                    <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#a371f7]/20 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center font-bold text-xs shrink-0 select-none">
                                        {{ $initials }}
                                    </span>
                                    <div class="min-w-0">
                                        @if ($hasValidName)
                                            <div class="font-semibold text-xs text-[#e6edf3] truncate">{{ $pName }}</div>
                                        @endif
                                        <div class="flex items-center gap-1.5 text-[11px] font-mono text-[#8b949e]">
                                            <a href="{{ route('scumdb.player', $deliv['steamId']) }}"
                                               class="hover:text-[#58a6ff] hover:underline truncate"
                                               title="Открыть досье игрока">
                                                {{ $deliv['steamId'] }}
                                            </a>
                                            <button type="button" @click="copyToClipboard('{{ $deliv['steamId'] }}')" class="text-[#8b949e] hover:text-[#e6edf3] p-0.5 cursor-pointer" title="Скопировать Steam ID"><x-icon name="copy" class="w-3 h-3" /></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- 3. Карточка (Десктоп) --}}
                            <div class="hidden md:block md:col-span-3 min-w-0">
                                <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] truncate max-w-full">
                                    {{ $deliv['cardName'] }}
                                </span>
                            </div>

                            {{-- 4. Детали выдачи --}}
                            <div class="md:col-span-3 text-xs text-[#e6edf3] break-words">
                                <div class="leading-relaxed">{{ $deliv['itemsSummary'] ?: 'Успешно выдано' }}</div>
                            </div>

                            {{-- 5. Локация / Кнопка карты --}}
                            <div class="md:col-span-1 md:text-right flex items-center justify-between md:justify-end gap-2 pt-1 md:pt-0">
                                @if ($deliv['location'] && preg_match('/X=([-\d.]+)\s+Y=([-\d.]+)/i', $deliv['location'], $lm))
                                    <a href="{{ route('map.index') }}?x={{ $lm[1] }}&y={{ $lm[2] }}"
                                       target="_blank"
                                       class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] border-[#30363d] text-[#58a6ff] hover:text-[#79c0ff] inline-flex items-center gap-1 shadow-sm cursor-pointer"
                                       title="Показать место выдачи на карте">
                                        <x-icon name="map" class="w-3.5 h-3.5" />
                                        <span class="font-mono text-[10px]">[{{ round($lm[1]) }}, {{ round($lm[2]) }}]</span>
                                    </a>
                                @else
                                    <span class="text-xs text-[#8b949e] opacity-40">—</span>
                                @endif
                            </div>

                        </div>
                    @empty
                        <div class="text-center py-12 text-sm text-[#8b949e]">
                            Доставок пока нет или ничего не найдено
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 3: РУЧНАЯ ПРОВЕРКА И ВЫДАЧА                                           --}}
    {{-- ========================================================================= --}}
    <div x-show="activeTab === 'check'" x-transition.opacity class="space-y-6">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            <div class="xl:col-span-7 panel p-5 sm:p-7 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-5">
                <div class="flex items-center gap-3.5 pb-4 border-b border-[#21262d]">
                    <div class="w-10 h-10 rounded-xl bg-[#d29922]/15 border border-[#d29922]/30 flex items-center justify-center shrink-0 shadow-inner">
                        <x-icon name="search" class="w-5 h-5 text-[#d29922]" />
                    </div>
                    <div>
                        <h2 class="font-bold text-base text-[#e6edf3]">Ручная проверка покупок игрока</h2>
                        <div class="text-xs text-[#8b949e]">Проверить наличие ожидающих выдачи покупок в WARGM API и выдать через RCON</div>
                    </div>
                </div>

                <form action="{{ route('wargm.check') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5 uppercase tracking-wider">Steam ID игрока (17 цифр)</label>
                        <div class="flex gap-2">
                            <input type="text" name="steam_id" class="input font-mono text-sm flex-1"
                                   placeholder="76561198..." pattern="\d{17}" required>
                            <button type="submit" class="btn btn-primary whitespace-nowrap flex items-center gap-1.5 shadow-md px-5 cursor-pointer">
                                <x-icon name="search" class="w-4 h-4" />
                                <span>Проверить и выдать</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="xl:col-span-5 panel p-5 sm:p-7 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-4">
                <div class="font-semibold text-sm text-[#e6edf3] flex items-center gap-2 pb-3 border-b border-[#21262d]">
                    <x-icon name="info" class="w-4 h-4 text-[#58a6ff]" />
                    <span>Принцип автоматической выдачи</span>
                </div>
                <ul class="space-y-2.5 text-xs text-[#8b949e] leading-relaxed">
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#58a6ff] mt-1.5 shrink-0"></span>
                        <span>Система опрашивает WARGM API со статусом <code class="mono text-[#a371f7]">pending</code> для указанного SteamID.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#58a6ff] mt-1.5 shrink-0"></span>
                        <span>Сверяет найденные <code class="mono text-[#58a6ff]">offer_id</code> с вашими карточками товаров.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#58a6ff] mt-1.5 shrink-0"></span>
                        <span>Исполняет RCON команды спавна рядом с персонажем игрока и подтверждает операцию в магазине.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] mt-1.5 shrink-0"></span>
                        <span>Игроки также могут сами забирать покупки прямо в игре командой <code class="mono text-[#3fb950] font-bold">!варгм</code>.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 4: НАСТРОЙКИ API                                                      --}}
    {{-- ========================================================================= --}}
    <div x-show="activeTab === 'settings'" x-transition.opacity class="space-y-4">
        <div class="panel p-5 sm:p-7 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[#21262d]">
                <div class="w-10 h-10 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center shrink-0 shadow-inner">
                    <x-icon name="settings" class="w-5 h-5 text-[#58a6ff]" />
                </div>
                <div>
                    <h2 class="font-bold text-base text-[#e6edf3]">Параметры интеграции WARGM API</h2>
                    <div class="text-xs text-[#8b949e]">Ключи доступа и интервалы опроса магазина</div>
                </div>
            </div>

            <form action="{{ route('wargm.settings') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">API URL</label>
                        <input type="text" name="apiUrl" class="input font-mono text-xs" value="{{ $settings['apiUrl'] }}" required>
                        <span class="text-[10px] text-[#8b949e]">По умолчанию: https://api.wargm.ru/v1.1/</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Shop ID (ID магазина в WARGM)</label>
                        <input type="text" name="shopId" class="input font-mono text-xs" value="{{ $settings['shopId'] }}" required placeholder="например: 1234">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">API Key (Секретный ключ)</label>
                        <input type="text" name="apiKey" class="input font-mono text-xs"
                               value="{{ $settings['apiKey'] ? '••••••••' . mb_substr($settings['apiKey'], -4) : '' }}"
                               placeholder="Оставьте пустым, если не меняется">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Защита от дублей выдачи (минут)</label>
                        <input type="number" name="duplicateCheckMinutes" class="input" value="{{ $settings['duplicateCheckMinutes'] ?? 5 }}" min="1" max="120">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Кулдаун команды !варгм в чате (секунд)</label>
                        <input type="number" name="commandCooldownSeconds" class="input" value="{{ $settings['commandCooldownSeconds'] ?? 30 }}" min="5" max="300">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Таймаут запросов API (секунд)</label>
                        <input type="number" name="timeout" class="input" value="{{ $settings['timeout'] ?? 30 }}" min="5" max="120">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Максимум предметов в карточке</label>
                        <input type="number" name="maxItemsPerCard" class="input" value="{{ $settings['maxItemsPerCard'] ?? 50 }}" min="1" max="500">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-[#30363d]">
                    <button type="submit" class="btn btn-primary shadow-md cursor-pointer">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить настройки</span>
                    </button>
                    <button type="submit" formaction="{{ route('wargm.test') }}" formmethod="POST" class="btn btn-accent cursor-pointer">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="refresh-cw" class="w-4 h-4" /> Проверить подключение</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 5: ИМПОРТ / ЭКСПОРТ                                                    --}}
    {{-- ========================================================================= --}}
    <div x-show="activeTab === 'import'" x-transition.opacity class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="panel p-5 sm:p-6 flex flex-col justify-between rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl">
                <div>
                    <div class="flex items-center gap-3 mb-3 pb-3 border-b border-[#21262d]">
                        <div class="w-10 h-10 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center shrink-0">
                            <x-icon name="upload" class="w-5 h-5 text-[#58a6ff]" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-[#e6edf3]">Экспорт карточек</h3>
                            <div class="text-xs text-[#8b949e]">Резервное копирование базы товаров</div>
                        </div>
                    </div>
                    <p class="text-xs text-[#8b949e] leading-relaxed mb-4">
                        Скачайте полный файл конфигурации всех карточек и наград в формате JSON для безопасного резервного хранения или переноса на другой сервер.
                    </p>
                </div>
                <a href="{{ route('wargm.export') }}" class="btn btn-accent w-full justify-center shadow-md cursor-pointer">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="download" class="w-4 h-4" /> Скачать JSON</span> ({{ count($cards) }} карточек)
                </a>
            </div>

            <div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl">
                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-[#21262d]">
                    <div class="w-10 h-10 rounded-xl bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center shrink-0">
                        <x-icon name="download" class="w-5 h-5 text-[#3fb950]" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-[#e6edf3]">Импорт карточек</h3>
                        <div class="text-xs text-[#8b949e]">Восстановление или добавление товаров</div>
                    </div>
                </div>
                <form action="{{ route('wargm.import') }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="import_json" rows="4" class="input font-mono text-xs w-full"
                              placeholder="Вставьте JSON массив карточек..."></textarea>
                    <button type="submit" class="btn btn-primary w-full justify-center shadow-md cursor-pointer">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="upload" class="w-4 h-4" /> Импортировать карточки</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- VISUAL CARD BUILDER MODAL (ИНТЕРАКТИВНЫЙ КОНСТРУКТОР КАРТОЧЕК)            --}}
    {{-- ========================================================================= --}}
    <div x-show="showModal"
         x-cloak
         role="dialog"
         aria-modal="true"
         x-transition.opacity
         @keydown.escape.window="closeModal()"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/80 backdrop-blur-sm overflow-y-auto"
         style="display: none;">
        
        <div @click.away="closeModal()"
             class="panel w-full max-w-4xl bg-[#121824] border border-[#30363d] shadow-2xl rounded-2xl overflow-hidden my-auto max-h-[92vh] flex flex-col">
            
            {{-- Modal Header --}}
            <div class="p-4 sm:px-6 sm:py-4 bg-[#0e131f] border-b border-[#21262d] flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#a371f7]/30 to-[#1f6feb]/20 border border-[#a371f7]/40 flex items-center justify-center text-[#a371f7] shadow-inner">
                        <span x-show="modalMode === 'create'"><x-icon name="plus" class="w-5 h-5" /></span>
                        <span x-show="modalMode !== 'create'"><x-icon name="edit" class="w-5 h-5" /></span>
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-[#e6edf3]" x-text="modalMode === 'create' ? 'Создание новой карточки WARGM' : 'Редактирование карточки WARGM'"></h2>
                        <div class="text-[11px] text-[#8b949e]">Настройте выдачу предметов, техники, валюты и навыков</div>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] flex items-center justify-center cursor-pointer">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            {{-- Modal Body (Scrollable) --}}
            <div class="p-4 sm:p-6 overflow-y-auto space-y-5 flex-1 custom-scrollbar">

                {{-- Быстрый выбор готовых пресетов --}}
                <div x-show="modalMode === 'create'" class="p-3.5 rounded-xl bg-[#0d1117] border border-[#21262d] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <x-icon name="sparkles" class="w-4 h-4 text-[#58a6ff]" />
                        <div>
                            <div class="text-xs font-bold text-[#e6edf3]">Быстрый старт из шаблона</div>
                            <div class="text-[11px] text-[#8b949e]">Заполните набор готовыми популярными предметами в 1 клик</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="selectedPreset" @change="applyPreset($event.target.value)"
                                class="bg-[#161b22] border border-[#30363d] rounded-xl px-3 py-1.5 text-xs text-[#e6edf3] outline-none cursor-pointer">
                            <option value="">-- Выбрать шаблон набора --</option>
                            <template x-for="p in presets" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <form id="wargm-card-form"
                      :action="form.id ? '/wargm/cards/' + form.id : '{{ route('wargm.cards.save') }}'"
                      method="POST"
                      class="space-y-6">
                    @csrf
                    <input type="hidden" name="card_id" :value="form.id">
                    <input type="hidden" name="items_json" :value="form.itemsJson">

                    {{-- 1. Основные параметры карточки --}}
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6">
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1.5 uppercase tracking-wider">
                                Название карточки <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="text" name="name" x-model="form.name" required
                                   class="input text-xs sm:text-sm w-full"
                                   placeholder="Например: Золотой пистолет + Патроны">
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1.5 uppercase tracking-wider">
                                ShopItemID (offer_id в WARGM) <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="text" name="shop_item_id" x-model="form.shopId" required
                                   class="input font-mono text-xs sm:text-sm w-full"
                                   placeholder="например: 218229">
                        </div>

                        <div class="md:col-span-2 flex items-end pb-1.5">
                            <label class="flex items-center gap-2 text-xs font-bold text-[#e6edf3] cursor-pointer select-none">
                                <input type="checkbox" name="enabled" value="1" x-model="form.enabled" class="rounded border-[#30363d] text-[#2ea043] focus:ring-0">
                                <span>Активна</span>
                            </label>
                        </div>
                    </div>

                    {{-- 2. Конструктор наград --}}
                    <div class="space-y-3 pt-4 border-t border-[#21262d]">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-[#e6edf3] flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5"><x-icon name="gift" class="w-4 h-4 text-[#a371f7]" /> Награды и предметы в карточке</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-[#21262d] text-[#58a6ff] border border-[#30363d]"
                                          x-text="form.items.length + ' поз.'"></span>
                                </h3>
                                <div class="text-[11px] text-[#8b949e]">Добавьте оружие, экипировку, транспорт, деньги, славу или VIP, которые получит игрок</div>
                            </div>

                            <button type="button" @click="toggleJsonMode()"
                                    class="text-xs text-[#8b949e] hover:text-[#58a6ff] flex items-center gap-1 cursor-pointer">
                                <span x-text="form.jsonMode ? 'Переключить в визуальный режим' : 'Режим прямого JSON'"></span>
                            </button>
                        </div>

                        {{-- Панель кнопок добавления наград --}}
                        <div x-show="!form.jsonMode" class="flex flex-wrap gap-1.5 p-2 rounded-xl bg-[#0d1117] border border-[#21262d]">
                            <button type="button" @click="addItem('item')" class="btn btn-xs bg-[#161b22] hover:bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/30 cursor-pointer">
                                <x-icon name="package" class="w-3.5 h-3.5" />
                                <span>+ Предмет</span>
                            </button>
                            <button type="button" @click="addItem('vehicle')" class="btn btn-xs bg-[#161b22] hover:bg-[#d29922]/20 text-[#d29922] border-[#d29922]/30 cursor-pointer">
                                <x-icon name="truck" class="w-3.5 h-3.5" />
                                <span>+ Транспорт</span>
                            </button>
                            <button type="button" @click="addItem('money')" class="btn btn-xs bg-[#161b22] hover:bg-[#2ea043]/20 text-[#3fb950] border-[#2ea043]/30 cursor-pointer">
                                <span class="font-bold">$</span>
                                <span>+ Деньги</span>
                            </button>
                            <button type="button" @click="addItem('gold')" class="btn btn-xs bg-[#161b22] hover:bg-[#f0883e]/20 text-[#f0883e] border-[#f0883e]/30 cursor-pointer">
                                <x-icon name="coins" class="w-3.5 h-3.5" />
                                <span>+ Золото</span>
                            </button>
                            <button type="button" @click="addItem('fame')" class="btn btn-xs bg-[#161b22] hover:bg-[#e3b341]/20 text-[#e3b341] border-[#e3b341]/30 cursor-pointer">
                                <x-icon name="star" class="w-3.5 h-3.5" />
                                <span>+ Слава</span>
                            </button>
                            <button type="button" @click="addItem('skill')" class="btn btn-xs bg-[#161b22] hover:bg-[#a371f7]/20 text-[#a371f7] border-[#a371f7]/30 cursor-pointer">
                                <x-icon name="zap" class="w-3.5 h-3.5" />
                                <span>+ Навык</span>
                            </button>
                            <button type="button" @click="addItem('attributes')" class="btn btn-xs bg-[#161b22] hover:bg-[#d29922]/20 text-[#d29922] border-[#d29922]/30 cursor-pointer">
                                <x-icon name="activity" class="w-3.5 h-3.5" />
                                <span>+ Атрибуты</span>
                            </button>
                            <button type="button" @click="addItem('vip')" class="btn btn-xs bg-[#161b22] hover:bg-[#bc4c9a]/20 text-[#d2a8ff] border-[#bc4c9a]/30 cursor-pointer">
                                <x-icon name="crown" class="w-3.5 h-3.5" />
                                <span>+ VIP статус</span>
                            </button>
                            <button type="button" @click="addItem('cargo_drop')" class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] text-[#c9d1d9] border-[#30363d] cursor-pointer">
                                <x-icon name="parachute" class="w-3.5 h-3.5" />
                                <span>+ Аирдроп</span>
                            </button>
                            <button type="button" @click="addItem('insurance')" class="btn btn-xs bg-[#161b22] hover:bg-[#2ea043]/20 text-[#3fb950] border-[#2ea043]/30 cursor-pointer">
                                <x-icon name="shield" class="w-3.5 h-3.5" />
                                <span>+ Страховка ТС</span>
                            </button>
                        </div>

                        {{-- Режим прямого редактирования JSON --}}
                        <div x-show="form.jsonMode">
                            <textarea x-model="form.rawJson"
                                      @input="handleRawJsonInput()"
                                      rows="10"
                                      class="input font-mono text-xs w-full bg-[#070a0f] text-[#c9d1d9] p-3 leading-relaxed"
                                      placeholder='[{"type":"item","data":{"itemName":"BP_DEagle_50Gold","amount":1}}]'></textarea>
                            <div class="text-[11px] text-[#8b949e] mt-1">
                                Отредактируйте JSON напрямую или переключитесь обратно в визуальный конструктор.
                            </div>
                        </div>

                        {{-- Список строк наград в карточке --}}
                        <div x-show="!form.jsonMode" class="space-y-3">
                            <template x-if="form.items.length === 0">
                                <div class="p-8 text-center rounded-2xl border border-dashed border-[#30363d] bg-[#0d1117] text-xs text-[#8b949e] space-y-2">
                                    <div class="w-12 h-12 mx-auto rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]">
                                        <x-icon name="gift" class="w-6 h-6" />
                                    </div>
                                    <div class="font-bold text-[#e6edf3]">Награды ещё не добавлены</div>
                                    <div class="text-[11px]">Нажмите кнопку выше (+ Предмет, + Транспорт, + Деньги, + VIP...) или выберите готовый шаблон.</div>
                                </div>
                            </template>

                            <template x-for="(item, index) in form.items" :key="item._uid || index">
                                <div class="p-3.5 rounded-2xl bg-[#0d1117] border border-[#21262d] hover:border-[#388bfd]/40 transition-all space-y-3 shadow-sm">
                                    
                                    {{-- Шапка строки: тип, бейдж и действия --}}
                                    <div class="flex items-center justify-between gap-2 border-b border-[#21262d] pb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base flex items-center justify-center" x-html="typeIcon(item.type)"></span>
                                            <span class="text-xs font-bold px-2 py-0.5 rounded-md border"
                                                  :class="typeBadge(item.type)"
                                                  x-text="typeLabel(item.type)"></span>
                                            <span class="text-[11px] text-[#8b949e] font-mono" x-text="'#' + (index + 1)"></span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="moveItem(index, -1)" :disabled="index === 0"
                                                    class="p-1 rounded text-xs text-[#8b949e] hover:text-[#e6edf3] disabled:opacity-30 cursor-pointer" title="Поднять выше"><x-icon name="chevron-up" class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="moveItem(index, 1)" :disabled="index === form.items.length - 1"
                                                    class="p-1 rounded text-xs text-[#8b949e] hover:text-[#e6edf3] disabled:opacity-30 cursor-pointer" title="Опустить ниже"><x-icon name="chevron-down" class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="duplicateItem(index)"
                                                    class="p-1 rounded text-xs text-[#8b949e] hover:text-[#58a6ff] cursor-pointer" title="Дублировать строку"><x-icon name="copy" class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="removeItem(index)"
                                                    class="p-1 rounded text-xs text-[#da3633] hover:text-[#f85149] cursor-pointer" title="Удалить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                                        </div>
                                    </div>

                                    {{-- ТИП: ПРЕДМЕТ (С ИНТЕРАКТИВНЫМ ВЫБОРОМ ИЗ КАТАЛОГА И ИКОНКАМИ) --}}
                                    <template x-if="item.type === 'item'">
                                        <div class="space-y-2.5">
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start"
                                                 x-data="{
                                                     searchQuery: '',
                                                     isOpen: false,
                                                     results: [],
                                                     isLoading: false,
                                                     async search() {
                                                         this.isLoading = true;
                                                         try {
                                                             const q = encodeURIComponent(this.searchQuery.trim());
                                                             const res = await fetch(`/catalog-api/items?q=${q}&limit=25`, { headers: { 'Accept': 'application/json' } });
                                                             const json = await res.json();
                                                             this.results = json.ok ? (json.data || []) : [];
                                                         } catch(e) {
                                                             this.results = [];
                                                         } finally {
                                                             this.isLoading = false;
                                                         }
                                                     },
                                                     select(it) {
                                                         item.data.itemName = it.id;
                                                         item.data._info = it;
                                                         this.isOpen = false;
                                                         this.searchQuery = '';
                                                         syncJson();
                                                     }
                                                 }">
                                                 
                                                <!-- Поле выбора предмета с иконкой и каталогом -->
                                                <div class="sm:col-span-8 space-y-1 relative">
                                                    <label class="block text-[11px] font-semibold text-[#8b949e]">
                                                        Предмет из каталога SCUM <span class="text-[#f85149]">*</span>
                                                    </label>

                                                    <!-- Состояние: предмет выбран (стильная карточка с WebP иконкой) -->
                                                    <template x-if="item.data.itemName">
                                                        <div class="flex items-center justify-between gap-2.5 p-2 rounded-xl bg-[#161b22] border border-[#388bfd]/50 shadow-sm">
                                                            <div class="flex items-center gap-2.5 min-w-0">
                                                                <div class="w-8 h-8 rounded-lg bg-[#0d1117] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5">
                                                                    <template x-if="item.data._info && item.data._info.icon">
                                                                        <img :src="'/' + item.data._info.icon" class="w-full h-full object-contain" :alt="item.data.itemName">
                                                                    </template>
                                                                    <template x-if="!item.data._info || !item.data._info.icon">
                                                                        <x-icon name="package" class="w-4 h-4 text-[#58a6ff]" />
                                                                    </template>
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <div class="text-xs font-bold text-[#e6edf3] truncate" x-text="item.data._info ? (item.data._info.name_ru || item.data._info.name) : item.data.itemName"></div>
                                                                    <div class="text-[10px] font-mono text-[#58a6ff] truncate" x-text="item.data.itemName"></div>
                                                                </div>
                                                            </div>
                                                            <button type="button" @click="isOpen = true; $nextTick(() => $refs.itemSearchInput?.focus())"
                                                                    class="btn btn-xs bg-[#21262d] hover:bg-[#30363d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d] px-2 shrink-0 cursor-pointer"
                                                                    title="Сменить предмет">
                                                                <x-icon name="edit" class="w-3 h-3" />
                                                                <span>Сменить</span>
                                                            </button>
                                                        </div>
                                                    </template>

                                                    <!-- Состояние: выбор через инпут / поиск -->
                                                    <div x-show="!item.data.itemName || isOpen" class="relative">
                                                        <div class="relative">
                                                            <span class="absolute left-3 top-2.5 text-[#8b949e] pointer-events-none">
                                                                <template x-if="isLoading">
                                                                    <div class="w-3.5 h-3.5 border-2 border-[#58a6ff] border-t-transparent rounded-full animate-spin"></div>
                                                                </template>
                                                                <template x-if="!isLoading">
                                                                    <x-icon name="search" class="w-3.5 h-3.5" />
                                                                </template>
                                                            </span>
                                                            <input type="text"
                                                                   x-ref="itemSearchInput"
                                                                   x-model="searchQuery"
                                                                   @focus="isOpen = true; if(results.length === 0) search()"
                                                                   @click="isOpen = true; if(results.length === 0) search()"
                                                                   @input.debounce.150ms="search(); isOpen = true"
                                                                   @keydown.escape="isOpen = false"
                                                                   placeholder="Поиск по названию или ID (калаш, бинт, яблоко)..."
                                                                   class="input pl-8.5 pr-8 text-xs font-medium w-full">
                                                            <button type="button" x-show="item.data.itemName && isOpen" @click="isOpen = false"
                                                                    class="absolute right-2 top-2 p-1 text-[#8b949e] hover:text-[#e6edf3] cursor-pointer">
                                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                                            </button>
                                                        </div>

                                                        <!-- Выпадающий список совпадений с иконками -->
                                                        <div x-show="isOpen"
                                                             x-cloak
                                                             @click.outside="isOpen = false"
                                                             class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-[#161b22] border border-[#30363d] rounded-xl shadow-2xl p-1.5 max-h-56 overflow-y-auto custom-scrollbar space-y-0.5 backdrop-blur-xl">
                                                            
                                                            <template x-if="isLoading">
                                                                <div class="p-4 text-center text-xs text-[#8b949e] flex items-center justify-center gap-2">
                                                                    <div class="w-3.5 h-3.5 border-2 border-[#58a6ff] border-t-transparent rounded-full animate-spin"></div>
                                                                    <span>Поиск предметов...</span>
                                                                </div>
                                                            </template>

                                                            <template x-if="!isLoading && results.length === 0">
                                                                <div class="p-4 text-center text-xs text-[#8b949e]">
                                                                    Ничего не найдено. Можно ввести точный Blueprint ID.
                                                                </div>
                                                            </template>

                                                            <template x-for="r in results" :key="r.id">
                                                                <div @click="select(r)"
                                                                     class="w-full flex items-center justify-between gap-2.5 p-2 rounded-lg text-left transition cursor-pointer hover:bg-[#1f6feb]/15 hover:border-[#388bfd]/30 border border-transparent group">
                                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                                        <div class="w-7 h-7 rounded-lg bg-[#0d1117] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5 group-hover:border-[#58a6ff]/50">
                                                                            <template x-if="r.icon">
                                                                                <img :src="'/' + r.icon" :alt="r.name" class="w-full h-full object-contain" loading="lazy">
                                                                            </template>
                                                                            <template x-if="!r.icon">
                                                                                <x-icon name="package" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                                                            </template>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] truncate" x-text="r.name_ru || r.name"></div>
                                                                            <div class="text-[10px] font-mono text-[#8b949e] truncate flex items-center gap-1.5">
                                                                                <span class="text-[#58a6ff]" x-text="r.id"></span>
                                                                                <span x-show="r.category_ru" class="opacity-70" x-text="'· ' + r.category_ru"></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <span class="text-[10px] font-semibold text-[#58a6ff] opacity-0 group-hover:opacity-100 transition shrink-0">Выбрать</span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Количество предметов -->
                                                <div class="sm:col-span-4 space-y-1">
                                                    <label class="block text-[11px] font-semibold text-[#8b949e]">
                                                        Количество (шт.) <span class="text-[#f85149]">*</span>
                                                    </label>
                                                    <input type="number" x-model.number="item.data.amount" @input="syncJson()"
                                                           class="input text-xs w-full font-mono" min="1" max="1000" value="1" required>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: ТРАНСПОРТ (С ИНТЕРАКТИВНЫМ ВЫБОРОМ И ИКОНКАМИ) --}}
                                    <template x-if="item.type === 'vehicle'">
                                        <div class="space-y-2.5">
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start"
                                                 x-data="{
                                                     searchQuery: '',
                                                     isOpen: false,
                                                     results: [],
                                                     isLoading: false,
                                                     async search() {
                                                         this.isLoading = true;
                                                         try {
                                                             const q = encodeURIComponent(this.searchQuery.trim());
                                                             const res = await fetch(`/catalog-api/vehicles?q=${q}`, { headers: { 'Accept': 'application/json' } });
                                                             const json = await res.json();
                                                             this.results = json.ok ? (json.data || []) : [];
                                                         } catch(e) {
                                                             this.results = [];
                                                         } finally {
                                                             this.isLoading = false;
                                                         }
                                                     },
                                                     select(v) {
                                                         item.data.vehicleName = v.id;
                                                         item.data._info = v;
                                                         this.isOpen = false;
                                                         this.searchQuery = '';
                                                         syncJson();
                                                     }
                                                 }">
                                                 
                                                <!-- Поле выбора транспорта с иконкой -->
                                                <div class="sm:col-span-12 space-y-1 relative">
                                                    <label class="block text-[11px] font-semibold text-[#8b949e]">
                                                        Транспортное средство SCUM <span class="text-[#f85149]">*</span>
                                                    </label>

                                                    <!-- Состояние: транспорт выбран (карточка с изображением) -->
                                                    <template x-if="item.data.vehicleName">
                                                        <div class="flex items-center justify-between gap-2.5 p-2 rounded-xl bg-[#161b22] border border-[#d29922]/50 shadow-sm">
                                                            <div class="flex items-center gap-2.5 min-w-0">
                                                                <div class="w-10 h-10 rounded-lg bg-[#0d1117] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5">
                                                                    <template x-if="item.data._info && item.data._info.icon">
                                                                        <img :src="'/' + item.data._info.icon" class="w-full h-full object-contain" :alt="item.data.vehicleName">
                                                                    </template>
                                                                    <template x-if="!item.data._info || !item.data._info.icon">
                                                                        <x-icon name="truck" class="w-5 h-5 text-[#d29922]" />
                                                                    </template>
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <div class="text-xs font-bold text-[#e6edf3] truncate" x-text="item.data._info ? (item.data._info.name_ru || item.data._info.name) : item.data.vehicleName"></div>
                                                                    <div class="text-[10px] font-mono text-[#d29922] truncate" x-text="item.data.vehicleName"></div>
                                                                </div>
                                                            </div>
                                                            <button type="button" @click="isOpen = true; $nextTick(() => $refs.vehicleSearchInput?.focus())"
                                                                    class="btn btn-xs bg-[#21262d] hover:bg-[#30363d] text-[#8b949e] hover:text-[#e6edf3] border-[#30363d] px-2 shrink-0 cursor-pointer"
                                                                    title="Сменить транспорт">
                                                                <x-icon name="edit" class="w-3 h-3" />
                                                                <span>Сменить</span>
                                                            </button>
                                                        </div>
                                                    </template>

                                                    <!-- Состояние: выбор через инпут / поиск -->
                                                    <div x-show="!item.data.vehicleName || isOpen" class="relative">
                                                        <div class="relative">
                                                            <span class="absolute left-3 top-2.5 text-[#8b949e] pointer-events-none">
                                                                <template x-if="isLoading">
                                                                    <div class="w-3.5 h-3.5 border-2 border-[#d29922] border-t-transparent rounded-full animate-spin"></div>
                                                                </template>
                                                                <template x-if="!isLoading">
                                                                    <x-icon name="truck" class="w-3.5 h-3.5 text-[#d29922]" />
                                                                </template>
                                                            </span>
                                                            <input type="text"
                                                                   x-ref="vehicleSearchInput"
                                                                   x-model="searchQuery"
                                                                   @focus="isOpen = true; if(results.length === 0) search()"
                                                                   @click="isOpen = true; if(results.length === 0) search()"
                                                                   @input.debounce.150ms="search(); isOpen = true"
                                                                   @keydown.escape="isOpen = false"
                                                                   placeholder="Поиск авто, мотоцикла, лодки (лайка, гольф, диртбайк)..."
                                                                   class="input pl-8.5 pr-8 text-xs font-medium w-full">
                                                            <button type="button" x-show="item.data.vehicleName && isOpen" @click="isOpen = false"
                                                                    class="absolute right-2 top-2 p-1 text-[#8b949e] hover:text-[#e6edf3] cursor-pointer">
                                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                                            </button>
                                                        </div>

                                                        <!-- Выпадающий список совпадений с иконками -->
                                                        <div x-show="isOpen"
                                                             x-cloak
                                                             @click.outside="isOpen = false"
                                                             class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-[#161b22] border border-[#30363d] rounded-xl shadow-2xl p-1.5 max-h-56 overflow-y-auto custom-scrollbar space-y-0.5 backdrop-blur-xl">
                                                            
                                                            <template x-if="isLoading">
                                                                <div class="p-4 text-center text-xs text-[#8b949e] flex items-center justify-center gap-2">
                                                                    <div class="w-3.5 h-3.5 border-2 border-[#d29922] border-t-transparent rounded-full animate-spin"></div>
                                                                    <span>Поиск техники...</span>
                                                                </div>
                                                            </template>

                                                            <template x-if="!isLoading && results.length === 0">
                                                                <div class="p-4 text-center text-xs text-[#8b949e]">
                                                                    Ничего не найдено. Можно указать точный класс.
                                                                </div>
                                                            </template>

                                                            <template x-for="r in results" :key="r.id">
                                                                <div @click="select(r)"
                                                                     class="w-full flex items-center justify-between gap-2.5 p-2 rounded-lg text-left transition cursor-pointer hover:bg-[#d29922]/15 hover:border-[#d29922]/30 border border-transparent group">
                                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                                        <div class="w-8 h-8 rounded-lg bg-[#0d1117] border border-[#30363d] flex items-center justify-center shrink-0 overflow-hidden p-0.5 group-hover:border-[#d29922]/50">
                                                                            <template x-if="r.icon">
                                                                                <img :src="'/' + r.icon" :alt="r.name" class="w-full h-full object-contain" loading="lazy">
                                                                            </template>
                                                                            <template x-if="!r.icon">
                                                                                <x-icon name="truck" class="w-4 h-4 text-[#d29922]" />
                                                                            </template>
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#d29922] truncate" x-text="r.name_ru || r.name"></div>
                                                                            <div class="text-[10px] font-mono text-[#8b949e] truncate flex items-center gap-1.5">
                                                                                <span class="text-[#d29922]" x-text="r.id"></span>
                                                                                <span x-show="r.type_ru" class="opacity-70" x-text="'· ' + r.type_ru"></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <span class="text-[10px] font-semibold text-[#d29922] opacity-0 group-hover:opacity-100 transition shrink-0">Выбрать</span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: ДЕНЬГИ --}}
                                    <template x-if="item.type === 'money'">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Сумма внутриигровой валюты ($)</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-2 text-[#3fb950] font-bold text-xs">$</span>
                                                <input type="number" x-model.number="item.data.amount" @input="syncJson()"
                                                       class="input font-mono text-xs w-full pl-7" min="1" step="100" placeholder="5000" required>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: ЗОЛОТО --}}
                                    <template x-if="item.type === 'gold'">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Количество золотых слитков</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-2.5 text-[#f0883e] text-xs"><x-icon name="coins" class="w-3.5 h-3.5" /></span>
                                                <input type="number" x-model.number="item.data.amount" @input="syncJson()"
                                                       class="input font-mono text-xs w-full pl-8" min="1" placeholder="10" required>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: СЛАВА --}}
                                    <template x-if="item.type === 'fame'">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Очки славы (Fame Points)</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-2.5 text-[#e3b341] text-xs"><x-icon name="star" class="w-3.5 h-3.5" /></span>
                                                <input type="number" x-model.number="item.data.amount" @input="syncJson()"
                                                       class="input font-mono text-xs w-full pl-8" min="1" placeholder="100" required>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: НАВЫК --}}
                                    <template x-if="item.type === 'skill'">
                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                            <div class="sm:col-span-8">
                                                <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Навык персонажа</label>
                                                <select x-model="item.data.skillName" @change="syncJson()"
                                                        class="bg-[#0d1117] border border-[#30363d] rounded-xl px-3 py-1.5 text-xs text-[#e6edf3] w-full outline-none">
                                                    <template x-for="s in availableSkills" :key="s.id">
                                                        <option :value="s.id" x-text="s.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div class="sm:col-span-4">
                                                <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Уровень навыка (0-4)</label>
                                                <select x-model.number="item.data.level" @change="syncJson()"
                                                        class="bg-[#0d1117] border border-[#30363d] rounded-xl px-3 py-1.5 text-xs text-[#e6edf3] w-full outline-none">
                                                    <option value="0">0 — Нет навыка (No skill)</option>
                                                    <option value="1">1 — Базовый (Basic)</option>
                                                    <option value="2">2 — Средний (Medium)</option>
                                                    <option value="3">3 — Продвинутый (Advanced)</option>
                                                    <option value="4">4 — Эксперт (Advanced+)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: АТРИБУТЫ --}}
                                    <template x-if="item.type === 'attributes'">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-2">Значения характеристик персонажа (1 - 8)</label>
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                <div>
                                                    <span class="text-[10px] text-[#8b949e] block mb-1">Сила (Str)</span>
                                                    <input type="number" x-model.number="item.data.strength" @input="syncJson()" min="1" max="8" class="input text-xs" value="5">
                                                </div>
                                                <div>
                                                    <span class="text-[10px] text-[#8b949e] block mb-1">Выносливость (Sta)</span>
                                                    <input type="number" x-model.number="item.data.stamina" @input="syncJson()" min="1" max="8" class="input text-xs" value="5">
                                                </div>
                                                <div>
                                                    <span class="text-[10px] text-[#8b949e] block mb-1">Ловкость (Dex)</span>
                                                    <input type="number" x-model.number="item.data.dexterity" @input="syncJson()" min="1" max="8" class="input text-xs" value="5">
                                                </div>
                                                <div>
                                                    <span class="text-[10px] text-[#8b949e] block mb-1">Интеллект (Int)</span>
                                                    <input type="number" x-model.number="item.data.intellect" @input="syncJson()" min="1" max="8" class="input text-xs" value="5">
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: VIP --}}
                                    <template x-if="item.type === 'vip'">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Длительность VIP-статуса (в днях)</label>
                                            <div class="relative max-w-xs">
                                                <input type="number" x-model.number="item.data.days" @input="syncJson()"
                                                       class="input text-xs w-full pr-12" min="1" max="3650" value="30" required>
                                                <span class="absolute right-3 top-2 text-[#8b949e] text-xs font-semibold">дней</span>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- ТИП: CARGO DROP --}}
                                    <template x-if="item.type === 'cargo_drop'">
                                        <div class="p-3 rounded-xl bg-[#070a0f] border border-[#21262d] text-xs text-[#8b949e] flex items-center gap-2.5">
                                            <x-icon name="package" class="w-4 h-4 text-[#bc8cff]" />
                                            <span>При активации на персонажа будет сброшен серверный аирдроп (BP_CargoDropEvent).</span>
                                        </div>
                                    </template>

                                    {{-- ТИП: СТРАХОВКА ТС --}}
                                    <template x-if="item.type === 'insurance'">
                                        <div class="space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Количество выплат (страховых возвратов)</label>
                                                    <input type="number" x-model.number="item.data.claims" @input="syncJson()"
                                                           class="input text-xs w-full" min="1" max="100" value="1" required>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#8b949e] mb-1">Срок действия страховки (в днях)</label>
                                                    <div class="relative">
                                                        <input type="number" x-model.number="item.data.days" @input="syncJson()"
                                                               class="input text-xs w-full pr-12" min="1" max="3650" value="30" required>
                                                        <span class="absolute right-3 top-2 text-[#8b949e] text-xs font-semibold">дней</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="p-3 rounded-xl bg-[#070a0f] border border-[#21262d] text-xs text-[#8b949e] flex items-center gap-2.5">
                                                <x-icon name="shield" class="w-4 h-4 text-[#3fb950]" />
                                                <span>Игроку выдаётся страховой полис. Для привязки ТС в игре нужно сесть в автомобиль и ввести команду <code class="mono text-[#58a6ff]">!страховка привязать</code>.</span>
                                            </div>
                                        </div>
                                    </template>

                                </div>
                            </template>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Modal Footer --}}
            <div class="p-4 sm:px-6 py-4 bg-[#0e131f] border-t border-[#21262d] flex items-center justify-between gap-3 shrink-0">
                <button type="button" @click="closeModal()" class="btn btn-sm cursor-pointer">
                    Отмена
                </button>
                <button type="submit" form="wargm-card-form" class="btn btn-primary shadow-lg shadow-[#1f6feb]/20 flex items-center gap-1.5 cursor-pointer">
                    <x-icon name="check" class="w-4 h-4" />
                    <span x-text="modalMode === 'create' ? 'Создать карточку' : 'Сохранить изменения'"></span>
                </button>
            </div>

        </div>
    </div>

</div>
@endsection
