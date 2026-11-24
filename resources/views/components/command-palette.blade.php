<div x-data="{
    search: '',
    selectedIndex: 0,
    items: [
        // Навигация по страницам
        { name: 'Дашборд серверов & Host Health', category: 'Мониторинг', keywords: 'сервер онлайн нагрузка аптайм cpu ram диск', url: '{{ route('servers.index') }}', icon: 'server' },
        { name: 'Игроки онлайн в реальном времени', category: 'Мониторинг', keywords: 'steam id игроки пользователи онлайн список сессии', url: '{{ route('players.index') }}', icon: 'users' },
        { name: 'Интерактивная карта острова SCUM', category: 'Мониторинг', keywords: 'карта секторы ящики дропы координаты gps', url: '{{ route('map.index') }}', icon: 'map' },
        { name: 'RCON Терминал и Консоль команд', category: 'Управление', keywords: 'консоль rcon терминал команды bash execute', url: '{{ route('rcon.index') }}', icon: 'terminal' },
        { name: 'База данных SCUM.db (SQLite)', category: 'Управление', keywords: 'база данных scumdb игроки транспорт флаги экономика', url: '{{ route('scumdb.index') }}', icon: 'database' },
        { name: 'Паки лута и наборы предметов', category: 'Управление', keywords: 'паки лут наборы оружия предметы экипировка', url: '{{ route('packs.index') }}', icon: 'package' },
        { name: 'Конфиги сервера (ServerSettings.ini)', category: 'Конфигурация', keywords: 'конфиг сервер ini settings economy ftp правка', url: '{{ route('config-files.index') }}', icon: 'settings' },
        { name: 'Автоматизация задач (Workflow Engine)', category: 'Управление', keywords: 'автоматизация крон триггеры расписание события', url: '{{ route('automation.index') }}', icon: 'cpu' },
        { name: 'Магазин WARGM & Автовыдача', category: 'Управление', keywords: 'варгм магазин покупки автовыдача товары shop id', url: '{{ route('wargm.index') }}', icon: 'shopping-cart' },
        { name: 'VIP статус игроков и привилегии', category: 'Плагины', keywords: 'вип vip статус привилегии игроки список', url: '{{ route('plugins.vip') }}', icon: 'crown' },
        { name: 'Страхование транспорта игроков', category: 'Плагины', keywords: 'страховка авто машины полис возврат транспорт', url: '{{ route('plugins.insurance') }}', icon: 'shield' },
        { name: 'Кастомный Airdrop и автодроп', category: 'Плагины', keywords: 'аирдроп дроп сундук калибровка высот сброс', url: '{{ route('plugins.airdrop') }}', icon: 'parachute' },
        { name: 'SaveHome базы и точки сохранения', category: 'Плагины', keywords: 'дом база сохранить точку savehome телепорт', url: '{{ route('plugins.savehome') }}', icon: 'home' },
        { name: 'Телепорт точки и координаты', category: 'Плагины', keywords: 'телепорт warp координаты метки локации', url: '{{ route('plugins.teleport') }}', icon: 'compass' },
        { name: 'Награды и Топ игроков сервера', category: 'Плагины', keywords: 'награды топ слава киллы рейтинг бонусы', url: '{{ route('plugins.rewards') }}', icon: 'trophy' },
        { name: 'Логи внутриигрового чата', category: 'Логи', keywords: 'чат сообщения флуд мат игроки поиск лог', url: '{{ route('logs.chat') }}', icon: 'file-text' },
        { name: 'Логи игровых событий и действий', category: 'Логи', keywords: 'события логи серверов сбор realtime', url: '{{ route('logs.events') }}', icon: 'activity' },
        { name: 'Логи убийств (PVP / PVE киллы)', category: 'Логи', keywords: 'убийства пвп пве киллы смерть оружие дальность', url: '{{ route('logs.kill') }}', icon: 'file-text' },
        { name: 'Логи RCON команд администраторов', category: 'Логи', keywords: 'ркон аудит команды админы логирование', url: '{{ route('logs.rcon') }}', icon: 'terminal' },
        { name: 'Логи экономики, торговли и банков', category: 'Логи', keywords: 'экономика деньги золото банк трейд торговля', url: '{{ route('logs.economy') }}', icon: 'file-text' },
        { name: 'Логи входов и выходов игроков', category: 'Логи', keywords: 'вход выход коннект дисконнект сессии ip', url: '{{ route('logs.login') }}', icon: 'users' },
        { name: 'Безопасность: Белый список IP & 2FA', category: 'Безопасность', keywords: 'ip белый список безопасность 2fa totp сессии пароль', url: '{{ route('account.settings') }}', icon: 'shield' },

        // Быстрые RCON действия
        { name: 'RCON: Оповещение в чат (#announce)', category: 'RCON Действие', keywords: '> announce оповещение сообщение сервер чат объявление', url: '{{ route('rcon.index') }}?cmd=announce+', icon: 'terminal' },
        { name: 'RCON: Апгрейд базы до бетона (#upgradebase)', category: 'RCON Действие', keywords: '> upgradebase апгрейд базы бетон постройки delay', url: '{{ route('rcon.index') }}?cmd=UpgradeBase+', icon: 'terminal' },
        { name: 'RCON: Доставить авто к игроку (#bringvehicle)', category: 'RCON Действие', keywords: '> bringvehicle доставка авто эвакуация машина транспорт', url: '{{ route('rcon.index') }}?cmd=BringVehicle+', icon: 'terminal' },
        { name: 'RCON: Вытащить игрока из текстур (#unstuck)', category: 'RCON Действие', keywords: '> unstuck текстуры застрял спасение спасти', url: '{{ route('rcon.index') }}?cmd=Unstuck+', icon: 'terminal' },
        { name: 'RCON: Проверить зависшие квесты (#findquestlockouts)', category: 'RCON Действие', keywords: '> findquestlockouts квесты софтлок зависшие quest lockout', url: '{{ route('rcon.index') }}?cmd=FindQuestLockouts', icon: 'terminal' },
        { name: 'RCON: Массовый сброс квестов (#runquestunstick)', category: 'RCON Действие', keywords: '> runquestunstick сброс квестов разблокировка quest', url: '{{ route('rcon.index') }}?cmd=RunQuestUnstick', icon: 'terminal' },
        { name: 'RCON: Список всего авто (#listspawnedvehicles)', category: 'RCON Действие', keywords: '> listspawnedvehicles транспорт авто машины список', url: '{{ route('rcon.index') }}?cmd=ListSpawnedVehicles', icon: 'terminal' },
        { name: 'RCON: Кикнуть игрока (#kick)', category: 'RCON Действие', keywords: '> kick кикнуть выгнать игрок steamid', url: '{{ route('rcon.index') }}?cmd=kick+', icon: 'terminal' },
        { name: 'RCON: Установить время суток (#settime)', category: 'RCON Действие', keywords: '> settime время сутки день ночь час', url: '{{ route('rcon.index') }}?cmd=settime+', icon: 'terminal' },
        { name: 'RCON: Сменить погоду (#setweather)', category: 'RCON Действие', keywords: '> setweather погода дождь туман солнце ясно', url: '{{ route('rcon.index') }}?cmd=setweather+', icon: 'terminal' },
        { name: 'RCON: Перезапуск сервера (#restartserver)', category: 'RCON Действие', keywords: '> restartserver рестарт перезагрузка сервер таймер', url: '{{ route('rcon.index') }}?cmd=RestartServer+60', icon: 'terminal' },
    ],
    get filteredItems() {
        if (!this.search.trim()) return this.items.slice(0, 8);
        const q = this.search.toLowerCase().trim();
        return this.items.filter(i => 
            i.name.toLowerCase().includes(q) || 
            i.category.toLowerCase().includes(q) ||
            (i.keywords && i.keywords.toLowerCase().includes(q))
        );
    },
    navigate() {
        const item = this.filteredItems[this.selectedIndex];
        if (item) {
            $store.ui.closeCmdPalette();
            window.location.href = item.url;
        }
    }
}"
     x-show="$store.ui.cmdPaletteOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     @keydown.escape.window="$store.ui.closeCmdPalette()"
     @keydown.down.prevent="selectedIndex = (selectedIndex + 1) % (filteredItems.length || 1)"
     @keydown.up.prevent="selectedIndex = (selectedIndex - 1 + (filteredItems.length || 1)) % (filteredItems.length || 1)"
     @keydown.enter.prevent="navigate()"
     class="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4 bg-black/75 backdrop-blur-md"
     x-cloak>
    
    <div class="fixed inset-0" @click="$store.ui.closeCmdPalette()"></div>

    <div class="relative w-full max-w-xl bg-[#0c1017] border border-[#21262d] rounded-2xl shadow-2xl overflow-hidden z-10 flex flex-col max-h-[75vh]">
        
        {{-- Search Input Bar --}}
        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-[#21262d] bg-[#090d14]/90">
            <x-icon name="search" class="w-5 h-5 text-[#8b949e] shrink-0" />
            <input type="text"
                   x-ref="cmdInput"
                   x-model="search"
                   @input="selectedIndex = 0"
                   x-init="$watch('$store.ui.cmdPaletteOpen', value => { if (value) { selectedIndex = 0; setTimeout(() => $refs.cmdInput.focus(), 50) } })"
                   placeholder="Поиск по разделам, конфигам, логам и RCON командам... (Ctrl+K)"
                   class="w-full bg-transparent border-0 text-[#e6edf3] placeholder-[#8b949e] text-sm focus:outline-none focus:ring-0" />
            <span class="text-[10px] bg-[#161b22] text-[#8b949e] border border-[#30363d] px-1.5 py-0.5 rounded font-mono">ESC</span>
        </div>

        {{-- Results List --}}
        <div class="overflow-y-auto p-2 space-y-1 flex-1 custom-scrollbar">
            <template x-for="(item, index) in filteredItems" :key="item.url + item.name">
                <a :href="item.url"
                   @click="$store.ui.closeCmdPalette()"
                   @mouseenter="selectedIndex = index"
                   class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all"
                   :class="selectedIndex === index ? 'bg-[#1f6feb]/20 text-[#58a6ff] border border-[#58a6ff]/40 shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3] border border-transparent'">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-[#090d14] border border-[#21262d] flex items-center justify-center text-xs shrink-0"
                             :class="selectedIndex === index ? 'border-[#58a6ff]/60 text-[#58a6ff]' : 'text-[#8b949e]'">
                            <template x-if="item.category === 'Мониторинг'"><x-icon name="server" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'Управление'"><x-icon name="terminal" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'Конфигурация'"><x-icon name="settings" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'Плагины'"><x-icon name="sparkles" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'Логи'"><x-icon name="file-text" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'Безопасность'"><x-icon name="shield" class="w-4 h-4" /></template>
                            <template x-if="item.category === 'RCON Действие'"><x-icon name="zap" class="w-4 h-4 text-amber-400" /></template>
                        </div>
                        <div class="truncate">
                            <span class="font-medium" :class="selectedIndex === index ? 'text-[#58a6ff]' : 'text-[#e6edf3]'" x-text="item.name"></span>
                        </div>
                    </div>
                    <span class="text-[10px] bg-[#090d14] border border-[#21262d] px-2 py-0.5 rounded-full uppercase tracking-wider font-semibold shrink-0"
                          :class="selectedIndex === index ? 'text-[#58a6ff] border-[#58a6ff]/40' : 'text-[#8b949e]'"
                          x-text="item.category"></span>
                </a>
            </template>

            <div x-show="filteredItems.length === 0" class="text-center py-8 text-sm text-[#8b949e]">
                Ничего не найдено по запросу «<span x-text="search" class="text-[#e6edf3]"></span>»
            </div>
        </div>

        {{-- Bottom Help Bar --}}
        <div class="px-4 py-2 bg-[#090d14] border-t border-[#21262d] text-[11px] text-[#8b949e] flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span>Навигация: <kbd class="bg-[#161b22] px-1.5 py-0.5 rounded font-mono text-[10px] text-[#c9d1d9] inline-flex items-center justify-center"><x-icon name="chevron-up" class="w-2.5 h-2.5" /></kbd> <kbd class="bg-[#161b22] px-1.5 py-0.5 rounded font-mono text-[10px] text-[#c9d1d9] inline-flex items-center justify-center"><x-icon name="chevron-down" class="w-2.5 h-2.5" /></kbd></span>
                <span>Выбор: <kbd class="bg-[#161b22] px-1.5 py-0.5 rounded font-mono text-[10px] text-[#c9d1d9]">↵ Enter</kbd></span>
                <span>Закрыть: <kbd class="bg-[#161b22] px-1.5 py-0.5 rounded font-mono text-[10px] text-[#c9d1d9]">Esc</kbd></span>
            </div>
            <span class="text-[#3fb950] font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                Быстрый переход
            </span>
        </div>
    </div>
</div>
