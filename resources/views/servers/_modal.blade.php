{{-- Модальное окно «Добавить / Настроить сервер» --}}
<div id="server-modal" x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 transform scale-95"
     x-transition:enter-end="opacity-100 transform scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 transform scale-100"
     x-transition:leave-end="opacity-0 transform scale-95"
     class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
     style="background:rgba(2, 6, 12, 0.85); backdrop-filter:blur(8px)"
     @keydown.escape.window="close()">
    
    <div class="relative w-full max-w-3xl my-auto rounded-2xl border border-[#30363d]/90 bg-[#121824] shadow-2xl shadow-black/80 flex flex-col max-h-[92vh] overflow-hidden" @click.stop>
        {{-- Верхний декоративный градиент --}}
        <div class="h-1.5 w-full bg-gradient-to-r from-[#1f6feb] via-[#388bfd] to-[#238636]"></div>

        {{-- Шапка модального окна --}}
        <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-[#30363d]/80 bg-[#0e131f]/90 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]">
                    <x-icon name="server" class="w-5 h-5" />
                </div>
                <div>
                    <h2 id="server-modal-title" class="text-base sm:text-lg font-bold text-[#e6edf3]" x-text="title">Добавить сервер</h2>
                    <p class="text-xs text-[#8b949e]">Настройки подключения к игровому серверу SCUM</p>
                </div>
            </div>
            <button type="button" @click="close()" class="p-2 rounded-lg text-[#8b949e] hover:text-[#e6edf3] hover:bg-[#21262d] transition-colors">
                <x-icon name="x" class="w-4 h-4" />
            </button>
        </div>

        {{-- Навигация по вкладкам --}}
        <div class="flex items-center gap-1 px-4 sm:px-6 pt-3 pb-2 border-b border-[#21262d] bg-[#0b0f17] overflow-x-auto custom-scrollbar shrink-0">
            <button type="button" @click="setTab('general')"
                    :class="currentTab === 'general' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                    class="px-3.5 py-1.5 rounded-lg text-xs border flex items-center gap-2 whitespace-nowrap transition-all">
                <x-icon name="server" class="w-4 h-4" />
                <span>Основные</span>
            </button>
            <button type="button" @click="setTab('ftp')"
                    :class="currentTab === 'ftp' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                    class="px-3.5 py-1.5 rounded-lg text-xs border flex items-center gap-2 whitespace-nowrap transition-all">
                <x-icon name="refresh-cw" class="w-4 h-4" />
                <span>FTP Watcher</span>
            </button>
            <button type="button" @click="setTab('rcon')"
                    :class="currentTab === 'rcon' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                    class="px-3.5 py-1.5 rounded-lg text-xs border flex items-center gap-2 whitespace-nowrap transition-all">
                <x-icon name="terminal" class="w-4 h-4" />
                <span>RCON</span>
            </button>
            <button type="button" @click="setTab('plugins')"
                    :class="currentTab === 'plugins' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                    class="px-3.5 py-1.5 rounded-lg text-xs border flex items-center gap-2 whitespace-nowrap transition-all">
                <x-icon name="puzzle" class="w-4 h-4" />
                <span>Плагины</span>
            </button>
            <button type="button" @click="setTab('lolka')"
                    :class="currentTab === 'lolka' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm font-semibold' : 'text-[#8b949e] hover:text-[#e6edf3] border-transparent hover:bg-[#161b22]'"
                    class="px-3.5 py-1.5 rounded-lg text-xs border flex items-center gap-2 whitespace-nowrap transition-all">
                <x-icon name="message-square" class="w-4 h-4" />
                <span>Lolka Webhooks</span>
            </button>
        </div>

        {{-- Тело формы со скроллом --}}
        <form id="server-form" :action="action" method="POST" class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-5 bg-[#0e131f]/50">
            @csrf

            {{-- 1. ВКЛАДКА: ОСНОВНЫЕ --}}
            <div x-show="currentTab === 'general'" x-cloak class="space-y-4">
                <div class="p-4 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-1.5">
                            Название сервера <span class="text-[#f85149]">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#8b949e]"><x-icon name="server" class="w-4 h-4" /></span>
                            <input type="text" name="name" required x-model="form.name" placeholder="Например: SCUM Official #1 / PvP High Loot"
                                   class="input pl-10 text-sm bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] rounded-xl py-2.5" maxlength="100">
                        </div>
                        <p class="text-[11px] text-[#8b949e] mt-1.5">Понятное имя для отображения в списке серверов и в шапке панели.</p>
                        @error('name') <div class="text-xs text-[#f85149] mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="pt-2 border-t border-[#21262d]">
                        <label class="flex items-center justify-between gap-3 p-3 rounded-xl bg-[#0d1117]/80 border border-[#21262d] hover:border-[#388bfd]/30 transition-all cursor-pointer">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-[#2ea043]/15 border border-[#2ea043]/30 flex items-center justify-center text-sm">
                                    <x-icon name="zap" class="w-4 h-4 text-amber-400" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-[#e6edf3]">Включить сервер</div>
                                    <div class="text-xs text-[#8b949e]">Разрешить сбор логов, RCON управление и работу плагинов</div>
                                </div>
                            </div>
                            <span class="toggle">
                                <input type="checkbox" name="enabled" value="1" x-model="form.enabled">
                                <span class="toggle-slider"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-[#1f6feb]/10 border border-[#388bfd]/25 text-xs text-[#8b949e] leading-relaxed flex items-start gap-3">
                    <x-icon name="info" class="w-4 h-4 text-[#58a6ff]" />
                    <div>
                        <strong class="text-[#e6edf3]">Быстрый старт:</strong> Заполните данные во вкладках <strong>FTP Watcher</strong> и <strong>RCON</strong>. Панель сразу начнёт считывать онлайн-игроков, события чата, карту и синхронизировать базу данных `SCUM.db`.
                    </div>
                </div>
            </div>

            {{-- 2. ВКЛАДКА: FTP & ЛОГИ --}}
            <div x-show="currentTab === 'ftp'" x-cloak class="space-y-4">
                <div class="p-4 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60">
                    <div class="flex items-center gap-2 mb-3">
                        <x-icon name="database" class="w-4 h-4 text-[#8b949e]" />
                        <h3 class="text-sm font-bold text-[#e6edf3]">Параметры подключения FTP Watcher (чтение логов и файлов)</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-1">
                            <label class="block text-xs text-[#8b949e] mb-1">Протокол</label>
                            <select name="ftp_protocol" x-model="form.ftp_protocol" @change="onProtocolChange()" class="input text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                                <option value="ftp">FTP (Стандартный)</option>
                                <option value="sftp">SFTP (SSH)</option>
                            </select>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-xs text-[#8b949e] mb-1">IP адрес / Хост</label>
                            <input type="text" name="ftp_host" x-model="form.ftp_host" placeholder="192.168.1.100" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-xs text-[#8b949e] mb-1">FTP / SFTP Порт</label>
                            <input type="number" name="ftp_port" x-model="form.ftp_port" placeholder="21" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                        <div>
                            <label class="block text-xs text-[#8b949e] mb-1">FTP Логин</label>
                            <input type="text" name="ftp_username" x-model="form.ftp_username" placeholder="scum_user" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs text-[#8b949e]">FTP Пароль</label>
                                <button type="button" @click="showFtpPassword = !showFtpPassword" class="text-[11px] text-[#58a6ff] hover:underline">
                                    <span x-text="showFtpPassword ? 'Скрыть' : 'Показать'"></span>
                                </button>
                            </div>
                            <div class="relative">
                                <input :type="showFtpPassword ? 'text' : 'password'" name="ftp_password" x-model="form.ftp_password" placeholder="••••••••" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg pr-8">
                            </div>
                        </div>
                    </div>

                    {{-- Кнопка проверки связи FTP --}}
                    <div class="mt-4 pt-3 border-t border-[#21262d] flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="testFtp()" :disabled="ftpTesting"
                                class="btn btn-sm bg-[#0d1117] hover:bg-[#161b22] border-[#30363d] hover:border-[#58a6ff]/50 text-xs text-[#58a6ff] flex items-center gap-2 cursor-pointer shadow-xs">
                            <span x-show="ftpTesting"><x-icon name="refresh-cw" class="w-3.5 h-3.5 animate-spin" /></span>
                            <span x-show="!ftpTesting"><x-icon name="wifi" class="w-3.5 h-3.5" /></span>
                            <span x-text="ftpTesting ? 'Проверка соединения…' : 'Проверить соединение FTP'">Проверить соединение FTP</span>
                        </button>
                        <div x-show="ftpTestResult" x-cloak class="text-xs font-semibold flex items-center gap-1.5"
                             :class="ftpTestResult?.success ? 'text-[#3fb950]' : 'text-[#f85149]'">
                            <span x-show="ftpTestResult?.success"><x-icon name="check-circle" class="w-4 h-4 text-[#3fb950]" /></span>
                            <span x-show="!ftpTestResult?.success"><x-icon name="alert-triangle" class="w-4 h-4 text-[#f85149]" /></span>
                            <span x-text="ftpTestResult?.message"></span>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60">
                    <div class="flex items-center gap-2 mb-3">
                        <x-icon name="file-text" class="w-4 h-4 text-[#8b949e]" />
                        <h3 class="text-sm font-bold text-[#e6edf3]">Пути к файлам сервера</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-xs text-[#8b949e] mb-1">Путь к папке логов (Logs)</label>
                            <input type="text" name="ftp_log_path" x-model="form.ftp_log_path" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg" placeholder="SCUM/Saved/SaveFiles/Logs">
                            <p class="text-[11px] text-[#8b949e] mt-1">Обычно: <code class="text-[#58a6ff]">SCUM/Saved/SaveFiles/Logs</code></p>
                        </div>
                        <div>
                            <label class="block text-xs text-[#8b949e] mb-1">Путь к базе данных SCUM.db</label>
                            <input type="text" name="ftp_scumdb_path" x-model="form.ftp_scumdb_path" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg" placeholder="SCUM/Saved/SaveFiles/SCUM.db">
                            <p class="text-[11px] text-[#8b949e] mt-1">Обычно: <code class="text-[#58a6ff]">SCUM/Saved/SaveFiles/SCUM.db</code></p>
                        </div>
                        <div>
                            <label class="block text-xs text-[#8b949e] mb-1">Путь к папке конфигураций (Config)</label>
                            <input type="text" name="ftp_config_path" x-model="form.ftp_config_path" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg" placeholder="SCUM/Saved/Config/WindowsServer">
                            <p class="text-[11px] text-[#8b949e] mt-1">Обычно: <code class="text-[#58a6ff]">SCUM/Saved/Config/WindowsServer</code></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. ВКЛАДКА: RCON --}}
            <div x-show="currentTab === 'rcon'" x-cloak class="space-y-4">
                <div class="p-4 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60 space-y-3">
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="terminal" class="w-4 h-4 text-[#8b949e]" />
                        <h3 class="text-sm font-bold text-[#e6edf3]">Параметры RCON подключения</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-[#8b949e] mb-1">RCON IP / Хост</label>
                            <input type="text" name="rcon_host" x-model="form.rcon_host" placeholder="192.168.1.100" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-xs text-[#8b949e] mb-1">RCON Порт</label>
                            <input type="number" name="rcon_port" x-model="form.rcon_port" placeholder="28015" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs text-[#8b949e]">RCON Пароль</label>
                            <button type="button" @click="showRconPassword = !showRconPassword" class="text-[11px] text-[#58a6ff] hover:underline">
                                <span x-text="showRconPassword ? 'Скрыть' : 'Показать'"></span>
                            </button>
                        </div>
                        <input :type="showRconPassword ? 'text' : 'password'" name="rcon_password" x-model="form.rcon_password" placeholder="••••••••" class="input mono text-xs sm:text-sm bg-[#0d1117] border-[#30363d] rounded-lg">
                    </div>

                    {{-- Кнопка проверки связи RCON --}}
                    <div class="mt-3 pt-3 border-t border-[#21262d] flex flex-wrap items-center justify-between gap-3">
                        <button type="button" @click="testRcon()" :disabled="rconTesting"
                                class="btn btn-sm bg-[#0d1117] hover:bg-[#161b22] border-[#30363d] hover:border-[#58a6ff]/50 text-xs text-[#58a6ff] flex items-center gap-2 cursor-pointer shadow-xs">
                            <span x-show="rconTesting"><x-icon name="refresh-cw" class="w-3.5 h-3.5 animate-spin" /></span>
                            <span x-show="!rconTesting"><x-icon name="terminal" class="w-3.5 h-3.5" /></span>
                            <span x-text="rconTesting ? 'Проверка RCON…' : 'Проверить соединение RCON'">Проверить соединение RCON</span>
                        </button>
                        <div x-show="rconTestResult" x-cloak class="text-xs font-semibold flex items-center gap-1.5"
                             :class="rconTestResult?.success ? 'text-[#3fb950]' : 'text-[#f85149]'">
                            <span x-show="rconTestResult?.success"><x-icon name="check-circle" class="w-4 h-4 text-[#3fb950]" /></span>
                            <span x-show="!rconTestResult?.success"><x-icon name="alert-triangle" class="w-4 h-4 text-[#f85149]" /></span>
                            <span x-text="rconTestResult?.message"></span>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-[#161b22]/50 border border-[#30363d]/50 text-xs text-[#8b949e] space-y-1.5">
                    <div class="font-semibold text-[#e6edf3] flex items-center gap-1.5">
                        <x-icon name="info" class="w-4 h-4 text-[#58a6ff] inline-block mr-1.5" /> Как узнать RCON пароль и порт:
                    </div>
                    <p>Параметры RCON настраиваются на игровом хостинге или в файле конфигурации <code class="text-[#58a6ff]">ServerSettings.ini</code> на вашем игровом сервере SCUM.</p>
                </div>
            </div>

            {{-- 4. ВКЛАДКА: ПЛАГИНЫ --}}
            <div x-show="currentTab === 'plugins'" x-cloak class="space-y-4">
                <div class="p-4 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60">
                    <div class="mb-3">
                        <h3 class="text-sm font-bold text-[#e6edf3]">Модули и плагины панели</h3>
                        <p class="text-xs text-[#8b949e]">Включите нужные функции для игроков и администратора на этом сервере</p>
                    </div>

                    @php
                        $pluginItems = [
                            'teleport' => ['icon' => 'map', 'title' => 'Телепорт', 'desc' => 'Команда !teleport — создание точек и возврат назад'],
                            'vip' => ['icon' => 'crown', 'title' => 'VIP статус', 'desc' => 'Привилегии, кастомные права и бонусы для VIP игроков'],
                            'saveHome' => ['icon' => 'home', 'title' => 'SaveHome', 'desc' => 'Команда !home — сохранение и быстрый телепорт домой'],
                            'airdrop' => ['icon' => 'parachute', 'title' => 'Airdrop', 'desc' => 'Автоматический и ручной сброс грузов с припасами'],
                            'rewards' => ['icon' => 'trophy', 'title' => 'Награды (Rewards)', 'desc' => 'Награды за наигранное время и топ-игрокам'],
                            'packs' => ['icon' => 'package', 'title' => 'Паки предметов', 'desc' => 'Стартовые наборы и выдача комплектов лута через RCON'],
                            'insurance' => ['icon' => 'shield', 'title' => 'Страхование ТС', 'desc' => 'Команды !страховка — привязка и возврат транспорта'],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($pluginItems as $key => $meta)
                            <label class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-[#0d1117]/80 border border-[#21262d] hover:border-[#388bfd]/40 transition-all cursor-pointer group">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-9 h-9 rounded-xl bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#58a6ff] group-hover:border-[#388bfd]/50 group-hover:text-white transition-all shrink-0 shadow-sm">
                                        <x-icon :name="$meta['icon']" class="w-4 h-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs sm:text-sm font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] transition-colors truncate">{{ $meta['title'] }}</div>
                                        <div class="text-[11px] text-[#8b949e] truncate">{{ $meta['desc'] }}</div>
                                    </div>
                                </div>
                                <span class="toggle shrink-0">
                                    <input type="checkbox" name="plugin_{{ $key }}" value="1" x-model="form.plugin_{{ $key }}">
                                    <span class="toggle-slider"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 5. ВКЛАДКА: LOLKA WEBHOOKS --}}
            <div x-show="currentTab === 'lolka'" x-cloak class="space-y-4">
                {{-- Скрытый инпут для отправки списка вебхуков в JSON --}}
                <input type="hidden" name="lolka_webhooks" :value="JSON.stringify(form.lolka_webhooks)">

                <div class="p-4 sm:p-5 rounded-xl bg-[#161b22]/70 border border-[#30363d]/60 space-y-4">
                    {{-- Шапка секции без ползунка --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#21262d]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#5865F2]/20 border border-[#5865F2]/40 flex items-center justify-center text-lg text-[#5865F2] shrink-0">
                                <x-icon name="message-square" class="w-4 h-4 text-[#58a6ff]" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#e6edf3]">Lolka Webhook интеграции</h3>
                                <p class="text-xs text-[#8b949e]">Отправка логов, событий и уведомлений в каналы через Webhook API</p>
                            </div>
                        </div>

                        <button type="button" @click="openAddWebhook()" x-show="!showAddWebhookForm"
                                class="btn btn-accent btn-sm text-xs font-semibold px-3.5 py-1.5 self-start sm:self-auto flex items-center gap-1.5 shadow-md shadow-[#1f6feb]/20">
                            <x-icon name="plus" class="w-4 h-4" />
                            <span>Добавить вебхук</span>
                        </button>
                    </div>

                    {{-- Форма добавления / редактирования вебхука --}}
                    <div x-show="showAddWebhookForm" x-transition class="p-4 rounded-xl bg-[#0d1117] border border-[#388bfd]/40 space-y-3.5 shadow-lg">
                        <div class="flex items-center justify-between pb-2 border-b border-[#21262d]">
                            <div class="flex items-center gap-2 text-xs font-bold text-[#58a6ff]">
                                <span x-text="editingWebhookIndex !== null ? 'Редактирование вебхука' : 'Добавление нового вебхука'"></span>
                            </div>
                            <button type="button" @click="cancelWebhookForm()" class="text-xs text-[#8b949e] hover:text-[#e6edf3] p-1"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                    Название вебхука <span class="text-[#f85149]">*</span>
                                </label>
                                <input type="text" x-model="webhookForm.name" placeholder="Например: Выполненные команды"
                                       class="input text-xs bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] rounded-lg">
                                <p class="text-[11px] text-[#8b949e] mt-1">Понятное название для отображения в списке.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                    Имя ключа вебхука (Key) <span class="text-[#f85149]">*</span>
                                </label>
                                <input type="text" x-model="webhookForm.key" placeholder="Например: commandWebhook, chatWebhook, custom_event..."
                                       class="input mono text-xs bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] rounded-lg">
                                <p class="text-[11px] text-[#8b949e] mt-1">Ключ, который будет использоваться системой для отправки.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">
                                Ссылка для вставки (Webhook URL) <span class="text-[#f85149]">*</span>
                            </label>
                            <input type="text" x-model="webhookForm.url" placeholder="https://lolka.app/api/webhooks/..."
                                   class="input mono text-xs bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] rounded-lg">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="cancelWebhookForm()" class="btn btn-sm text-xs px-3 py-1.5 text-[#8b949e] hover:text-[#e6edf3]">Отмена</button>
                            <button type="button" @click="saveWebhook()" class="btn btn-primary btn-sm text-xs font-semibold px-4 py-1.5 shadow-md shadow-[#2ea043]/20">
                                <x-icon name="check" class="w-4 h-4" />
                                <span x-text="editingWebhookIndex !== null ? 'Обновить вебхук' : 'Сохранить вебхук'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Список сохранённых вебхуков --}}
                    <div class="space-y-2.5">
                        <template x-if="form.lolka_webhooks.length === 0">
                            <div class="p-6 text-center rounded-xl bg-[#0d1117]/60 border border-dashed border-[#30363d] text-xs text-[#8b949e] space-y-2">
                                <div class="w-10 h-10 rounded-xl bg-[#58a6ff]/10 text-[#58a6ff] flex items-center justify-center mx-auto mb-1"><x-icon name="message-square" class="w-5 h-5" /></div>
                                <p>Нет добавленных вебхуков.</p>
                                <button type="button" @click="openAddWebhook()" class="btn btn-accent btn-xs px-3 py-1 mt-1">
                                    <span class="inline-flex items-center gap-1.5"><x-icon name="plus" class="w-4 h-4" /> Добавить вебхук</span>
                                </button>
                            </div>
                        </template>

                        <template x-for="(wh, idx) in form.lolka_webhooks" :key="idx">
                            <div class="p-3.5 rounded-xl bg-[#0d1117] border border-[#21262d] hover:border-[#388bfd]/30 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="min-w-0 space-y-1 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-bold text-[#e6edf3]" x-text="wh.name || 'Вебхук'"></span>
                                        <span class="mono text-[11px] bg-[#161b22] px-2 py-0.5 rounded border border-[#30363d] text-[#58a6ff]" x-text="wh.key"></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-[#8b949e]">
                                        <span class="mono text-[11px] truncate max-w-md bg-[#161b22]/70 px-2 py-0.5 rounded" x-text="wh.url"></span>
                                        <button type="button" @click="copy(wh.url)" class="text-[#58a6ff] hover:underline text-[11px] shrink-0" title="Скопировать ссылку">
                                            Копировать
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto">
                                    <button type="button" @click="openEditWebhook(idx)" class="btn btn-sm text-xs py-1 px-2.5 text-[#8b949e] hover:text-[#e6edf3]" title="Редактировать">
                                        <x-icon name="edit" class="w-3.5 h-3.5 text-[#58a6ff]" />
                                    </button>
                                    <button type="button" @click="removeWebhook(idx)" class="btn btn-danger btn-sm text-xs py-1 px-2.5 opacity-80 hover:opacity-100" title="Удалить">
                                        <x-icon name="trash" class="w-3.5 h-3.5 text-[#f85149]" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Нижняя панель с кнопками сохранения --}}
            <div class="pt-4 border-t border-[#30363d]/80 flex items-center justify-between gap-3 bg-[#0e131f]/30">
                <button type="button" @click="close()" class="btn px-4 py-2 text-xs sm:text-sm">Отмена</button>
                <button id="server-submit-btn" type="submit" class="btn btn-primary px-5 py-2 text-xs sm:text-sm shadow-lg shadow-[#2ea043]/20 flex items-center gap-1.5">
                    <x-icon name="check" class="w-4 h-4" />
                    <span x-text="submitLabel">Сохранить сервер</span>
                </button>
            </div>
        </form>
    </div>
</div>
