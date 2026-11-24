/**
 * SCUM Config Editor - Интеллектуальный визуальный и текстовый редактор конфигураций сервера SCUM
 */

// База знаний настроек SCUM для понятного визуального отображения
const SCUM_INI_DICTIONARY = {
    // Секции
    _sections: {
        'General': { title: 'Основное и Сервер', icon: 'settings', desc: 'Название, пароли, приветствие и базовые параметры сервера' },
        'World': { title: 'Игровой мир и Время', icon: 'compass', desc: 'Время суток, скорость времени, аирдропы, прицел, компас и карта' },
        'Respawn': { title: 'Возрождение и Смерть', icon: 'skull', desc: 'Стоимость и таймеры респавна в секторе, убежище и у отряда' },
        'Vehicles': { title: 'Транспорт и Техника', icon: 'car', desc: 'Лимиты транспорта, расход топлива, разряд аккумуляторов и уничтожение' },
        'Damage': { title: 'Урон и PvP', icon: 'target', desc: 'Множители урона между игроками, по марионеткам и от мира' },
        'Puppets': { title: 'Марионетки (Зомби)', icon: '🧟', desc: 'Плотность спавна, орды зомби, подрывники и сила урона' },
        'Zombies': { title: 'Марионетки (Зомби)', icon: '🧟', desc: 'Плотность спавна, орды зомби, подрывники и сила урона' },
        'Sentinels': { title: 'Роботы (Сентинели)', icon: '🤖', desc: 'Включение, урон, здоровье и точность боевых роботов' },
        'Robots': { title: 'Роботы (Сентинели)', icon: '🤖', desc: 'Включение, урон, здоровье и точность боевых роботов' },
        'BaseBuilding': { title: 'Строительство и Флаги', icon: 'flag', desc: 'Разрешение построек, лимит элементов флага, захват и гниение' },
        'ItemSpawning': { title: 'Лут и Предметы', icon: 'box', desc: 'Множители спавна лута в мире, ящиках и таймеры респавна' },
        'Killbox': { title: 'Киллбоксы', icon: 'flame', desc: 'Таймеры дверей, газа и параметры комнат с лутом' },
        'Meteo': { title: 'Погода и Климат', icon: 'broadcast', desc: 'Частота дождей, ветра и динамическая смена погоды' },
        'Weather': { title: 'Погода и Климат', icon: 'broadcast', desc: 'Частота дождей, ветра и динамическая смена погоды' },
        'CustomSettings': { title: 'Дополнительные настройки', icon: 'sliders', desc: 'Пользовательские и специальные параметры' },
    },

    // Параметры
    _keys: {
        // [General]
        'ServerName': { title: 'Название сервера', desc: 'Отображается в общем списке серверов игры', type: 'string' },
        'ServerDescription': { title: 'Описание сервера', desc: 'Подробное описание в браузере серверов', type: 'multiline' },
        'ServerPassword': { title: 'Пароль сервера', desc: 'Оставьте пустым, если сервер публичный', type: 'string' },
        'ServerBannerUrl': { title: 'URL баннера сервера', desc: 'Прямая ссылка на картинку-баннер сервера', type: 'string' },
        'ServerPlaystyle': { title: 'Стиль игры', desc: 'Тип игрового процесса', type: 'select', options: ['PVP', 'PVE', 'PVE_PVP', 'RP', 'HARDCORE'] },
        'WelcomeMessage': { title: 'Приветственное сообщение', desc: 'Текст, показываемый игрокам при заходе на сервер', type: 'multiline' },
        'MessageOfTheDay': { title: 'Сообщение дня (MOTD)', desc: 'Информационное сообщение для игроков', type: 'multiline' },
        'MessageOfTheDayCooldown': { title: 'Интервал показа MOTD (сек)', desc: 'Как часто показывать сообщение дня', type: 'number', step: 60, min: 0 },
        'MinPing': { title: 'Минимальный пинг', desc: '0 — без ограничений', type: 'number', step: 10, min: 0 },
        'MaxPing': { title: 'Максимальный пинг', desc: 'Авто-кик при превышении пинга (0 = выключено)', type: 'number', step: 25, min: 0 },
        'LogoutTimer': { title: 'Таймер безопасного выхода (сек)', desc: 'Время ожидания персонажа при выходе из игры', type: 'number', step: 5, min: 0 },
        'LogoutTimerModifiersEnabled': { title: 'Модификаторы таймера выхода', desc: 'Увеличение времени выхода в бою или рядом с врагом', type: 'boolean' },
        'MasterServerMasterPort': { title: 'Порт мастер-сервера', desc: 'Служебный сетевой порт', type: 'number' },
        'MasterServerQueryPort': { title: 'Query порт', desc: 'Порт запросов состояния сервера', type: 'number' },

        // [World]
        'MaxAllowedCharactersInWorld': { title: 'Максимум игроков (слоты)', desc: 'Максимальное число игроков онлайн одновременно', type: 'number', step: 1, min: 1, max: 128 },
        'TimeOfDay': { title: 'Текущее время суток', desc: 'Формат ЧЧ:ММ:СС (например 12:00:00)', type: 'string' },
        'TimeOfDaySpeed': { title: 'Скорость течения времени', desc: '1.0 = стандартная скорость. 2.0 = в 2 раза быстрее', type: 'number', step: 0.1, min: 0.1 },
        'NightTimeSpeedMultiplier': { title: 'Множитель скорости ночи', desc: 'Ускорение времени ночью (1.0 = обычная ночь, 2.0 = в 2 раза короче)', type: 'number', step: 0.1, min: 0.1 },
        'CustomTimeOfDaySpeedMultiplier': { title: 'Пользовательский множитель времени', desc: 'Общий модификатор скорости игровых суток', type: 'number', step: 0.1 },
        'SunriseTime': { title: 'Время рассвета', desc: 'Формат времени восхода солнца (например 06:00:00)', type: 'string' },
        'SunsetTime': { title: 'Время заката', desc: 'Формат времени заката (например 20:00:00)', type: 'string' },
        'CargoDropCooldownMinimum': { title: 'Мин. интервал аирдропа (мин)', desc: 'Минимальное время между сбросами грузов', type: 'number', step: 5, min: 1 },
        'CargoDropCooldownMaximum': { title: 'Макс. интервал аирдропа (мин)', desc: 'Максимальное время между сбросами грузов', type: 'number', step: 5, min: 1 },
        'CargoDropFallDelay': { title: 'Задержка падения груза (сек)', desc: 'Время спуска аирдропа на парашюте', type: 'number', step: 10, min: 10 },
        'CargoDropDuration': { title: 'Время жизни аирдропа (сек)', desc: 'Сколько секунд ящик находится на земле до взрыва/исчезновения', type: 'number', step: 60, min: 60 },
        'EnableCargoDrops': { title: 'Включить аирдропы', desc: 'Периодический сброс ценных контейнеров с самолета', type: 'boolean' },
        'EnableAirDropAnnouncement': { title: 'Оповещение об аирдропе', desc: 'Звуковые и текстовые уведомления в игре', type: 'boolean' },
        'bAllowThirdPerson': { title: 'Вид от 3-го лица', desc: 'Разрешить переключение камеры от третьего лица', type: 'boolean' },
        'bAllowCrosshair': { title: 'Прицел на экране', desc: 'Отображение точки прицела без режима прицеливания', type: 'boolean' },
        'bAllowCompass': { title: 'Компас на экране', desc: 'Отображение шкалы компаса вверху экрана', type: 'boolean' },
        'bAllowMap': { title: 'Игровая карта', desc: 'Разрешить открывать карту острова в игре', type: 'boolean' },
        'bAllowKillFeed': { title: 'Килл-фид (оповещения об убийствах)', desc: 'Показ сообщений кто кого убил в углу экрана', type: 'boolean' },
        'bAllowGlobalChat': { title: 'Глобальный чат', desc: 'Общение между всеми игроками на сервере', type: 'boolean' },
        'bAllowLocalChat': { title: 'Локальный чат', desc: 'Общение в радиусе слышимости персонажа', type: 'boolean' },
        'bAllowSquadChat': { title: 'Чат отряда', desc: 'Внутриотрядный приватный чат', type: 'boolean' },
        'bAllowAdminChat': { title: 'Чат администратора', desc: 'Выделенный канал общения для админов', type: 'boolean' },

        // [Respawn]
        'RandomLocationPrice': { title: 'Цена случайного возрождения', desc: 'Стоимость респавна в случайном месте ($ или слава)', type: 'number', step: 50, min: 0 },
        'SectorPrice': { title: 'Цена возрождения в секторе', desc: 'Стоимость выбора конкретного сектора', type: 'number', step: 100, min: 0 },
        'ShelterPrice': { title: 'Цена возрождения в убежище/базе', desc: 'Стоимость респавна на кровати/шелтере', type: 'number', step: 100, min: 0 },
        'SquadPrice': { title: 'Цена возрождения у отряда', desc: 'Стоимость респавна возле тиммейта', type: 'number', step: 100, min: 0 },
        'RandomInitialTime': { title: 'Задержка случайного респавна (сек)', desc: 'Начальный таймер перед возрождением', type: 'number', step: 5, min: 0 },
        'SectorInitialTime': { title: 'Задержка респавна в секторе (сек)', desc: 'Начальный таймер выбора сектора', type: 'number', step: 5, min: 0 },
        'ShelterInitialTime': { title: 'Задержка респавна в убежище (сек)', desc: 'Начальный таймер респавна на кровати', type: 'number', step: 5, min: 0 },
        'SquadInitialTime': { title: 'Задержка респавна у отряда (сек)', desc: 'Начальный таймер респавна у тиммейта', type: 'number', step: 5, min: 0 },
        'Permadeath': { title: 'Перманентная смерть', desc: 'При смерти персонаж удаляется навсегда', type: 'boolean' },
        'DisableRespawn': { title: 'Отключить возрождение', desc: 'Полный запрет респавна после смерти', type: 'boolean' },
        'ResetKillstreakOnDeath': { title: 'Сброс серии убийств при смерти', desc: 'Обнуление счетчика киллстрика', type: 'boolean' },
        'RespawnPriceMultiplier': { title: 'Множитель цен респавна', desc: 'Общий модификатор стоимости всех видов возрождения', type: 'number', step: 0.1, min: 0 },

        // [Vehicles]
        'MaxAllowedVehicles': { title: 'Максимум транспорта на карте', desc: 'Общий лимит всей техники на сервере', type: 'number', step: 5, min: 0 },
        'VehicleInactivityDestructionTime': { title: 'Уничтожение брошенной техники (часы)', desc: 'Время до самоуничтожения транспорта, если им не пользовались', type: 'number', step: 24, min: 0 },
        'FuelConsumptionRateMultiplier': { title: 'Множитель расхода топлива', desc: '1.0 = обычный расход, 0.5 = в 2 раза экономичнее', type: 'number', step: 0.1, min: 0 },
        'BatteryDrainRateMultiplier': { title: 'Множитель разряда аккумулятора', desc: 'Скорость разряда батарей в транспорте', type: 'number', step: 0.1, min: 0 },
        'VehicleDamageMultiplier': { title: 'Множитель урона транспорту', desc: 'Урон по технике от столкновений и пуль', type: 'number', step: 0.1, min: 0 },
        'LockProtectionDamageMultiplier': { title: 'Урон защите замков', desc: 'Модификатор урона по замкам транспорта при взломе', type: 'number', step: 0.1, min: 0 },
        'EnableVehiclePushing': { title: 'Возможность толкать технику', desc: 'Игроки могут толкать застрявший транспорт руками', type: 'boolean' },

        // [Damage]
        'HumanToHumanDamageModifier': { title: 'PvP множитель урона (пули)', desc: '1.0 = стандартный урон между игроками', type: 'number', step: 0.1, min: 0 },
        'HumanToHumanArmedMeleeDamageModifier': { title: 'PvP урон холодным оружием', desc: 'Урон оружием ближнего боя между игроками', type: 'number', step: 0.1, min: 0 },
        'HumanToHumanUnarmedMeleeDamageModifier': { title: 'PvP урон кулаками', desc: 'Урон в рукопашном бою без оружия', type: 'number', step: 0.1, min: 0 },
        'PlayerToPuppetDamageModifier': { title: 'Урон игроков по зомби', desc: 'Модификатор урона оружия по марионеткам', type: 'number', step: 0.1, min: 0 },
        'PuppetToPlayerDamageModifier': { title: 'Урон зомби по игрокам', desc: 'Модификатор урона от ударов марионеток', type: 'number', step: 0.1, min: 0 },
        'PlayerToWorldDamageModifier': { title: 'Урон от падений и мира', desc: 'Урон от высоты, падений и окружения', type: 'number', step: 0.1, min: 0 },

        // [Puppets]
        'PuppetsDensityModifier': { title: 'Плотность спавна зомби', desc: '1.0 = стандартное количество марионеток в мире', type: 'number', step: 0.1, min: 0 },
        'MaxAllowedPuppetsInWorld': { title: 'Максимум зомби на карте', desc: 'Общий лимит активных марионеток на сервере', type: 'number', step: 10, min: 0 },
        'PuppetSpawningMultiplier': { title: 'Скорость появления зомби', desc: 'Множитель частоты появления новых зомби', type: 'number', step: 0.1, min: 0 },
        'HordePuppetDamageMultiplier': { title: 'Урон зомби в орде', desc: 'Модификатор урона при активации орды', type: 'number', step: 0.1, min: 0 },
        'EnablePuppets': { title: 'Включить марионеток (зомби)', desc: 'Разрешить спавн зомби на сервере', type: 'boolean' },
        'EnableSuicidePuppets': { title: 'Зомби-камикадзе (подрывники)', desc: 'Спавн зомби с взрывчаткой и звуковым таймером', type: 'boolean' },
        'EnablePuppetHordes': { title: 'Механика орд зомби', desc: 'Спавн волн зомби при громком шуме и выстрелах', type: 'boolean' },

        // [Sentinels]
        'EnableSentinels': { title: 'Включить роботов (Сентинелей)', desc: 'Боевые роботы на военных базах и бункерах', type: 'boolean' },
        'SentinelDamageMultiplier': { title: 'Множитель урона роботов', desc: 'Урон от выстрелов пулеметов роботов', type: 'number', step: 0.1, min: 0 },
        'SentinelHealthMultiplier': { title: 'Множитель здоровья роботов', desc: 'Прочность брони роботов (1.0 = стандарт)', type: 'number', step: 0.1, min: 0 },
        'SentinelAccuracyMultiplier': { title: 'Точность стрельбы роботов', desc: 'Меткость прицеливания роботов по игрокам', type: 'number', step: 0.1, min: 0 },

        // [BaseBuilding]
        'EnableBaseBuilding': { title: 'Разрешить строительство баз', desc: 'Возможность возводить заборы, вышки и укрепления', type: 'boolean' },
        'MaxElementsPerFlag': { title: 'Лимит построек на 1 флаг', desc: 'Максимум строительных элементов внутри зоны флага', type: 'number', step: 50, min: 10 },
        'FlagOvertakeDuration': { title: 'Время захвата флага (сек)', desc: 'Длительность удержания для захвата чужого флага', type: 'number', step: 60, min: 10 },
        'BaseBuildingDecayMultiplier': { title: 'Скорость разрушения построек', desc: 'Гниение баз без обслуживания (1.0 = стандарт, 0 = вечные базы)', type: 'number', step: 0.1, min: 0 },
        'DisableFlagDamage': { title: 'Отключить урон по флагам', desc: 'Полная защита флагов от разрушения (антирейд флагов)', type: 'boolean' },
        'MaxFlagPerPlayer': { title: 'Максимум флагов на игрока', desc: 'Сколько баз может создать один игрок', type: 'number', step: 1, min: 1 },
        'MaxFlagPerSquad': { title: 'Максимум флагов на отряд', desc: 'Сколько баз может создать весь отряд', type: 'number', step: 1, min: 1 },

        // [ItemSpawning]
        'SpawnerProbabilityMultiplier': { title: 'Шанс спавна лута в мире', desc: 'Множитель появления предметов лежащих открыто', type: 'number', step: 0.1, min: 0 },
        'ExamineSpawnerProbabilityMultiplier': { title: 'Шанс лута в ящиках/шкафах', desc: 'Множитель вероятности найти предмет при обыске контейнеров', type: 'number', step: 0.1, min: 0 },
        'SpawnerItemInitialBonus': { title: 'Начальный бонус предметов', desc: 'Бонус к луту после перезапуска сервера', type: 'number', step: 0.1, min: 0 },
        'SpawnerItemRespawnTime': { title: 'Время респавна лута (сек)', desc: 'Как быстро восстанавливается лут в зонах', type: 'number', step: 60, min: 0 },
        'CleanItemsOnGroundInterval': { title: 'Очистка брошенного лута (сек)', desc: 'Интервал удаления выброшенных на землю предметов', type: 'number', step: 300, min: 0 },
    }
};

