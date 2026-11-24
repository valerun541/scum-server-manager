@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', 'RCON Консоль')

@section('content')
    @php
        $isConnected = ($status['connected'] ?? false);
        $boot = [
            'connected' => $isConnected,
            'history' => collect($history->items())->map(fn($r) => [
                'cmd' => $r->command ?? '',
                'response' => $r->response ?? '',
                'error' => $r->error ?? '',
            ])->values(),
        ];

        $quickCategories = [
            'all' => 'Все',
            'server' => 'Сервер & Статус',
            'players' => 'Игроки & Онлайн',
            'vehicles' => 'Транспорт & Авто',
            'base' => 'Базы & Территории',
            'economy' => 'Экономика & Слава',
            'moderation' => 'Модерация & Бан',
            'chat' => 'Оповещения & Чат',
            'npcs' => 'Существа, Роботы & Зомби',
            'spawn' => 'Спавн лута & Эвенты',
            'squads' => 'Сквады & Квесты',
        ];

        $quickCommands = [
            // 1. Сервер & Статус (server)
            ['category' => 'server', 'cmd' => 'ListCommands', 'icon' => 'terminal', 'name' => 'ListCommands', 'desc' => 'Список всех 238 команд сервера'],
            ['category' => 'server', 'cmd' => 'CheckServerTime', 'icon' => 'clock', 'name' => 'CheckServerTime', 'desc' => 'Текущее серверное игровое время'],
            ['category' => 'server', 'cmd' => 'SetTime 12 00', 'icon' => 'sun', 'name' => 'SetTime (Время)', 'desc' => 'Установить время: SetTime <Час> [Минута]'],
            ['category' => 'server', 'cmd' => 'SetWeather 0.0', 'icon' => 'cloud-rain', 'name' => 'SetWeather (Погода)', 'desc' => 'Погода: 0.0 (ясно) .. 1.0 (шторм)'],
            ['category' => 'server', 'cmd' => 'ShutdownServer 60', 'icon' => 'power', 'name' => 'ShutdownServer (Таймер)', 'desc' => 'Безопасное выключение сервера через N секунд'],
            ['category' => 'server', 'cmd' => 'ReloadCustomMapConfig', 'icon' => 'refresh-cw', 'name' => 'ReloadCustomMapConfig', 'desc' => 'Перезагрузить пользовательский конфиг карты'],

            // 2. Игроки & Онлайн (players)
            ['category' => 'players', 'cmd' => 'ListPlayers', 'icon' => 'users', 'name' => 'ListPlayers (Онлайн)', 'desc' => 'Список игроков онлайн со статусом и пингом'],
            ['category' => 'players', 'cmd' => 'Whois ', 'icon' => 'search', 'name' => 'Whois / Досье', 'desc' => 'Полное досье игрока по SteamID'],
            ['category' => 'players', 'cmd' => 'Location ', 'icon' => 'map-pin', 'name' => 'Location игрока', 'desc' => 'Текущие координаты игрока по SteamID'],
            ['category' => 'players', 'cmd' => 'Teleport 0 0 0 ', 'icon' => 'crosshair', 'name' => 'Teleport по координатам', 'desc' => 'Teleport <X> <Y> <Z> [SteamID]'],
            ['category' => 'players', 'cmd' => 'TeleportTo ', 'icon' => 'navigation', 'name' => 'TeleportTo (К игроку)', 'desc' => 'TeleportTo <SteamID_1> <SteamID_2>'],
            ['category' => 'players', 'cmd' => 'TeleportToMe ', 'icon' => 'corner-down-left', 'name' => 'TeleportToMe (Ко мне)', 'desc' => 'Телепортировать игрока к себе по SteamID'],
            ['category' => 'players', 'cmd' => 'SetGodMode True ', 'icon' => 'shield', 'name' => 'SetGodMode (Бессмертие)', 'desc' => 'Режим неуязвимости: SetGodMode <True|False> [SteamID]'],
            ['category' => 'players', 'cmd' => 'SetInfiniteStamina True ', 'icon' => 'zap', 'name' => 'SetInfiniteStamina', 'desc' => 'Бесконечная выносливость персонажа'],
            ['category' => 'players', 'cmd' => 'SetInfiniteAmmo True ', 'icon' => 'target', 'name' => 'SetInfiniteAmmo', 'desc' => 'Бесконечные патроны для оружия'],
            ['category' => 'players', 'cmd' => 'Knockout ', 'icon' => 'activity', 'name' => 'Knockout игрока', 'desc' => 'Нокаут: Knockout <SteamID> [Длительность]'],

            // 3. Транспорт & Авто (vehicles)
            ['category' => 'vehicles', 'cmd' => 'ListSpawnedVehicles', 'icon' => 'truck', 'name' => 'ListSpawnedVehicles', 'desc' => 'Список созданной техники с ID и координатами'],
            ['category' => 'vehicles', 'cmd' => 'ListVehicles', 'icon' => 'list', 'name' => 'ListVehicles', 'desc' => 'Список всех классов транспорта в игре'],
            ['category' => 'vehicles', 'cmd' => 'TeleportToVehicle ', 'icon' => 'navigation-2', 'name' => 'TeleportToVehicle', 'desc' => 'Телепорт к транспорту по VehicleId'],
            ['category' => 'vehicles', 'cmd' => 'SpawnVehicle BPC_Laika', 'icon' => 'plus-circle', 'name' => 'SpawnVehicle (Лайка)', 'desc' => 'SpawnVehicle <Класс> [Координаты|SteamID]'],
            ['category' => 'vehicles', 'cmd' => 'DestroyVehicle ', 'icon' => 'trash-2', 'name' => 'DestroyVehicle', 'desc' => 'Удалить транспорт по его VehicleId'],
            ['category' => 'vehicles', 'cmd' => 'DestroyAllVehicles', 'icon' => 'alert-triangle', 'name' => 'DestroyAllVehicles', 'desc' => 'Удалить весь ничейный транспорт на сервере'],

            // 4. Базы & Территории (base)
            ['category' => 'base', 'cmd' => 'ListFlags', 'icon' => 'flag', 'name' => 'ListFlags (Список баз)', 'desc' => 'Список всех зарегистрированных флагов и баз'],
            ['category' => 'base', 'cmd' => 'ShowFlagInfo ', 'icon' => 'info', 'name' => 'ShowFlagInfo', 'desc' => 'Информация о флаге базы по его ID'],
            ['category' => 'base', 'cmd' => 'ShowFlagLocations', 'icon' => 'map', 'name' => 'ShowFlagLocations', 'desc' => 'Координаты всех установленных флагов'],
            ['category' => 'base', 'cmd' => 'UpgradeBaseBuildingElementsWithinRadius 5000 1', 'icon' => 'hammer', 'name' => 'UpgradeBase Elements', 'desc' => 'Апгрейд элементов базы в радиусе (см) до уровня'],
            ['category' => 'base', 'cmd' => 'DestroyAllBaseBuildingElementsWithinRadius 500', 'icon' => 'x-circle', 'name' => 'Destroy Elements Radius', 'desc' => 'Уничтожить элементы базы в радиусе (см)'],
            ['category' => 'base', 'cmd' => 'DestroyAllBaseBuildingElementsForFlag ', 'icon' => 'trash', 'name' => 'DestroyFlag Elements', 'desc' => 'Уничтожить всю базу по ID флага'],

            // 5. Оповещения & Чат (chat)
            ['category' => 'chat', 'cmd' => 'Announce Внимание: Рестарт сервера через 10 минут!', 'icon' => 'megaphone', 'name' => 'Announce (Анонс на экран)', 'desc' => 'Системное оповещение по центру экрана всем онлайн'],
            ['category' => 'chat', 'cmd' => 'SendNotification 1 "Важное сообщение сервера" ', 'icon' => 'bell', 'name' => 'SendNotification', 'desc' => 'SendNotification <1-4> "<Текст>" [SteamID]'],

            // 6. Экономика & Слава (economy)
            ['category' => 'economy', 'cmd' => 'ChangeCurrencyBalance Cash 1000 ', 'icon' => 'dollar-sign', 'name' => 'Начислить доллары ($)', 'desc' => 'ChangeCurrencyBalance Cash <Сумма> [SteamID]'],
            ['category' => 'economy', 'cmd' => 'ChangeCurrencyBalance Gold 5 ', 'icon' => 'coins', 'name' => 'Начислить золото', 'desc' => 'ChangeCurrencyBalance Gold <Сумма> [SteamID]'],
            ['category' => 'economy', 'cmd' => 'ChangeCurrencyBalanceToAllOnline Cash 500', 'icon' => 'users', 'name' => 'Начислить доллары всем', 'desc' => 'Выдать валюту каждому онлайн-игроку'],
            ['category' => 'economy', 'cmd' => 'ChangeFamePoints 50 ', 'icon' => 'award', 'name' => 'Начислить славу', 'desc' => 'ChangeFamePoints <Количество> [SteamID]'],
            ['category' => 'economy', 'cmd' => 'SetFamePointsToAllOnline 100', 'icon' => 'star', 'name' => 'Слава всем онлайн', 'desc' => 'Установить очки славы всем на сервере'],
            ['category' => 'economy', 'cmd' => 'ResetEconomy', 'icon' => 'refresh-ccw', 'name' => 'ResetEconomy', 'desc' => 'Сброс торговой экономики и цен у трейдеров'],

            // 7. Модерация & Бан (moderation)
            ['category' => 'moderation', 'cmd' => 'KickPlayer ', 'icon' => 'user-x', 'name' => 'KickPlayer', 'desc' => 'KickPlayer <SteamID> [Причина]'],
            ['category' => 'moderation', 'cmd' => 'BanPlayer ', 'icon' => 'slash', 'name' => 'BanPlayer', 'desc' => 'BanPlayer <SteamID> [Причина]'],
            ['category' => 'moderation', 'cmd' => 'UnbanPlayer ', 'icon' => 'check-circle', 'name' => 'UnbanPlayer', 'desc' => 'UnbanPlayer <SteamID>'],
            ['category' => 'moderation', 'cmd' => 'SilencePlayer 60 ', 'icon' => 'volume-x', 'name' => 'SilencePlayer (Мут чата)', 'desc' => 'SilencePlayer <Минуты> <SteamID>'],
            ['category' => 'moderation', 'cmd' => 'UnsilencePlayer ', 'icon' => 'volume-2', 'name' => 'UnsilencePlayer', 'desc' => 'Снять блокировку текстового чата'],
            ['category' => 'moderation', 'cmd' => 'MutePlayer ', 'icon' => 'mic-off', 'name' => 'MutePlayer (Голос)', 'desc' => 'Заглушить микрофон игрока по SteamID'],
            ['category' => 'moderation', 'cmd' => 'UnmutePlayer ', 'icon' => 'mic', 'name' => 'UnmutePlayer (Голос)', 'desc' => 'Включить микрофон игрока по SteamID'],
            ['category' => 'moderation', 'cmd' => 'ListSilencedPlayers', 'icon' => 'list', 'name' => 'ListSilencedPlayers', 'desc' => 'Список игроков с мутом текстового чата'],
            ['category' => 'moderation', 'cmd' => 'ListMutedPlayers', 'icon' => 'list', 'name' => 'ListMutedPlayers', 'desc' => 'Список игроков с заглушенным микрофоном'],

            // 8. Существа, Роботы & Зомби (npcs)
            ['category' => 'npcs', 'cmd' => 'ListSpawnedArmedNPCs', 'icon' => 'cpu', 'name' => 'ListSpawnedArmedNPCs', 'desc' => 'Список вооруженных роботов/NPC на сервере'],
            ['category' => 'npcs', 'cmd' => 'ListAnimals', 'icon' => 'feather', 'name' => 'ListAnimals', 'desc' => 'Список всех классов диких животных'],
            ['category' => 'npcs', 'cmd' => 'SpawnAnimal BP_Bear 1', 'icon' => 'plus', 'name' => 'SpawnAnimal (Медведь)', 'desc' => 'SpawnAnimal <Класс> [Количество]'],
            ['category' => 'npcs', 'cmd' => 'SpawnArmedNPC BP_Guard 1', 'icon' => 'shield', 'name' => 'SpawnArmedNPC', 'desc' => 'Спавн вооруженного робота-стража'],
            ['category' => 'npcs', 'cmd' => 'SpawnZombie BP_Zombie_Fast 1', 'icon' => 'skull', 'name' => 'SpawnZombie (Марионетка)', 'desc' => 'SpawnZombie <Класс> [Количество]'],
            ['category' => 'npcs', 'cmd' => 'SpawnBrenner 1', 'icon' => 'flame', 'name' => 'SpawnBrenner', 'desc' => 'Спавн босса Brenner (огнеметчик)'],
            ['category' => 'npcs', 'cmd' => 'SpawnRazor 1', 'icon' => 'scissors', 'name' => 'SpawnRazor', 'desc' => 'Спавн прыгающего мутанта Razor'],
            ['category' => 'npcs', 'cmd' => 'DestroyZombiesWithinRadius 5000', 'icon' => 'trash-2', 'name' => 'DestroyZombies Radius', 'desc' => 'Уничтожить зомби в радиусе (см)'],
            ['category' => 'npcs', 'cmd' => 'DestroyArmedNPCsWithinRadius 5000', 'icon' => 'alert-octagon', 'name' => 'DestroyArmedNPCs Radius', 'desc' => 'Уничтожить роботов в радиусе (см)'],
            ['category' => 'npcs', 'cmd' => 'DestroyAllAnimalsWithinRadius 5000', 'icon' => 'x', 'name' => 'DestroyAnimals Radius', 'desc' => 'Уничтожить животных в радиусе (см)'],

            // 9. Спавн лута & Эвенты (spawn)
            ['category' => 'spawn', 'cmd' => 'SpawnItem Apple 5', 'icon' => 'package', 'name' => 'SpawnItem', 'desc' => 'SpawnItem <Класс> [Количество]'],
            ['category' => 'spawn', 'cmd' => 'SpawnInventoryFullOf BP_MetalChest 50 BPC_Ammo_7_62x39mm', 'icon' => 'archive', 'name' => 'SpawnInventoryFullOf', 'desc' => 'Спавн ящика, заполненного указанным лутом'],
            ['category' => 'spawn', 'cmd' => 'ScheduleCargoDrop', 'icon' => 'box', 'name' => 'ScheduleCargoDrop', 'desc' => 'Вызвать падение груза Airdrop'],
            ['category' => 'spawn', 'cmd' => 'ScheduleWorldEvent BP_CargoDropEvent 0 0 0', 'icon' => 'cloud-snow', 'name' => 'ScheduleWorldEvent', 'desc' => 'Запустить мировое событие по координатам'],
            ['category' => 'spawn', 'cmd' => 'DestroyAllItemsWithinRadius 500', 'icon' => 'trash', 'name' => 'DestroyAllItems Radius', 'desc' => 'Удалить все предметы на земле в радиусе (см)'],

            // 10. Сквады & Квесты (squads)
            ['category' => 'squads', 'cmd' => 'ListSquads', 'icon' => 'shield', 'name' => 'ListSquads', 'desc' => 'Список всех зарегистрированных сквадов'],
            ['category' => 'squads', 'cmd' => 'ListSquadMembers ', 'icon' => 'users', 'name' => 'ListSquadMembers', 'desc' => 'Список участников сквада по SquadId'],
            ['category' => 'squads', 'cmd' => 'DumpAllSquadsInfoList', 'icon' => 'file-text', 'name' => 'DumpAllSquadsInfoList', 'desc' => 'Полная выгрузка информации по всем сквадам'],
            ['category' => 'squads', 'cmd' => 'ExportQuests', 'icon' => 'download', 'name' => 'ExportQuests', 'desc' => 'Выгрузка данных квестов сервера'],
        ];
    @endphp

    <div x-data="rconTerm(@js($boot), '{{ route('rcon.send') }}', '{{ url('/') }}/rcon/job/')" class="space-y-6">

        {{-- ===== 1. HERO HEADER ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d]/80 bg-gradient-to-br from-[#101623] via-[#0d121c] to-[#080c13] p-5 sm:p-7 shadow-2xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-20 w-72 h-72 rounded-full bg-[#2ea043]/10 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-14 h-14 shrink-0 rounded-2xl bg-gradient-to-br from-[#1f6feb] via-[#388bfd] to-[#238636] p-[2px] shadow-lg shadow-[#1f6feb]/25">
                        <div class="w-full h-full rounded-[14px] bg-[#0d1117] flex items-center justify-center text-[#58a6ff]">
                            <x-icon name="terminal" class="w-7 h-7" />
                        </div>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-[#e6edf3] tracking-tight">
                                RCON Консоль управления
                            </h1>
                            <span class="badge badge-blue">Interactive CLI</span>
                        </div>
                        <p class="text-xs sm:text-sm text-[#8b949e]">
                            Прямой защищённый шлюз выполнения игровых команд SCUM в реальном времени
                        </p>
                        <div class="flex flex-wrap items-center gap-2.5 mt-2.5 text-xs">
                            {{-- Бейдж статуса подключения --}}
                            @if ($isConnected)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#2ea043]/15 border border-[#2ea043]/40 text-[#3fb950] font-semibold shadow-sm">
                                    <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-pulse"></span>
                                    RCON Шлюз подключен
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f85149]/15 border border-[#f85149]/40 text-[#f85149] font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-[#f85149]"></span>
                                    RCON Шлюз отключен
                                </span>
                            @endif

                            @if (!empty($currentServer))
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#21262d] border border-[#30363d] text-[#8b949e]">
                                    <x-icon name="server" class="w-3.5 h-3.5" />
                                    <span class="text-[#e6edf3] font-medium">{{ $currentServer['name'] ?? 'Сервер' }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0 pt-2 lg:pt-0">
                    <button type="button" @click="spawnerOpen = true"
                            class="btn btn-sm btn-primary px-3.5 py-2 text-xs font-bold flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/25">
                        <x-icon name="package" class="w-4 h-4" />
                        <span>Каталог спавна</span>
                    </button>
                    <button type="button" @click="copyTerminal()"
                            class="btn btn-sm px-3.5 py-2 text-xs text-[#8b949e] hover:text-[#e6edf3] flex items-center gap-1.5" title="Скопировать весь лог консоли">
                        <x-icon name="copy" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Копировать лог</span>
                    </button>
                    <button type="button" @click="clear()"
                            class="btn btn-sm px-3.5 py-2 text-xs text-[#8b949e] hover:text-[#e6edf3] flex items-center gap-1.5" title="Очистить экран">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                        <span>Очистить</span>
                    </button>
                    <a href="{{ route('servers.index') }}"
                       class="btn btn-sm px-3.5 py-2 text-xs text-[#8b949e] hover:text-[#58a6ff] hover:border-[#388bfd]/40 flex items-center gap-1.5">
                        <x-icon name="settings" class="w-3.5 h-3.5" />
                        <span>Настройки</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ===== 2. БЫСТРЫЕ ЧИПСЫ КОМАНД (TOP PILLS) ===== --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <span class="text-xs font-bold text-[#8b949e] uppercase tracking-wider shrink-0 flex items-center gap-1">
                <x-icon name="sparkles" class="w-3.5 h-3.5 text-[#58a6ff]" /> Популярные:
            </span>
            <button type="button" @click="insert('ListPlayers')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="users" class="w-3.5 h-3.5" />
                <span>ListPlayers</span>
            </button>
            <button type="button" @click="insert('ListSpawnedVehicles')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="truck" class="w-3.5 h-3.5" />
                <span>ListSpawnedVehicles</span>
            </button>
            <button type="button" @click="insert('ListSquads')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="shield" class="w-3.5 h-3.5" />
                <span>ListSquads</span>
            </button>
            <button type="button" @click="insert('ListFlags')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="flag" class="w-3.5 h-3.5" />
                <span>ListFlags</span>
            </button>
            <button type="button" @click="insert('ListCommands')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="terminal" class="w-3.5 h-3.5" />
                <span>ListCommands</span>
            </button>
            <button type="button" @click="insert('ScheduleCargoDrop')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="box" class="w-3.5 h-3.5" />
                <span>CargoDrop</span>
            </button>
            <button type="button" @click="insert('Whois ')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="search" class="w-3.5 h-3.5" />
                <span>Whois</span>
            </button>
            <button type="button" @click="insert('CheckServerTime')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="clock" class="w-3.5 h-3.5" />
                <span>CheckServerTime</span>
            </button>
            <button type="button" @click="insert('Announce ')"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-[#121824] hover:bg-[#1f6feb]/20 border border-[#30363d] hover:border-[#388bfd]/40 text-[#e6edf3] hover:text-[#58a6ff] transition-all shrink-0 inline-flex items-center gap-1.5 cursor-pointer">
                <x-icon name="megaphone" class="w-3.5 h-3.5" />
                <span>Announce</span>
            </button>
        </div>

        {{-- ===== 3. ГЛАВНАЯ СЕТКА: ТЕРМИНАЛ + ПАНЕЛЬ БЫСТРЫХ КОМАНД ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">

            {{-- 3.1 ТЕРМИНАЛ (8 колонок на Desktop, 12 на Mobile) --}}
            <div class="lg:col-span-7 xl:col-span-8 flex flex-col rounded-2xl border border-[#30363d]/90 bg-[#0d1117] shadow-2xl overflow-hidden">
                {{-- Заголовок терминала --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#21262d] bg-[#161b22]/90 backdrop-blur-md select-none">
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1.5 mr-2">
                            <span class="w-3 h-3 rounded-full bg-[#f85149] inline-block shadow-sm"></span>
                            <span class="w-3 h-3 rounded-full bg-[#d29922] inline-block shadow-sm"></span>
                            <span class="w-3 h-3 rounded-full bg-[#3fb950] inline-block shadow-sm"></span>
                        </div>
                        <div class="flex items-center gap-2 font-mono text-xs text-[#8b949e]">
                            <span class="text-[#3fb950] font-semibold">●</span>
                            <span class="text-[#e6edf3] font-medium">rcon@scum-gateway</span>
                            <span>~ CLI</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="font-mono text-[#3fb950] text-[11px] px-2 py-0.5 rounded bg-[#3fb950]/10 border border-[#3fb950]/30 font-semibold"
                              x-show="connected">ONLINE</span>
                        <span class="font-mono text-[#f85149] text-[11px] px-2 py-0.5 rounded bg-[#f85149]/10 border border-[#f85149]/30 font-semibold"
                              x-show="!connected">OFFLINE</span>
                        <span class="font-mono text-[#8b949e] text-[11px]" x-text="clock"></span>
                    </div>
                </div>

                {{-- Область вывода сообщений терминала --}}
                <div class="p-4 sm:p-5 font-mono text-xs sm:text-[13px] leading-relaxed flex-1 space-y-2 min-h-[360px] sm:min-h-[460px] max-h-[560px] overflow-y-auto bg-[#070a0f] custom-scrollbar selection:bg-[#1f6feb]/30"
                     x-ref="output">
                    <template x-for="(line, i) in lines" :key="i">
                        <div x-html="renderLine(line)"></div>
                    </template>
                </div>

                {{-- Строка ввода команды (CLI Prompt) --}}
                <form class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-3 sm:p-4 border-t border-[#21262d] bg-[#0b0f17]" @submit.prevent="submit()">
                    <div class="flex items-center gap-2 flex-1 bg-[#121824] border border-[#30363d] focus-within:border-[#58a6ff] rounded-xl px-3 py-2 transition-all">
                        <span class="font-mono shrink-0 font-extrabold text-sm text-[#58a6ff] select-none">scum$</span>
                        <input x-ref="input" x-model="cmd" type="text"
                               class="w-full bg-transparent border-none outline-none font-mono text-xs sm:text-sm text-[#e6edf3] placeholder-[#6e7681]"
                               style="caret-color:#58a6ff"
                               placeholder="Введите RCON команду… (например: ListPlayers, ListSpawnedVehicles, Whois)"
                               autocomplete="off" spellcheck="false"
                               :disabled="busy"
                               @keydown.enter.prevent="submit()"
                               @keydown.arrow-up.prevent="nav(-1)"
                               @keydown.arrow-down.prevent="nav(1)">
                        <button type="button" x-show="cmd" @click="cmd = ''" class="p-1 text-[#8b949e] hover:text-[#e6edf3]" title="Очистить"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="submit"
                                class="btn btn-accent px-5 py-2.5 text-xs sm:text-sm font-semibold shadow-lg shadow-[#1f6feb]/20 flex items-center justify-center gap-2 flex-1 sm:flex-initial cursor-pointer"
                                :disabled="busy || !cmd.trim()">
                            <template x-if="busy">
                                <span class="inline-block w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            </template>
                            <template x-if="!busy">
                                <span class="flex items-center gap-1.5">Выполнить <kbd class="px-1.5 py-0.5 rounded bg-[#21262d] text-[10px] font-mono text-[#8b949e]">↵</kbd></span>
                            </template>
                        </button>
                    </div>
                </form>

                {{-- Подсказка горячих клавиш --}}
                <div class="px-4 py-2 border-t border-[#1b222d] bg-[#070a0f] flex items-center justify-between text-[11px] text-[#6e7681]">
                    <div class="flex items-center gap-3">
                        <span><kbd class="px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[#8b949e] inline-flex items-center justify-center"><x-icon name="chevron-up" class="w-2.5 h-2.5" /></kbd> <kbd class="px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[#8b949e] inline-flex items-center justify-center"><x-icon name="chevron-down" class="w-2.5 h-2.5" /></kbd> история</span>
                        <span><kbd class="px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[#8b949e]">Enter</kbd> отправка</span>
                    </div>
                    <div>
                        <span class="text-[#8b949e]">Команды:</span> <span class="text-[#58a6ff] cursor-pointer hover:underline" @click="insert('ListCommands')">ListCommands</span>, <span class="text-[#58a6ff] cursor-pointer hover:underline" @click="clear()">clear</span>
                    </div>
                </div>
            </div>

            {{-- 3.2 ПАНЕЛЬ БЫСТРЫХ КОМАНД & ШПАРГАЛКА (5 колонок на Desktop, 12 на Mobile) --}}
            <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-4">

                {{-- Карточка с быстрыми командами --}}
                <div class="panel p-4 sm:p-5 flex flex-col rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl"
                     x-data="{
                         cmdSearch: '',
                         activeTab: 'all',
                         matches(cmd, name, desc) {
                             if (!this.cmdSearch || !this.cmdSearch.trim()) return true;
                             const q = this.cmdSearch.toLowerCase();
                             return (cmd + ' ' + name + ' ' + desc).toLowerCase().includes(q);
                         }
                     }">
                    <div class="flex items-center justify-between gap-2 mb-3 pb-3 border-b border-[#21262d]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]"><x-icon name="zap" class="w-4 h-4" /></div>
                            <div>
                                <h2 class="font-bold text-sm text-[#e6edf3]">Быстрые команды</h2>
                                <p class="text-[11px] text-[#8b949e]">Кликните, чтобы вставить в консоль</p>
                            </div>
                        </div>
                    </div>

                    {{-- Поиск по быстрым командам --}}
                    <div class="relative mb-3">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#8b949e]"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                        <input type="text" x-model="cmdSearch" placeholder="Фильтр команд (например: ListPlayers, Spawn, Teleport)..."
                               class="input pl-8 py-1.5 text-xs bg-[#0d1117] border-[#30363d] rounded-lg">
                        <button type="button" x-show="cmdSearch" @click="cmdSearch = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-[#8b949e] hover:text-[#e6edf3] cursor-pointer"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>

                    {{-- Категории команд --}}
                    <div class="flex items-center gap-1 overflow-x-auto pb-2 mb-2 no-scrollbar">
                        @foreach ($quickCategories as $catKey => $catLabel)
                            <button type="button" @click="activeTab = '{{ $catKey }}'"
                                    :class="activeTab === '{{ $catKey }}' ? 'bg-[#1f6feb]/25 text-[#58a6ff] border-[#388bfd]/40 font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                                    class="px-2.5 py-1 rounded-lg text-xs border whitespace-nowrap transition-all cursor-pointer">
                                {{ $catLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Список команд --}}
                    <div class="flex flex-col gap-2 max-h-[380px] overflow-y-auto pr-1 custom-scrollbar">
                        @foreach ($quickCommands as $item)
                            <div x-show="(activeTab === 'all' || activeTab === '{{ $item['category'] }}') && matches(@js($item['cmd']), @js($item['name']), @js($item['desc']))"
                                 class="p-2.5 rounded-xl bg-[#0d1117]/80 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all flex items-center justify-between gap-2 group cursor-pointer"
                                 @click="insert(@js($item['cmd']))">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#8b949e] group-hover:text-[#58a6ff] group-hover:border-[#388bfd]/40 shrink-0">
                                        <x-icon :name="$item['icon']" class="w-4 h-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors truncate">
                                            {{ $item['name'] }}
                                        </div>
                                        <div class="text-[11px] font-mono text-[#8b949e] truncate">
                                            {{ $item['cmd'] }}
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-xs px-2 py-1 text-[11px] text-[#8b949e] group-hover:text-[#58a6ff] group-hover:border-[#388bfd]/30 shrink-0 cursor-pointer"
                                        title="Вставить команду в консоль">
                                    Вставить
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Шпаргалка синтаксиса --}}
                <div class="panel p-4 sm:p-5 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl space-y-3">
                    <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-[#21262d]">
                        <div class="flex items-center gap-2">
                            <x-icon name="book-open" class="w-4 h-4 text-[#58a6ff]" />
                            <div>
                                <h2 class="font-bold text-sm text-[#e6edf3]">Шпаргалка параметров</h2>
                                <p class="text-[11px] text-[#8b949e]">Кликните на шаблон, чтобы скопировать в строку ввода</p>
                            </div>
                        </div>
                        <span class="text-[10px] uppercase font-bold text-[#8b949e] px-2 py-0.5 rounded bg-[#161b22] border border-[#30363d]">SCUM CLI</span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        {{-- 1. Whois --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('Whois ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    Whois <span class="text-[#58a6ff] font-normal">&lt;SteamID&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Информация об игроке, локация, статус</div>
                        </div>

                        {{-- 2. Teleport --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('Teleport 0 0 0 ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    Teleport <span class="text-[#d2a8ff] font-normal">X Y Z</span> <span class="text-[#58a6ff] font-normal">&lt;SteamID&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Телепортировать игрока по мировым координатам</div>
                        </div>

                        {{-- 3. TeleportTo --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('TeleportTo ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    TeleportTo <span class="text-[#58a6ff] font-normal">&lt;SteamID_1&gt;</span> <span class="text-[#7ee787] font-normal">&lt;SteamID_2&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Телепортировать одного игрока к другому</div>
                        </div>

                        {{-- 4. Announce --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('Announce ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    Announce <span class="text-[#a5d6ff] font-normal">&lt;Текст объявления&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Глобальное системное оповещение по центру экрана</div>
                        </div>

                        {{-- 5. SendNotification --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('SendNotification 1 \"Привет!\" ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    SendNotification <span class="text-[#e6edf3] font-normal">1</span> <span class="text-[#a5d6ff] font-normal">"Текст"</span> <span class="text-[#58a6ff] font-normal">&lt;SteamID&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Отправить личное системное уведомление игроку</div>
                        </div>

                        {{-- 6. SpawnItem --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('SpawnItem Apple 5')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    SpawnItem <span class="text-[#7ee787] font-normal">&lt;Class&gt;</span> <span class="text-[#e6edf3] font-normal">&lt;Count&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Выдать предмет в указанном количестве</div>
                        </div>

                        {{-- 7. SpawnVehicle --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('SpawnVehicle BPC_Laika')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    SpawnVehicle <span class="text-[#7ee787] font-normal">&lt;Class&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Спавн транспорта по названию класса</div>
                        </div>

                        {{-- 8. ChangeCurrencyBalance --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('ChangeCurrencyBalance Cash 1000 ')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    ChangeCurrencyBalance <span class="text-[#f2cc60] font-normal">Cash</span> <span class="text-[#7ee787] font-normal">&lt;Сумма&gt;</span> <span class="text-[#58a6ff] font-normal">&lt;SteamID&gt;</span>
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Начислить баланс валюты игроку</div>
                        </div>

                        {{-- 9. ScheduleCargoDrop --}}
                        <div class="p-2.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] hover:border-[#388bfd]/50 hover:bg-[#141d2e]/80 transition-all cursor-pointer group flex flex-col gap-1"
                             @click="insert('ScheduleCargoDrop')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-xs font-bold text-[#f0883e] group-hover:text-[#58a6ff] transition-colors">
                                    ScheduleCargoDrop
                                </div>
                                <span class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]">Вставить</span>
                            </div>
                            <div class="text-[11px] text-[#8b949e]">Запустить сброс контейнера Airdrop на карту</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ===== 4. ИСТОРИЯ ВЫПОЛНЕННЫХ КОМАНД ===== --}}
        <div class="panel p-4 sm:p-6 rounded-2xl border border-[#30363d]/80 bg-[#121824]/90 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-[#21262d]">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]"><x-icon name="file-text" class="w-4 h-4" /></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-sm sm:text-base text-[#e6edf3]">Журнал RCON команд</h2>
                            <span class="badge badge-gray text-xs">Всего: {{ $history->total() }}</span>
                        </div>
                        <p class="text-xs text-[#8b949e]">История отправки команд через веб-консоль и демоны</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-sm px-3.5 py-1.5 text-xs flex items-center gap-1.5 cursor-pointer" @click="window.location.reload()">
                        <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Обновить</span>
                    </button>
                </div>
            </div>

            {{-- Карточки на мобильных устройствах --}}
            <div class="block md:hidden space-y-3">
                @forelse ($history as $c)
                    <div class="p-3.5 rounded-xl bg-[#0d1117]/90 border border-[#21262d] space-y-2">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-[#8b949e]">{{ Carbon::parse($c->created_at)->format('d.m.Y H:i:s') }}</span>
                            @if ($c->error)
                                <span class="badge badge-red text-[10px] inline-flex items-center gap-1"><x-icon name="x" class="w-2.5 h-2.5" /><span>Ошибка</span></span>
                            @else
                                <span class="badge badge-green text-[10px] flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#3fb950]"></span> OK</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2 bg-[#161b22] p-2 rounded-lg border border-[#30363d]/60 font-mono text-xs text-[#e6edf3]">
                            <span class="truncate font-semibold text-[#58a6ff]">{{ $c->command }}</span>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="copyText(@js($c->command))" class="btn btn-xs px-1.5 py-0.5 cursor-pointer" title="Скопировать"><x-icon name="copy" class="w-3 h-3" /></button>
                                <button type="button" @click="reRun(@js($c->command))" class="btn btn-xs btn-accent px-1.5 py-0.5 flex items-center justify-center cursor-pointer" title="Повторить"><x-icon name="play" class="w-3 h-3" /></button>
                            </div>
                        </div>
                        @if ($c->response)
                            <div class="text-xs font-mono text-[#8b949e] bg-[#0b0f17] p-2 rounded border border-[#21262d] truncate cursor-pointer hover:border-[#388bfd]/30"
                                 @click="showModal('Ответ: ' + @js($c->command), @js($c->response))">
                                <span class="text-[#3fb950]">&gt;</span> {{ $c->response }}
                            </div>
                        @elseif ($c->error)
                            <div class="text-xs font-mono text-[#f85149] bg-[#f85149]/10 p-2 rounded border border-[#f85149]/20">
                                {{ $c->error }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-8 text-[#8b949e] text-xs">
                        Команды ещё не выполнялись.
                    </div>
                @endforelse
            </div>

            {{-- Таблица на планшетах и десктопах --}}
            <div class="hidden md:block overflow-x-auto rounded-xl border border-[#21262d]">
                <table class="table w-full">
                    <thead>
                        <tr class="bg-[#0e131f]">
                            <th class="py-3 px-4 text-xs">Время</th>
                            <th class="py-3 px-4 text-xs">Команда</th>
                            <th class="py-3 px-4 text-xs">Ответ сервера</th>
                            <th class="py-3 px-4 text-xs">Статус</th>
                            <th class="py-3 px-4 text-xs text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#21262d] bg-[#0d1117]/80">
                        @forelse ($history as $c)
                            <tr class="hover:bg-[#161d2d] transition-colors">
                                <td class="py-3 px-4 text-xs text-[#8b949e] whitespace-nowrap">
                                    {{ Carbon::parse($c->created_at)->format('d.m H:i:s') }}
                                </td>
                                <td class="py-3 px-4 font-mono text-xs font-semibold text-[#e6edf3] max-w-[220px] truncate" title="{{ $c->command }}">
                                    <span class="text-[#58a6ff]">scum$</span> {{ $c->command }}
                                </td>
                                <td class="py-3 px-4 font-mono text-xs text-[#8b949e] max-w-sm truncate" title="{{ $c->response ?? $c->error ?? '' }}">
                                    @if ($c->error)
                                        <span class="text-[#f85149]">{{ $c->error }}</span>
                                    @elseif ($c->response)
                                        <span class="text-[#3fb950] cursor-pointer hover:underline" @click="showModal('Ответ: ' + @js($c->command), @js($c->response))">
                                            {{ $c->response }}
                                        </span>
                                    @else
                                        <span class="text-[#6e7681]">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if ($c->error)
                                        <span class="badge badge-red text-[10px]">Ошибка</span>
                                    @else
                                        <span class="badge badge-green text-[10px]">OK</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" @click="copyText(@js($c->command))" class="btn btn-xs px-2 py-1 cursor-pointer" title="Скопировать команду"><x-icon name="copy" class="w-3 h-3" /></button>
                                        <button type="button" @click="reRun(@js($c->command))" class="btn btn-xs btn-accent px-2 py-1 flex items-center justify-center cursor-pointer" title="Выполнить снова">
                                            <x-icon name="play" class="w-3 h-3" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-sm text-[#8b949e] text-center py-8">
                                    Команды ещё не отправлялись.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($history->hasPages())
                <div class="mt-4 flex justify-center">
                    {{ $history->links('vendor.pagination.panel') }}
                </div>
            @endif
        </div>

        {{-- ===== 5. МОДАЛЬНОЕ ОКНО ПРОСМОТРА ПОЛНОГО ОТВЕТА ===== --}}
        <div x-show="responseModalOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(2, 6, 12, 0.85); backdrop-filter:blur(8px)"
             @keydown.escape.window="closeModal()">
            <div class="relative w-full max-w-2xl rounded-2xl border border-[#30363d] bg-[#121824] shadow-2xl overflow-hidden flex flex-col max-h-[85vh]" @click.stop>
                <div class="flex items-center justify-between px-5 py-4 border-b border-[#21262d] bg-[#0e131f]">
                    <div class="flex items-center gap-2.5">
                        <x-icon name="message-square" class="w-4 h-4 text-[#58a6ff]" />
                        <h3 class="font-bold text-sm sm:text-base text-[#e6edf3] truncate" x-text="modalTitle">Ответ сервера</h3>
                    </div>
                    <button type="button" @click="closeModal()" class="p-1 text-[#8b949e] hover:text-[#e6edf3] cursor-pointer"><x-icon name="x" class="w-4 h-4" /></button>
                </div>
                <div class="p-5 overflow-y-auto font-mono text-xs text-[#3fb950] bg-[#070a0f] whitespace-pre-wrap break-all leading-relaxed flex-1 custom-scrollbar"
                     x-text="modalContent"></div>
                <div class="flex items-center justify-between px-5 py-3 border-t border-[#21262d] bg-[#0e131f]">
                    <button type="button" @click="copyText(modalContent)" class="btn btn-sm btn-accent text-xs inline-flex items-center gap-1.5 cursor-pointer"><x-icon name="copy" class="w-3.5 h-3.5" /><span>Копировать ответ</span></button>
                    <button type="button" @click="closeModal()" class="btn btn-sm text-xs cursor-pointer">Закрыть</button>
                </div>
            </div>
        </div>

        {{-- ===== 6. МОДАЛЬНОЕ ОКНО: КАТАЛОГ СПАВНА И КОНСТРУКТОР КОМАНД ===== --}}
        <div x-show="spawnerOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm"
             @keydown.escape.window="spawnerOpen = false">
            <div class="relative w-full max-w-xl flex flex-col rounded-2xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-visible"
                 @click.outside="spawnerOpen = false">
                
                <!-- Шапка модалки -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-[#30363d] bg-[#0d1117] rounded-t-2xl">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#1f6feb]/20 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]">
                            <x-icon name="package" class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-[#e6edf3] text-sm sm:text-base leading-tight">Интерактивный спавнер SCUM</h3>
                            <p class="text-[11px] text-[#8b949e]">Выбор предмета, техники, существа или локации из каталога</p>
                        </div>
                    </div>
                    <button type="button" @click="spawnerOpen = false" class="w-8 h-8 rounded-lg bg-[#21262d] text-[#8b949e] hover:text-[#e6edf3] flex items-center justify-center cursor-pointer">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Тело модалки -->
                <div class="p-5 space-y-4">
                    <!-- Выбор типа операции -->
                    <div class="grid grid-cols-4 gap-1.5 p-1 rounded-xl bg-[#0d1117] border border-[#21262d]">
                        <button type="button" @click="spawnerType = 'item'; spawnerItem = ''"
                                :class="spawnerType === 'item' ? 'bg-[#388bfd] text-white shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                                class="py-2 text-xs font-semibold rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                            <x-icon name="box" class="w-3.5 h-3.5" />
                            <span>Предмет</span>
                        </button>
                        <button type="button" @click="spawnerType = 'vehicle'; spawnerItem = ''"
                                :class="spawnerType === 'vehicle' ? 'bg-[#388bfd] text-white shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                                class="py-2 text-xs font-semibold rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                            <x-icon name="truck" class="w-3.5 h-3.5" />
                            <span>Транспорт</span>
                        </button>
                        <button type="button" @click="spawnerType = 'npc'; spawnerItem = ''"
                                :class="spawnerType === 'npc' ? 'bg-[#388bfd] text-white shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                                class="py-2 text-xs font-semibold rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                            <x-icon name="target" class="w-3.5 h-3.5" />
                            <span>Существа</span>
                        </button>
                        <button type="button" @click="spawnerType = 'marker'; spawnerItem = ''"
                                :class="spawnerType === 'marker' ? 'bg-[#388bfd] text-white shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'"
                                class="py-2 text-xs font-semibold rounded-lg transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                            <x-icon name="map-pin" class="w-3.5 h-3.5" />
                            <span>Телепорт</span>
                        </button>
                    </div>

                    <!-- Селектор каталога с динамическим типом -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-[#8b949e]">
                            <span x-show="spawnerType === 'item'">Выберите предмет</span>
                            <span x-show="spawnerType === 'vehicle'">Выберите транспорт</span>
                            <span x-show="spawnerType === 'npc'">Выберите NPC или животное</span>
                            <span x-show="spawnerType === 'marker'">Выберите точку карты (POI)</span>
                        </label>

                        <div x-show="spawnerType === 'item'" @catalog-selected.stop="spawnerItem = $event.detail.item.id">
                            <x-catalog-picker type="item" name="item" placeholder="Поиск среди 2 438 предметов..." />
                        </div>
                        <div x-show="spawnerType === 'vehicle'" @catalog-selected.stop="spawnerItem = $event.detail.item.id">
                            <x-catalog-picker type="vehicle" name="vehicle" placeholder="Поиск авто, мотоцикла, лодки..." />
                        </div>
                        <div x-show="spawnerType === 'npc'" @catalog-selected.stop="spawnerItem = $event.detail.item.id">
                            <x-catalog-picker type="npc" name="npc" placeholder="Поиск существа, животного, зомби..." />
                        </div>
                        <div x-show="spawnerType === 'marker'" @catalog-selected.stop="spawnerItem = $event.detail.item.command">
                            <x-catalog-picker type="marker" name="marker" placeholder="Поиск бункера, пещеры, банкомата..." />
                        </div>
                    </div>

                    <!-- Дополнительные параметры -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="spawnerType !== 'marker'">
                        <div x-show="spawnerType === 'item' || spawnerType === 'npc'">
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Количество</label>
                            <input type="number" x-model.number="spawnerAmount" min="1" max="100" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Локация / Координаты (Опционально)</label>
                            <input type="text" x-model="spawnerTarget" placeholder='Например: 0 0 0 или пусто' class="input mono w-full">
                        </div>
                    </div>

                    <!-- Превью сгенерированной команды -->
                    <div class="p-3 rounded-xl bg-[#0d1117] border border-[#21262d] space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-[#8b949e]">Сгенерированная команда RCON:</div>
                        <div class="mono text-xs text-[#58a6ff] break-all select-all font-semibold"
                             x-text="getSpawnerCommand() || '(Выберите объект из каталога выше)'"></div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button type="button" @click="spawnerOpen = false" class="btn btn-sm text-xs cursor-pointer">
                            Отмена
                        </button>
                        <button type="button" @click="insertSpawnerCmd()"
                                :disabled="!spawnerItem"
                                class="btn btn-sm btn-secondary text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer">
                            <x-icon name="terminal" class="w-3.5 h-3.5" />
                            <span>Вставить в консоль</span>
                        </button>
                        <button type="button" @click="executeSpawnerCmd()"
                                :disabled="!spawnerItem"
                                class="btn btn-sm btn-primary text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer">
                            <x-icon name="zap" class="w-3.5 h-3.5" />
                            <span>Выполнить в RCON</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
