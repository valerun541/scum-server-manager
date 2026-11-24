@extends('layouts.app')

@section('title', 'Плагин: Страхование ТС')

@section('content')
<div x-data="{
    searchQuery: '{{ $search ?? '' }}',
    grantModal: false,
    editModal: false,
    editPolicy: {
        id: null,
        steam_id: '',
        player_name: '',
        vehicle_id: '',
        vehicle_asset: '',
        vehicle_alias: '',
        status: 'pending',
        claims_total: 1,
        claims_used: 0,
        expires_at: '',
        notes: ''
    },
    openEdit(pol) {
        this.editPolicy = {
            id: pol.id,
            steam_id: pol.steam_id || '',
            player_name: pol.player_name || '',
            vehicle_id: pol.vehicle_id || '',
            vehicle_asset: pol.vehicle_asset || '',
            vehicle_alias: pol.vehicle_alias || '',
            status: pol.status || 'pending',
            claims_total: pol.claims_total ?? 1,
            claims_used: pol.claims_used ?? 0,
            expires_at: pol.expires_at ? pol.expires_at.replace(' ', 'T').substring(0, 16) : '',
            notes: pol.notes || ''
        };
        this.editModal = true;
    }
}" class="space-y-6">

    {{-- ===== Hero Banner ===== --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101923] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#2ea043]/15 blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb]/30 to-[#388bfd]/20 border border-[#388bfd]/40 flex items-center justify-center text-[#58a6ff] shadow-lg shadow-[#1f6feb]/15 shrink-0"><x-icon name="shield" class="w-7 h-7" /></div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Страхование транспорта</h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ ($settings['enabled'] ?? true) ? 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ ($settings['enabled'] ?? true) ? 'bg-[#3fb950] animate-pulse' : 'bg-[#f85149]' }}"></span>
                            {{ ($settings['enabled'] ?? true) ? 'Плагин активен' : 'Плагин отключен' }}
                        </span>
                    </div>
                    <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                        Автоматическое восстановление транспорта после покупки страховки на Wargm или выдачи админом.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                    <span class="text-[#8b949e]">Всего:</span>
                    <span class="font-bold text-[#e6edf3]">{{ $stats['total'] ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                    <span class="text-[#8b949e]">Активных:</span>
                    <span class="font-bold text-[#3fb950]">{{ $stats['active'] ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                    <span class="text-[#8b949e]">Ожидают:</span>
                    <span class="font-bold text-[#d29922]">{{ $stats['pending'] ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                    <span class="text-[#8b949e]">Уничтожено:</span>
                    <span class="font-bold text-[#f85149]">{{ $stats['destroyed'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Main Content Grid ===== --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Left Column (4 cols): Настройки & Быстрая выдача --}}
        <div class="space-y-6 lg:col-span-4">

            {{-- 1. Настройки страхования --}}
            <div class="panel p-5 md:p-6 rounded-2xl space-y-4">
                <form action="{{ route('plugins.insurance.settings') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="pb-3 border-b border-[#30363d] flex items-center justify-between">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="settings" class="w-4 h-4 text-sky-400" /> Параметры плагина
                        </h2>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#0d1117] border border-[#21262d]">
                            <div>
                                <span class="font-semibold text-[#e6edf3] block">Работа плагина</span>
                                <span class="text-[11px] text-[#8b949e]">Вкл/выкл в <a href="{{ route('servers.index') }}" class="text-[#58a6ff] hover:underline">настройках сервера</a></span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ ($settings['enabled'] ?? true) ? 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ ($settings['enabled'] ?? true) ? 'bg-[#3fb950]' : 'bg-[#f85149]' }}"></span>
                                {{ ($settings['enabled'] ?? true) ? 'Включен' : 'Отключен' }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Кулдаун между возвратами (мин.)
                            </label>
                            <input type="number" name="cooldown_minutes" min="0" max="1440"
                                   value="{{ $settings['cooldown_minutes'] ?? 60 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">Минимальное время между повторным спавном ТС</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Страховых возвратов по умолчанию
                            </label>
                            <input type="number" name="claims_per_policy" min="1" max="100"
                                   value="{{ $settings['claims_per_policy'] ?? 1 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">Сколько раз можно вернуть уничтоженное авто</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Срок действия страховки (дней)
                            </label>
                            <input type="number" name="duration_days" min="1" max="365"
                                   value="{{ $settings['duration_days'] ?? 30 }}"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            <div class="text-[11px] text-[#8b949e] mt-1">Через сколько дней полис сгорает</div>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#0d1117] border border-[#21262d]">
                            <div>
                                <span class="font-semibold text-[#e6edf3] block">Защита от дюпа</span>
                                <span class="text-[11px] text-[#8b949e]">Удалять старый кузов с карты при спавне</span>
                            </div>
                            <input type="checkbox" name="destroy_old_if_alive" value="1" {{ ($settings['destroy_old_if_alive'] ?? true) ? 'checked' : '' }}
                                   class="checkbox checkbox-sm checkbox-primary">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-full py-2 font-bold shadow-md shadow-[#238636]/20">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить параметры</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. Ручная выдача полиса игроку --}}
            <div class="panel p-5 md:p-6 rounded-2xl space-y-4">
                <form action="{{ route('plugins.insurance.grant') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="pb-3 border-b border-[#30363d]">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <x-icon name="gift" class="w-4 h-4 text-emerald-400" /> Выдать страховку игроку
                        </h2>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Steam ID игрока <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="text" name="steam_id" required
                                   placeholder="76561198..."
                                   class="input text-xs py-2 mono bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Имя игрока (опционально)
                            </label>
                            <input type="text" name="player_name"
                                   placeholder="Survivor"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Возвратов</label>
                                <input type="number" name="claims" min="1" max="100" value="1"
                                       class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Дней</label>
                                <input type="number" name="duration_days" min="1" max="365" value="30"
                                       class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-full py-2 font-bold shadow-md shadow-[#1f6feb]/20">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="shield" class="w-4 h-4" /> Оформить полис</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 3. Справка по чат-командам --}}
            <div class="panel p-5 md:p-6 rounded-2xl space-y-3 text-xs">
                <h3 class="font-bold text-sm text-[#e6edf3] flex items-center gap-2">
                    <x-icon name="message-square" class="w-4 h-4 text-sky-400" /> Чат-команды для игроков
                </h3>
                <div class="space-y-2 text-[#8b949e]">
                    <div class="p-2 rounded-lg bg-[#0d1117] border border-[#21262d]">
                        <code class="text-[#58a6ff] font-bold">!страховка</code>
                        <div class="mt-0.5 text-[11px]">Посмотреть статус страховки, авто и оставшиеся возвраты.</div>
                    </div>
                    <div class="p-2 rounded-lg bg-[#0d1117] border border-[#21262d]">
                        <code class="text-[#3fb950] font-bold">!страховка привязать</code>
                        <div class="mt-0.5 text-[11px]">Привязать автомобиль (нужно находиться в авто или вплотную).</div>
                    </div>
                    <div class="p-2 rounded-lg bg-[#0d1117] border border-[#21262d]">
                        <code class="text-[#f0883e] font-bold">!страховка вернуть</code>
                        <div class="mt-0.5 text-[11px]">Восстановить транспорт при уничтожении или утрате.</div>
                    </div>
                    <div class="p-2 rounded-lg bg-[#0d1117] border border-[#21262d]">
                        <code class="text-[#d2a8ff] font-bold">!страховка отвязать</code>
                        <div class="mt-0.5 text-[11px]">Отвязать авто от полиса (для продажи или смены авто).</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right Column (8 cols): Таблица страховых полисов --}}
        <div class="panel p-5 md:p-6 lg:col-span-8 rounded-2xl flex flex-col justify-between space-y-4">
            <div>
                {{-- Search & Header --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <x-icon name="file-text" class="w-5 h-5 text-sky-400" />
                        <h2 class="font-bold text-base text-[#e6edf3]">Реестр страховых полисов</h2>
                        <span class="text-xs text-[#8b949e]">({{ count($policies) }})</span>
                    </div>

                    <form method="GET" action="{{ route('plugins.insurance') }}" class="flex items-center gap-2">
                        <input type="text" name="q" value="{{ $search ?? '' }}"
                               placeholder="Поиск по SteamID, имени или авто..."
                               class="input input-sm text-xs bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-56 sm:w-64">
                        @if(!empty($search))
                            <a href="{{ route('plugins.insurance') }}" class="btn btn-sm btn-ghost text-xs">Сброс</a>
                        @endif
                        <button type="submit" class="btn btn-sm btn-ghost"><x-icon name="search" class="w-3.5 h-3.5 text-[#8b949e]" /></button>
                    </form>
                </div>

                {{-- Table --}}
                @if (count($policies) === 0)
                    <div class="text-sm text-[#8b949e] text-center py-16 bg-[#0d1117]/50 rounded-xl border border-[#30363d]/50">
                        Страховых полисов пока нет. Они создаются автоматически при покупке в WARGM или через форму слева.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117] max-h-[620px] overflow-y-auto">
                        <table class="table text-xs">
                            <thead>
                                <tr class="bg-[#101623] border-b border-[#30363d]">
                                    <th class="py-2.5 px-3">#ID</th>
                                    <th class="py-2.5 px-3">Игрок</th>
                                    <th class="py-2.5 px-3">Транспорт</th>
                                    <th class="py-2.5 px-3">Статус</th>
                                    <th class="py-2.5 px-3">Возвраты</th>
                                    <th class="py-2.5 px-3">Срок</th>
                                    <th class="py-2.5 px-3 text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#21262d]">
                                @foreach ($policies as $pol)
                                    @php
                                        $statusBadges = [
                                            'pending' => 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30',
                                            'active' => 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30',
                                            'destroyed' => 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30',
                                            'claimed' => 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30',
                                            'expired' => 'bg-[#21262d] text-[#8b949e] border-[#30363d]',
                                        ];
                                        $statusTitles = [
                                            'pending' => 'Ожидает привязки',
                                            'active' => 'Действует',
                                            'destroyed' => 'Уничтожен',
                                            'claimed' => 'Выплачен',
                                            'expired' => 'Истёк',
                                        ];
                                        $badgeClass = $statusBadges[$pol['status']] ?? 'bg-[#21262d] text-[#8b949e] border-[#30363d]';
                                        $badgeTitle = $statusTitles[$pol['status']] ?? $pol['status'];
                                        $assetClean = !empty($pol['vehicle_asset']) ? str_replace(['BP_', '_ES', '_C'], '', $pol['vehicle_asset']) : '—';
                                    @endphp
                                    <tr class="hover:bg-[#161b22] transition-colors">
                                        <td class="py-2.5 px-3 font-mono text-[#8b949e]">
                                            #{{ $pol['id'] }}
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-semibold text-[#e6edf3]">
                                                {{ $pol['player_name'] ?: 'Игрок' }}
                                            </div>
                                            <a href="{{ route('scumdb.player', ['steamId' => $pol['steam_id']]) }}"
                                               class="mono text-[11px] text-[#58a6ff] hover:underline">
                                                {{ $pol['steam_id'] }}
                                            </a>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            @if(!empty($pol['vehicle_asset']))
                                                <div class="font-medium text-[#e6edf3] flex items-center gap-1.5">
                                                    <x-icon name="car" class="w-4 h-4 text-amber-400" />
                                                    <span>{{ $assetClean }}</span>
                                                </div>
                                                <div class="text-[11px] text-[#8b949e] mono">
                                                    ID: {{ $pol['vehicle_id'] ?: '—' }} {{ $pol['vehicle_alias'] ? "({$pol['vehicle_alias']})" : '' }}
                                                </div>
                                            @else
                                                <span class="text-[#8b949e] italic">Не привязан</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                                {{ $badgeTitle }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="font-bold text-[#e6edf3]">{{ $pol['claims_used'] }}</span>
                                            <span class="text-[#8b949e]">/ {{ $pol['claims_total'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-3 text-[11px] text-[#8b949e]">
                                            @if($pol['expires_at'])
                                                {{ \Carbon\Carbon::parse($pol['expires_at'])->format('d.m.Y H:i') }}
                                            @else
                                                Бессрочно
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                {{-- Кнопка редактирования --}}
                                                <button type="button" @click="openEdit({{ json_encode($pol) }})" title="Редактировать параметры страховки"
                                                        class="btn btn-xs bg-[#1f6feb]/20 hover:bg-[#1f6feb]/40 text-[#58a6ff] border-[#388bfd]/30">
                                                    <span class="inline-flex items-center gap-1"><x-icon name="edit" class="w-3.5 h-3.5" /> Редактировать</span>
                                                </button>

                                                {{-- Кнопка спавна админом (если привязан авто) --}}
                                                @if(!empty($pol['vehicle_asset']))
                                                    <form action="{{ route('plugins.insurance.claim') }}" method="POST"
                                                          onsubmit="return confirm('Выдать транспорт игроку {{ $pol['player_name'] ?: $pol['steam_id'] }} прямо сейчас?')">
                                                        @csrf
                                                        <input type="hidden" name="steam_id" value="{{ $pol['steam_id'] }}">
                                                        <button type="submit" title="Спавн транспорта игроку"
                                                                class="btn btn-xs bg-[#238636]/20 hover:bg-[#238636]/40 text-[#3fb950] border-[#2ea043]/30">
                                                            <span class="inline-flex items-center gap-1"><x-icon name="zap" class="w-3.5 h-3.5" /> Спавн</span>
                                                        </button>
                                                    </form>
                                                @endif

                                                {{-- Кнопка удаления --}}
                                                <form action="{{ route('plugins.insurance.delete', ['id' => $pol['id']]) }}" method="POST"
                                                      onsubmit="return confirm('Удалить страховой полис #{{ $pol['id'] }}?')">
                                                    @csrf
                                                    <button type="submit" title="Удалить"
                                                            class="btn btn-xs btn-ghost text-[#f85149] hover:bg-[#da3633]/20">
                                                        <x-icon name="trash" class="w-3.5 h-3.5 text-red-400" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="text-[11px] text-[#8b949e] border-t border-[#30363d] pt-3 flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5"><x-icon name="shield" class="w-4 h-4 text-emerald-400" /> Автоматический спавн использует точные 3D координаты игрока с безопасным смещением.</span>
                <span>SCUM Server Manager</span>
            </div>
        </div>

    </div>

    {{-- ===== Datalist: Модели транспорта SCUM ===== --}}
    <datalist id="scum-vehicles-list">
        <option value="BPC_Rager">BPC_Rager (Бычара / Внедорожник)</option>
        <option value="BPC_Cruiser">BPC_Cruiser (Круизер / Мотоцикл)</option>
        <option value="BPC_Laika">BPC_Laika (Лайка / Хэтчбек)</option>
        <option value="BPC_WolfsWagen">BPC_WolfsWagen (Вольфсваген)</option>
        <option value="BPC_Dirtbike">BPC_Dirtbike (Кроссовый мотоцикл)</option>
        <option value="BPC_SidecarBike">BPC_SidecarBike (Мотоцикл с коляской)</option>
        <option value="BPC_MountainBike">BPC_MountainBike (Горный велосипед)</option>
        <option value="BPC_CityBike">BPC_CityBike (Городской велосипед)</option>
        <option value="BPC_Tractor">BPC_Tractor (Трактор)</option>
        <option value="BPC_Barba">BPC_Barba (Катер Барба)</option>
        <option value="BPC_Dinghy">BPC_Dinghy (Моторная лодка Dinghy)</option>
        <option value="BPC_BigRaft">BPC_BigRaft (Большой плот)</option>
        <option value="BPC_RIS">BPC_RIS (Катер RIS)</option>
        <option value="BPC_SUP">BPC_SUP (SUP-доска)</option>
        <option value="BP_WheelBarrow_Metal">BP_WheelBarrow_Metal (Металлическая тачка)</option>
        <option value="BP_WheelBarrow_Improvised">BP_WheelBarrow_Improvised (Самодельная тачка)</option>
    </datalist>

    {{-- ===== Edit Insurance Modal ===== --}}
    <div x-show="editModal" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
         style="background:rgba(2, 6, 12, 0.85); backdrop-filter:blur(8px)"
         @keydown.escape.window="editModal = false">
        
        <div class="relative w-full max-w-2xl my-auto rounded-2xl border border-[#30363d]/90 bg-[#121824] shadow-2xl shadow-black/80 flex flex-col max-h-[92vh] overflow-hidden" @click.stop>
            {{-- Верхняя полоса акцента --}}
            <div class="h-1.5 w-full bg-gradient-to-r from-[#1f6feb] via-[#388bfd] to-[#238636]"></div>

            {{-- Шапка модального окна --}}
            <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-[#30363d]/80 bg-[#0e131f]/90 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-lg text-[#58a6ff]">
                        <x-icon name="edit" class="w-3.5 h-3.5 text-sky-400" />
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-[#e6edf3]">
                            Редактирование полиса <span class="font-mono text-[#58a6ff]" x-text="'#' + editPolicy.id"></span>
                        </h2>
                        <p class="text-xs text-[#8b949e]">Изменение параметров страховки, привязанного авто и лимитов</p>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="p-2 rounded-lg text-[#8b949e] hover:text-[#e6edf3] hover:bg-[#21262d] transition-colors">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            {{-- Форма редактирования --}}
            <form :action="'{{ url('/plugins/insurance') }}/' + editPolicy.id + '/update'" method="POST" class="flex flex-col overflow-y-auto custom-scrollbar">
                @csrf
                <div class="p-5 sm:p-6 space-y-4 text-xs">
                    {{-- Строка 1: Игрок и SteamID --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Steam ID игрока <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="text" name="steam_id" x-model="editPolicy.steam_id" required
                                   class="input text-xs py-2 mono bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Имя игрока (никнейм)
                            </label>
                            <input type="text" name="player_name" x-model="editPolicy.player_name"
                                   placeholder="Никнейм игрока"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                        </div>
                    </div>

                    {{-- Строка 2: Статус и Срок действия --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Статус полиса <span class="text-[#f85149]">*</span>
                            </label>
                            <select name="status" x-model="editPolicy.status"
                                    class="select text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                                <option value="pending">Ожидает привязки (Pending)</option>
                                <option value="active">Действует / Привязан (active)</option>
                                <option value="destroyed">Уничтожен / Готов к спавну (destroyed)</option>
                                <option value="claimed">Выплачен / Лимит исчерпан (claimed)</option>
                                <option value="expired">Истёк по времени (Expired)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Срок действия (до)
                            </label>
                            <input type="datetime-local" name="expires_at" x-model="editPolicy.expires_at"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                            <div class="text-[10px] text-[#8b949e] mt-1">Оставьте пустым для бессрочного полиса</div>
                        </div>
                    </div>

                    {{-- Секция: Привязанный транспорт --}}
                    <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#21262d] space-y-3">
                        <div class="font-bold text-[#e6edf3] flex items-center gap-1.5 pb-2 border-b border-[#21262d]">
                            <x-icon name="car" class="w-4 h-4 text-amber-400" /> Привязанный транспорт
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-[#8b949e] mb-1">
                                    Модель транспорта (Asset)
                                </label>
                                <input type="text" name="vehicle_asset" x-model="editPolicy.vehicle_asset"
                                       list="scum-vehicles-list" placeholder="Например: BPC_Rager"
                                       class="input text-xs py-2 mono bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] w-full">
                            </div>
                            <div>
                                <label class="block font-semibold text-[#8b949e] mb-1">
                                    ID авто на сервере
                                </label>
                                <input type="number" name="vehicle_id" x-model="editPolicy.vehicle_id"
                                       placeholder="3805195"
                                       class="input text-xs py-2 mono bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] w-full">
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Позывной / Кастомное имя авто
                            </label>
                            <input type="text" name="vehicle_alias" x-model="editPolicy.vehicle_alias"
                                   placeholder="Черный Демон, Бычара и т.д."
                                   class="input text-xs py-2 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] w-full">
                        </div>
                    </div>

                    {{-- Строка 4: Счётчики возвратов --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Всего возвратов (лимит) <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="number" name="claims_total" x-model="editPolicy.claims_total" min="1" max="1000" required
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                            <div class="text-[10px] text-[#8b949e] mt-1">Сколько раз разрешено восстановить авто</div>
                        </div>
                        <div>
                            <label class="block font-semibold text-[#8b949e] mb-1">
                                Использовано возвратов <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="number" name="claims_used" x-model="editPolicy.claims_used" min="0" max="1000" required
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full">
                            <div class="text-[10px] text-[#8b949e] mt-1">Сколько раз игрок уже восстанавливал авто</div>
                        </div>
                    </div>

                    {{-- Строка 5: Заметки администратора --}}
                    <div>
                        <label class="block font-semibold text-[#8b949e] mb-1">
                            Заметки администратора
                        </label>
                        <textarea name="notes" x-model="editPolicy.notes" rows="2"
                                  placeholder="Причина редактирования или спец. пометки..."
                                  class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] w-full"></textarea>
                    </div>
                </div>

                {{-- Подвал модального окна --}}
                <div class="flex items-center justify-end gap-2.5 px-5 sm:px-6 py-3.5 border-t border-[#30363d]/80 bg-[#0e131f]/90 shrink-0">
                    <button type="button" @click="editModal = false"
                            class="btn btn-sm btn-ghost text-[#8b949e] hover:text-[#e6edf3]">
                        Отмена
                    </button>
                    <button type="submit"
                            class="btn btn-sm btn-primary py-1.5 px-4 font-bold shadow-md shadow-[#238636]/20">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить изменения</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