export function configEditor(cfg) {
    return {
        cfg,
        files: cfg.files || [],
        backups: cfg.backups || [],
        dir: cfg.configDir || '',
        connected: !!cfg.connected,

        // Состояние выбора
        current: null,
        content: '',
        originalContent: '',
        busy: false,
        notice: null,

        // Мобильное меню файлов
        mobileFilesOpen: false,

        // Режимы редактирования: 'visual' (форма/настройки), 'raw' (код), 'diff' (сравнение)
        mode: 'visual',
        fileFilter: '',
        fileCategory: 'all', // 'all', 'main', 'ini', 'json', 'lists', 'engine'
        searchQuery: '',     // Поиск параметров внутри файла
        activeSection: '',   // Быстрый переход по секциям
        showAddParamModal: false,
        newParamSection: '',
        newParamKey: '',
        newParamValue: '',
        showAddSectionModal: false,
        newSectionName: '',

        // Модели для визуального редактора
        iniSections: [],
        jsonTree: null,
        isJsonValid: true,
        jsonErrorMsg: '',
        listItems: [],
        newListItem: '',

        // Сравнение изменений
        diffItems: [],
        backupFilterCurrent: false,
        backupSearch: '',

        init() {
            // Если есть файлы, открываем первый главный файл автоматически
            if (this.files.length > 0 && !this.current) {
                const mainFile = this.files.find(f => f.name.toLowerCase() === 'serversettings.ini')
                    || this.files.find(f => f.name.toLowerCase() === 'economyoverride.json')
                    || this.files[0];
                if (mainFile) {
                    this.open(mainFile);
                }
            }

            // Горячая клавиша Ctrl+S / Cmd+S
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    if (this.current && !this.busy) {
                        this.save();
                    }
                }
            });
        },

        /* ===== Вычисляемые свойства и категории файлов ===== */
        get isDirty() {
            return this.current && this.content !== this.originalContent;
        },

        get fileType() {
            if (!this.current) return 'none';
            const name = this.current.name.toLowerCase();
            if (name.endsWith('.json')) return 'json';
            if (['adminusers.ini', 'whitelist.ini', 'banlist.ini', 'mutelist.ini', 'silencelist.ini'].includes(name)) return 'list';
            if (name.endsWith('.ini') || name.endsWith('.cfg')) return 'ini';
            return 'text';
        },

        isMainFile(name) {
            const n = name.toLowerCase();
            return [
                'serversettings.ini', 'economyoverride.json', 'raidsettings.json',
                'adminusers.ini', 'game.ini', 'whitelist.ini', 'banlist.ini'
            ].includes(n);
        },

        isEngineFile(name) {
            const n = name.toLowerCase();
            return ['engine.ini', 'gameusersettings.ini', 'scalability.ini', 'input.ini', 'compat.ini'].includes(n);
        },

        getFileIcon(name) {
            const n = (name || '').toLowerCase();
            if (n === 'serversettings.ini') return '<svg class="w-4 h-4 text-[#58a6ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
            if (n === 'economyoverride.json') return '<svg class="w-4 h-4 text-[#e3b341]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
            if (n === 'raidsettings.json') return '<svg class="w-4 h-4 text-[#f85149]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>';
            if (n === 'adminusers.ini') return '<svg class="w-4 h-4 text-[#a371f7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
            if (n.includes('whitelist') || n.includes('banlist')) return '<svg class="w-4 h-4 text-[#3fb950]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
            if (n.endsWith('.json')) return '<span class="text-xs font-mono font-bold text-[#58a6ff]">{ }</span>';
            return '<svg class="w-4 h-4 text-[#8b949e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';
        },

        getFileBadge(name) {
            const n = name.toLowerCase();
            if (n.endsWith('.json')) return { text: 'JSON', cls: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' };
            if (['adminusers.ini', 'whitelist.ini', 'banlist.ini'].includes(n)) return { text: 'СПИСОК', cls: 'bg-purple-500/15 text-purple-400 border-purple-500/30' };
            if (n.endsWith('.ini')) return { text: 'INI', cls: 'bg-blue-500/15 text-blue-400 border-blue-500/30' };
            return { text: 'TXT', cls: 'bg-gray-500/15 text-gray-400 border-gray-500/30' };
        },

        get filteredFiles() {
            let list = this.files;
            if (this.fileCategory === 'main') {
                list = list.filter(f => this.isMainFile(f.name));
            } else if (this.fileCategory === 'ini') {
                list = list.filter(f => f.name.toLowerCase().endsWith('.ini') || f.name.toLowerCase().endsWith('.cfg'));
            } else if (this.fileCategory === 'json') {
                list = list.filter(f => f.name.toLowerCase().endsWith('.json'));
            } else if (this.fileCategory === 'lists') {
                list = list.filter(f => ['adminusers.ini', 'whitelist.ini', 'banlist.ini', 'mutelist.ini'].includes(f.name.toLowerCase()));
            } else if (this.fileCategory === 'engine') {
                list = list.filter(f => this.isEngineFile(f.name));
            }

            if (this.fileFilter.trim()) {
                const q = this.fileFilter.toLowerCase().trim();
                list = list.filter(f => f.name.toLowerCase().includes(q));
            }
            return list;
        },

        get filteredBackups() {
            let list = this.backups;
            if (this.backupFilterCurrent && this.current) {
                list = list.filter(b => b.file === this.current.name);
            }
            if (this.backupSearch.trim()) {
                const q = this.backupSearch.toLowerCase().trim();
                list = list.filter(b => b.file.toLowerCase().includes(q) || b.stamp.includes(q));
            }
            return list;
        },

        /* ===== Форматирование данных ===== */
        fmtSize(n) {
            n = Number(n) || 0;
            if (n >= 1048576) return (n / 1048576).toFixed(1).replace('.', ',') + ' МБ';
            if (n >= 1024) return Math.round(n / 1024) + ' КБ';
            return n + ' Б';
        },

        fmtStamp(s) {
            const m = String(s || '').match(/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/);
            return m ? m[3] + '.' + m[2] + '.' + m[1] + ' ' + m[4] + ':' + m[5] + ':' + m[6] : (s || '—');
        },

        isOpen(f) {
            return this.current && this.current.name === f.name;
        },

        /* ===== Переключение режимов ===== */
        setMode(newMode) {
            if (this.mode === newMode) return;
            if (this.mode === 'visual') {
                this.syncFromVisualToRaw();
            } else if (this.mode === 'raw') {
                this.syncFromRawToVisual();
            }

            if (newMode === 'diff') {
                this.generateDiff();
            }
            this.mode = newMode;
        },

        /* ===== Чтение / Открытие файла ===== */
        open(f) {
            if (this.busy) return;
            if (this.isDirty && !confirm(`В файле "${this.current.name}" есть несохранённые изменения. Перейти к "${f.name}" без сохранения?`)) {
                return;
            }
            this.busy = true;
            this.notice = null;
            this.searchQuery = '';
            this.mobileFilesOpen = false;

            fetch(this.cfg.readUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.cfg.csrf,
                },
                body: JSON.stringify({ file: f.name }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.ok) {
                        this.current = { name: f.name, size: f.size, encoding: d.encoding || 'utf-8' };
                        this.content = d.content || '';
                        this.originalContent = d.content || '';
                        this.syncFromRawToVisual();
                    } else {
                        this.current = null;
                        this.content = '';
                        this.originalContent = '';
                        this.notice = { ok: false, text: d.error || 'Не удалось прочитать файл' };
                        if (window.toast) window.toast(d.error || 'Не удалось прочитать файл', 'error');
                    }
                })
                .catch((e) => {
                    this.notice = { ok: false, text: 'Ошибка запроса: ' + e.message };
                    if (window.toast) window.toast('Ошибка запроса чтения', 'error');
                })
                .finally(() => {
                    this.busy = false;
                });
        },

        /* ===== Парсинг и синхронизация INI, JSON, Списков ===== */
        syncFromRawToVisual() {
            const type = this.fileType;
            if (type === 'ini') {
                this.parseIni(this.content);
            } else if (type === 'json') {
                this.parseJson(this.content);
            } else if (type === 'list') {
                this.parseList(this.content);
            }
        },

        syncFromVisualToRaw() {
            const type = this.fileType;
            if (type === 'ini') {
                this.content = this.buildIni();
            } else if (type === 'json') {
                this.content = this.buildJson();
            } else if (type === 'list') {
                this.content = this.buildList();
            }
        },

        /* --- INI Парсер --- */
        parseIni(text) {
            const lines = text.split(/\r?\n/);
            const sections = [];
            let currentSection = {
                name: 'General',
                title: 'Основное',
                icon: 'settings',
                desc: 'Базовые параметры',
                collapsed: false,
                items: [],
                rawComments: []
            };

            let pendingComments = [];

            for (let i = 0; i < lines.length; i++) {
                const rawLine = lines[i];
                const line = rawLine.trim();

                if (!line) {
                    continue;
                }

                // Комментарии
                if (line.startsWith(';') || line.startsWith('#') || line.startsWith('//')) {
                    pendingComments.push(rawLine);
                    continue;
                }

                // Секция [SectionName]
                const secMatch = line.match(/^\[([^\]]+)\]$/);
                if (secMatch) {
                    if (currentSection.items.length > 0 || currentSection.rawComments.length > 0 || sections.length > 0) {
                        sections.push(currentSection);
                    }
                    const secName = secMatch[1].trim();
                    const secMeta = SCUM_INI_DICTIONARY._sections[secName] || {
                        title: secName,
                        icon: 'folder',
                        desc: 'Параметры секции ' + secName
                    };

                    currentSection = {
                        name: secName,
                        title: secMeta.title,
                        icon: secMeta.icon,
                        desc: secMeta.desc,
                        collapsed: false,
                        items: [],
                        rawComments: pendingComments
                    };
                    pendingComments = [];
                    continue;
                }

                // Ключ=Значение
                const eqIdx = line.indexOf('=');
                if (eqIdx !== -1) {
                    const key = line.slice(0, eqIdx).trim();
                    const val = line.slice(eqIdx + 1).trim();

                    const keyMeta = SCUM_INI_DICTIONARY._keys[key] || {};
                    let detectedType = keyMeta.type;

                    if (!detectedType) {
                        const lowVal = val.toLowerCase();
                        if (['true', 'false', '1', '0', 'yes', 'no'].includes(lowVal) && key.toLowerCase().startsWith('b')) {
                            detectedType = 'boolean';
                        } else if (!isNaN(Number(val)) && val !== '') {
                            detectedType = 'number';
                        } else {
                            detectedType = 'string';
                        }
                    }

                    let parsedVal = val;
                    if (detectedType === 'boolean') {
                        const low = val.toLowerCase();
                        parsedVal = (low === 'true' || low === '1' || low === 'yes');
                    } else if (detectedType === 'number') {
                        parsedVal = val; // Сохраняем строку для аккуратного инпута
                    }

                    currentSection.items.push({
                        key,
                        value: parsedVal,
                        rawOriginalValue: val,
                        type: detectedType,
                        title: keyMeta.title || key,
                        desc: keyMeta.desc || '',
                        options: keyMeta.options || null,
                        step: keyMeta.step || (val.includes('.') ? 0.1 : 1),
                        min: keyMeta.min !== undefined ? keyMeta.min : null,
                        max: keyMeta.max !== undefined ? keyMeta.max : null,
                        comments: pendingComments
                    });
                    pendingComments = [];
                } else {
                    pendingComments.push(rawLine);
                }
            }

            if (currentSection.items.length > 0 || currentSection.rawComments.length > 0 || sections.length === 0) {
                sections.push(currentSection);
            }

            this.iniSections = sections;
            if (sections.length > 0 && !this.activeSection) {
                this.activeSection = sections[0].name;
            }
        },

        buildIni() {
            let out = [];
            for (const sec of this.iniSections) {
                if (sec.rawComments && sec.rawComments.length > 0) {
                    out.push(sec.rawComments.join('\n'));
                }
                out.push(`[${sec.name}]`);
                for (const item of sec.items) {
                    if (item.comments && item.comments.length > 0) {
                        out.push(item.comments.join('\n'));
                    }
                    let formattedVal = item.value;
                    if (item.type === 'boolean') {
                        // Сохраняем оригинальный стиль bool (True/False или 1/0 или true/false)
                        const orig = String(item.rawOriginalValue || '').toLowerCase();
                        if (orig === '1' || orig === '0') {
                            formattedVal = item.value ? '1' : '0';
                        } else if (orig === 'true' || orig === 'false') {
                            formattedVal = item.value ? 'True' : 'False';
                        } else {
                            formattedVal = item.value ? 'true' : 'false';
                        }
                    }
                    out.push(`${item.key}=${formattedVal}`);
                }
                out.push('');
            }
            return out.join('\n').trim() + '\n';
        },

        /* --- JSON Парсер --- */
        parseJson(text) {
            try {
                if (!text.trim()) {
                    this.jsonTree = {};
                    this.isJsonValid = true;
                    this.jsonErrorMsg = '';
                    return;
                }
                this.jsonTree = JSON.parse(text);
                this.isJsonValid = true;
                this.jsonErrorMsg = '';
            } catch (e) {
                this.isJsonValid = false;
                this.jsonErrorMsg = e.message;
            }
        },

        buildJson() {
            if (!this.isJsonValid || this.jsonTree === null) {
                return this.content;
            }
            try {
                return JSON.stringify(this.jsonTree, null, 2) + '\n';
            } catch (e) {
                return this.content;
            }
        },

        formatJson() {
            try {
                const parsed = JSON.parse(this.content);
                this.content = JSON.stringify(parsed, null, 2) + '\n';
                this.jsonTree = parsed;
                this.isJsonValid = true;
                this.jsonErrorMsg = '';
                if (window.toast) window.toast('JSON красиво отформатирован', 'success');
            } catch (e) {
                this.isJsonValid = false;
                this.jsonErrorMsg = e.message;
                if (window.toast) window.toast('Ошибка синтаксиса JSON: ' + e.message, 'error');
            }
        },

        minifyJson() {
            try {
                const parsed = JSON.parse(this.content);
                this.content = JSON.stringify(parsed);
                this.jsonTree = parsed;
                this.isJsonValid = true;
                this.jsonErrorMsg = '';
                if (window.toast) window.toast('JSON сжат в 1 строку', 'info');
            } catch (e) {
                this.isJsonValid = false;
                this.jsonErrorMsg = e.message;
                if (window.toast) window.toast('Ошибка синтаксиса JSON', 'error');
            }
        },

        /* --- List Парсер (SteamIDs / Admins) --- */
        parseList(text) {
            const lines = text.split(/\r?\n/);
            const items = [];
            for (const l of lines) {
                const trimmed = l.trim();
                if (!trimmed) continue;
                const isComment = trimmed.startsWith(';') || trimmed.startsWith('#') || trimmed.startsWith('//');
                const cleanId = trimmed.replace(/^[;#/]+\s*/, '').split(/[\s#;]/)[0];
                const comment = trimmed.includes('#') ? trimmed.slice(trimmed.indexOf('#') + 1).trim() : '';

                items.push({
                    raw: trimmed,
                    value: cleanId,
                    comment: comment,
                    isComment: isComment,
                    isValidSteamId: /^\d{17}$/.test(cleanId)
                });
            }
            this.listItems = items;
        },

        buildList() {
            return this.listItems.map(item => {
                if (item.isComment) return `# ${item.value}`;
                return item.comment ? `${item.value} # ${item.comment}` : item.value;
            }).join('\n') + '\n';
        },

        addListItem() {
            const val = this.newListItem.trim();
            if (!val) return;
            const isValid = /^\d{17}$/.test(val);
            this.listItems.push({
                raw: val,
                value: val,
                comment: '',
                isComment: false,
                isValidSteamId: isValid
            });
            this.newListItem = '';
            this.content = this.buildList();
            if (window.toast) window.toast(`Добавлен SteamID: ${val}`, 'success');
        },

        removeListItem(index) {
            this.listItems.splice(index, 1);
            this.content = this.buildList();
        },

        /* ===== Поиск и фильтрация внутри активного файла ===== */
        get filteredSections() {
            if (!this.searchQuery.trim()) return this.iniSections;
            const q = this.searchQuery.toLowerCase().trim();

            return this.iniSections.map(sec => {
                const matchesSec = sec.name.toLowerCase().includes(q) || (sec.title && sec.title.toLowerCase().includes(q));
                const filteredItems = sec.items.filter(item => {
                    return item.key.toLowerCase().includes(q) ||
                        (item.title && item.title.toLowerCase().includes(q)) ||
                        (item.desc && item.desc.toLowerCase().includes(q)) ||
                        String(item.value).toLowerCase().includes(q);
                });
                if (matchesSec || filteredItems.length > 0) {
                    return {
                        ...sec,
                        items: matchesSec ? sec.items : filteredItems,
                        collapsed: false // Раскрываем при поиске
                    };
                }
                return null;
            }).filter(Boolean);
        },

        /* ===== Управление параметрами INI ===== */
        onParamChange() {
            // Мгновенное обновление сырого содержимого
            this.content = this.buildIni();
        },

        toggleSection(secName) {
            const sec = this.iniSections.find(s => s.name === secName);
            if (sec) sec.collapsed = !sec.collapsed;
        },

        expandAllSections() {
            this.iniSections.forEach(s => s.collapsed = false);
        },

        collapseAllSections() {
            this.iniSections.forEach(s => s.collapsed = true);
        },

        openAddParam(secName) {
            this.newParamSection = secName;
            this.newParamKey = '';
            this.newParamValue = '';
            this.showAddParamModal = true;
        },

        addCustomParam() {
            if (!this.newParamKey.trim()) return;
            const sec = this.iniSections.find(s => s.name === this.newParamSection);
            if (!sec) return;

            const key = this.newParamKey.trim();
            const val = this.newParamValue.trim();
            const keyMeta = SCUM_INI_DICTIONARY._keys[key] || {};

            sec.items.push({
                key,
                value: val,
                rawOriginalValue: val,
                type: keyMeta.type || (val === 'true' || val === 'false' ? 'boolean' : (!isNaN(Number(val)) && val !== '' ? 'number' : 'string')),
                title: keyMeta.title || key,
                desc: keyMeta.desc || 'Пользовательский параметр',
                options: keyMeta.options || null,
                step: 1
            });

            this.showAddParamModal = false;
            this.onParamChange();
            if (window.toast) window.toast(`Параметр [${key}] добавлен`, 'success');
        },

        removeParam(secIndex, itemIndex) {
            if (!confirm('Удалить этот параметр из конфигурации?')) return;
            this.iniSections[secIndex].items.splice(itemIndex, 1);
            this.onParamChange();
        },

        addNewSection() {
            const name = this.newSectionName.trim().replace(/[\[\]]/g, '');
            if (!name) return;
            const meta = SCUM_INI_DICTIONARY._sections[name] || {
                title: name,
                icon: 'folder',
                desc: 'Секция ' + name
            };
            this.iniSections.push({
                name: name,
                title: meta.title,
                icon: meta.icon,
                desc: meta.desc,
                collapsed: false,
                items: [],
                rawComments: []
            });
            this.newSectionName = '';
            this.showAddSectionModal = false;
            this.activeSection = name;
            this.onParamChange();
            if (window.toast) window.toast(`Секция [${name}] добавлена`, 'success');
        },

        /* ===== Сравнение изменений (Diff) ===== */
        generateDiff(compareText = null) {
            const original = (compareText !== null ? compareText : this.originalContent).split(/\r?\n/);
            const current = (this.mode === 'visual' ? this.buildIni() : this.content).split(/\r?\n/);

            const diff = [];
            const max = Math.max(original.length, current.length);

            for (let i = 0; i < max; i++) {
                const origLine = original[i];
                const currLine = current[i];

                if (origLine === currLine) {
                    if (currLine !== undefined) {
                        diff.push({ type: 'same', line: currLine, num: i + 1 });
                    }
                } else {
                    if (origLine !== undefined && (currLine === undefined || origLine !== currLine)) {
                        diff.push({ type: 'del', line: origLine, num: i + 1 });
                    }
                    if (currLine !== undefined && (origLine === undefined || origLine !== currLine)) {
                        diff.push({ type: 'add', line: currLine, num: i + 1 });
                    }
                }
            }
            this.diffItems = diff;
        },

        /* ===== Сохранение файла на сервер ===== */
        save() {
            if (this.busy || !this.current) return;
            this.busy = true;
            this.notice = null;

            if (this.mode === 'visual') {
                this.syncFromVisualToRaw();
            }

            fetch(this.cfg.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.cfg.csrf,
                },
                body: JSON.stringify({
                    file: this.current.name,
                    content: this.content,
                    encoding: this.current.encoding,
                }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        this.originalContent = this.content;
                        if (d.backups) this.backups = d.backups;
                        this.notice = { ok: true, text: d.message || 'Файл успешно сохранён на сервер!' };
                        if (window.toast) window.toast(d.message || 'Файл сохранён на сервер', 'success');
                    } else {
                        this.notice = { ok: false, text: d.message || 'Ошибка сохранения' };
                        if (window.toast) window.toast(d.message || 'Ошибка сохранения', 'error');
                    }
                })
                .catch((e) => {
                    this.notice = { ok: false, text: 'Ошибка запроса: ' + e.message };
                    if (window.toast) window.toast('Ошибка запроса сохранения', 'error');
                })
                .finally(() => {
                    this.busy = false;
                });
        },

        /* ===== Создание локального бэкапа ===== */
        createBackupNow() {
            if (this.busy || !this.current) return;
            this.busy = true;
            this.notice = null;

            fetch(this.cfg.backupUrl || '/config-files/backup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.cfg.csrf,
                },
                body: JSON.stringify({ file: this.current.name }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        if (d.backups) this.backups = d.backups;
                        this.notice = { ok: true, text: d.message || 'Локальный бэкап создан!' };
                        if (window.toast) window.toast('Локальный бэкап создан', 'success');
                    } else {
                        this.notice = { ok: false, text: d.message || 'Не удалось создать бэкап' };
                        if (window.toast) window.toast(d.message || 'Ошибка создания бэкапа', 'error');
                    }
                })
                .catch(() => {
                    this.notice = { ok: false, text: 'Ошибка запроса бэкапа' };
                })
                .finally(() => {
                    this.busy = false;
                });
        },

        /* ===== Восстановление из бэкапа ===== */
        restore(path) {
            if (this.busy || !path) return;
            if (!confirm('Восстановить этот бэкап на сервер? Текущий файл на сервере будет заменён сохранённой копией.')) return;
            this.busy = true;
            this.notice = null;

            fetch(this.cfg.restoreUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.cfg.csrf,
                },
                body: JSON.stringify({ path }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        if (d.backups) this.backups = d.backups;
                        this.notice = { ok: true, text: d.message || 'Бэкап восстановлен!' };
                        if (window.toast) window.toast('Бэкап успешно восстановлен', 'success');
                        if (this.current && path.indexOf(this.current.name) !== -1) {
                            this.open(this.current);
                        }
                    } else {
                        this.notice = { ok: false, text: d.message || 'Ошибка восстановления' };
                        if (window.toast) window.toast(d.message || 'Ошибка восстановления', 'error');
                    }
                })
                .catch(() => {
                    this.notice = { ok: false, text: 'Ошибка запроса восстановления' };
                })
                .finally(() => {
                    this.busy = false;
                });
        },

        /* ===== Сброс к исходному ===== */
        resetChanges() {
            if (!confirm('Сбросить все несохранённые изменения к исходному состоянию?')) return;
            this.content = this.originalContent;
            this.syncFromRawToVisual();
            if (window.toast) window.toast('Изменения сброшены', 'info');
        },

        /* ===== Копирование в буфер ===== */
        copyContent() {
            const txt = this.mode === 'visual' ? this.buildIni() : this.content;
            if (window.copyToClipboard) {
                window.copyToClipboard(txt);
            } else {
                navigator.clipboard.writeText(txt);
            }
            if (window.toast) window.toast('Содержимое скопировано в буфер', 'success');
        }
    };
}
