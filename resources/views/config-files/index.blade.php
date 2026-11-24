@extends('layouts.app')

@section('title', 'Конфиги сервера')

@section('content')
    @php
        $cfg = [
            'files' => $files,
            'backups' => $backups,
            'configDir' => $configDir,
            'connected' => $connected,
            'readUrl' => route('config-files.read'),
            'saveUrl' => route('config-files.save'),
            'backupUrl' => route('config-files.backup'),
            'restoreUrl' => route('config-files.restore'),
            'csrf' => csrf_token(),
        ];
    @endphp

    <div x-data="configEditor(@js($cfg))" class="space-y-5 pb-16 lg:pb-0">
        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-4 sm:p-6 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#d29922]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-[#d29922]/30 to-[#f0883e]/10 border border-[#d29922]/40 flex items-center justify-center text-[#d29922] shadow-lg shadow-[#d29922]/10 shrink-0"><x-icon name="settings" class="w-6 h-6" /></div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-lg sm:text-2xl font-bold text-[#e6edf3] tracking-tight">Конфигурация сервера</h1>
                            @if ($connected)
                                <span class="badge badge-green flex items-center gap-1.5 py-0.5 text-[10px] sm:text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                                    FTP подключен
                                </span>
                            @else
                                <span class="badge badge-red flex items-center gap-1.5 py-0.5 text-[10px] sm:text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f85149]"></span>
                                    FTP отключен
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] sm:text-xs text-[#8b949e] mt-0.5 truncate sm:whitespace-normal">
                            Визуальный и текстовый редактор <code class="text-[#58a6ff] mono">ServerSettings.ini</code>, <code class="text-[#3fb950] mono">EconomyOverride.json</code> и др.
                        </p>
                    </div>
                </div>

                {{-- Быстрые метрики --}}
                <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 text-xs">
                    <div class="px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center justify-between sm:justify-start gap-2">
                        <span class="text-[#8b949e]">Файлов:</span>
                        <span class="font-bold text-[#e6edf3]">{{ count($files) }}</span>
                    </div>
                    <div class="px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center justify-between sm:justify-start gap-2">
                        <span class="text-[#8b949e]">Бэкапов:</span>
                        <span class="font-bold text-[#e6edf3]" x-text="backups.length">{{ count($backups) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Предупреждения / Ошибки FTP ===== --}}
        @if (!$enabled)
            <div class="p-3.5 rounded-xl border border-[#d29922]/50 bg-[#3a2f1a]/40 text-[#d29922] text-xs sm:text-sm flex items-start gap-2.5">
                <x-icon name="alert-triangle" class="w-5 h-5 text-amber-400" />
                <div>
                    <div class="font-semibold">FTP не настроен</div>
                    <div class="text-[11px] sm:text-xs text-[#d29922]/90 mt-0.5">
                        Укажите параметры подключения к FTP серверу в настройках хоста, чтобы редактировать файлы конфигурации SCUM.
                    </div>
                </div>
            </div>
        @elseif (!$connected)
            <div class="p-3.5 rounded-xl border border-[#f85149]/50 bg-[#3a1a1a]/40 text-[#f85149] text-xs sm:text-sm flex items-start gap-2.5">
                <x-icon name="alert-triangle" class="w-5 h-5 text-red-400" />
                <div>
                    <div class="font-semibold">Не удалось подключиться по FTP</div>
                    <div class="text-[11px] sm:text-xs text-[#f85149]/90 mt-0.5">{{ $error ?? 'Проверьте логин, пароль и IP-адрес FTP сервера в настройках.' }}</div>
                </div>
            </div>
        @elseif (!$configDir)
            <div class="p-3.5 rounded-xl border border-[#d29922]/50 bg-[#3a2f1a]/40 text-[#d29922] text-xs sm:text-sm flex items-start gap-2.5">
                <x-icon name="alert-triangle" class="w-5 h-5 text-amber-400" />
                <div>
                    <div class="font-semibold">Каталог конфигураций не найден</div>
                    <div class="text-[11px] sm:text-xs text-[#d29922]/90 mt-0.5">
                        Автопоиск не обнаружил файлы в каталоге <code class="mono bg-black/30 px-1 py-0.5 rounded">SCUM/Saved/Config/WindowsServer</code>.
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== Уведомление об операции ===== --}}
        <div x-show="notice" x-cloak x-transition
             class="p-3 sm:p-4 rounded-xl border text-xs sm:text-sm flex items-center justify-between gap-3 shadow-lg"
             :class="notice && notice.ok ? 'border-[#2ea043]/50 bg-[#1a3a1f]/60 text-[#3fb950]' : 'border-[#f85149]/50 bg-[#3a1a1a]/60 text-[#f85149]'">
            <div class="flex items-center gap-2">
                <template x-if="notice && notice.ok"><x-icon name="check" class="w-4 h-4 text-emerald-400" /></template><template x-if="notice && !notice.ok"><x-icon name="alert-triangle" class="w-4 h-4 text-amber-400" /></template>
                <span class="font-medium" x-text="notice ? notice.text : ''"></span>
            </div>
            <button type="button" @click="notice = null" class="text-xs opacity-70 hover:opacity-100 p-1 cursor-pointer"><x-icon name="x" class="w-3.5 h-3.5" /></button>
        </div>

        @if ($connected)
            {{-- ========================================================= --}}
            {{-- МОБИЛЬНЫЙ СЕЛЕКТОР ФАЙЛОВ (ТОЛЬКО НА СМАРТФОНАХ/ПЛАНШЕТАХ) --}}
            {{-- ========================================================= --}}
            <div class="lg:hidden panel p-3 sm:p-4 rounded-2xl border border-[#30363d] bg-[#161b22] space-y-3 shadow-md">
                <div class="flex items-center justify-between gap-2 cursor-pointer" @click="mobileFilesOpen = !mobileFilesOpen">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="text-xl shrink-0" x-html="getFileIcon(current ? current.name : '')"></span>
                        <div class="min-w-0">
                            <div class="text-[10px] text-[#8b949e] uppercase font-bold tracking-wider">Текущий файл:</div>
                            <div class="mono text-xs font-bold text-[#e6edf3] truncate" x-text="current ? current.name : 'Выберите файл из списка...'"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-xs btn-accent flex items-center gap-1 shrink-0 py-1.5 px-2.5">
                        <span x-text="mobileFilesOpen ? 'Скрыть' : 'Сменить файл'"></span>
                        <span :class="mobileFilesOpen ? 'rotate-180' : 'rotate-0'" class="transition-transform duration-200 inline-block"><x-icon name="chevron-down" class="w-3.5 h-3.5" /></span>
                    </button>
                </div>

                {{-- Раскрывающийся список файлов на мобильных --}}
                <div x-show="mobileFilesOpen" x-cloak x-transition class="pt-3 border-t border-[#30363d] space-y-3">
                    {{-- Поиск по файлам --}}
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-[#8b949e]"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                        <input type="text" x-model="fileFilter" placeholder="Поиск файла..."
                               class="input pl-8 text-xs py-1.5 bg-[#0d1117] border-[#30363d]">
                        <button type="button" x-show="fileFilter" @click="fileFilter = ''" class="absolute right-2.5 top-2 text-xs text-[#8b949e]"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>

                    {{-- Категории --}}
                    <div class="flex items-center gap-1 p-1 rounded-xl bg-[#0d1117] border border-[#30363d] overflow-x-auto text-[11px]">
                        <button type="button" @click="fileCategory = 'all'" class="px-2 py-1 rounded-lg font-medium whitespace-nowrap"
                                :class="fileCategory === 'all' ? 'bg-[#21262d] text-[#e6edf3]' : 'text-[#8b949e]'">Все</button>
                        <button type="button" @click="fileCategory = 'main'" class="px-2 py-1 rounded-lg font-medium whitespace-nowrap"
                                :class="fileCategory === 'main' ? 'bg-[#d29922]/20 text-[#d29922]' : 'text-[#8b949e]'"><x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1.5" /> Главные</button>
                        <button type="button" @click="fileCategory = 'ini'" class="px-2 py-1 rounded-lg font-medium whitespace-nowrap"
                                :class="fileCategory === 'ini' ? 'bg-[#1f6feb]/20 text-[#58a6ff]' : 'text-[#8b949e]'">INI</button>
                        <button type="button" @click="fileCategory = 'json'" class="px-2 py-1 rounded-lg font-medium whitespace-nowrap"
                                :class="fileCategory === 'json' ? 'bg-[#2ea043]/20 text-[#3fb950]' : 'text-[#8b949e]'">JSON</button>
                        <button type="button" @click="fileCategory = 'engine'" class="px-2 py-1 rounded-lg font-medium whitespace-nowrap"
                                :class="fileCategory === 'engine' ? 'bg-[#21262d] text-[#e6edf3]' : 'text-[#8b949e]'">Движок</button>
                    </div>

                    {{-- Список файлов для мобильных --}}
                    <div class="space-y-1 max-h-60 overflow-y-auto pr-1">
                        <template x-for="f in filteredFiles" :key="f.name">
                            <button type="button" @click="open(f)"
                                    class="w-full text-left flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg border transition-all"
                                    :class="isOpen(f) ? 'border-[#58a6ff] bg-[#1f6feb]/15' : 'border-[#30363d] bg-[#0d1117]'">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-sm shrink-0" x-html="getFileIcon(f.name)"></span>
                                    <span class="mono text-xs text-[#e6edf3] truncate" x-text="f.name"></span>
                                </div>
                                <span class="badge mono text-[9px]" :class="getFileBadge(f.name).cls" x-text="getFileBadge(f.name).text"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ===== Основная сетка файлов и редактора ===== --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- ========================================================= --}}
                {{-- ЛЕВАЯ КОЛОНКА: Навигатор по файлам (ТОЛЬКО НА ДЕСКТОПЕ)   --}}
                {{-- ========================================================= --}}
                <div class="hidden lg:flex panel p-5 lg:col-span-4 rounded-2xl flex-col gap-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <x-icon name="file-text" class="w-5 h-5 text-[#58a6ff]" />
                            <h2 class="font-bold text-[#e6edf3] text-base">Файлы сервера</h2>
                        </div>
                        <span class="badge badge-gray mono text-[11px]" x-text="filteredFiles.length + ' из ' + files.length">
                            {{ count($files) }}
                        </span>
                    </div>

                    {{-- Поиск по списку файлов --}}
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-[#8b949e]"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                        <input type="text" x-model="fileFilter" placeholder="Поиск файла конфигурации..."
                               class="input pl-8 text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        <button type="button" x-show="fileFilter" @click="fileFilter = ''" class="absolute right-2.5 top-2 text-xs text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>

                    {{-- Категории файлов (Табы) --}}
                    <div class="flex items-center gap-1 p-1 rounded-xl bg-[#0d1117] border border-[#30363d] overflow-x-auto text-xs">
                        <button type="button" @click="fileCategory = 'all'"
                                class="px-2.5 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer"
                                :class="fileCategory === 'all' ? 'bg-[#21262d] text-[#e6edf3] shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                            Все
                        </button>
                        <button type="button" @click="fileCategory = 'main'"
                                class="px-2.5 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer flex items-center gap-1"
                                :class="fileCategory === 'main' ? 'bg-[#d29922]/20 text-[#d29922] border border-[#d29922]/40 shadow-sm' : 'text-[#8b949e] hover:text-[#d29922]'">
                            <x-icon name="star" class="w-3.5 h-3.5 text-[#e3b341] inline-block mr-1.5" /> Главные
                        </button>
                        <button type="button" @click="fileCategory = 'ini'"
                                class="px-2.5 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer"
                                :class="fileCategory === 'ini' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border border-[#1f6feb]/40 shadow-sm' : 'text-[#8b949e] hover:text-[#58a6ff]'">
                            INI
                        </button>
                        <button type="button" @click="fileCategory = 'json'"
                                class="px-2.5 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer"
                                :class="fileCategory === 'json' ? 'bg-[#2ea043]/20 text-[#3fb950] border border-[#2ea043]/40 shadow-sm' : 'text-[#8b949e] hover:text-[#3fb950]'">
                            JSON
                        </button>
                        <button type="button" @click="fileCategory = 'engine'"
                                class="px-2.5 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer"
                                :class="fileCategory === 'engine' ? 'bg-[#21262d] text-[#e6edf3] shadow-sm' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                            Движок
                        </button>
                    </div>

                    {{-- Список файлов --}}
                    @if (count($files) === 0)
                        <div class="text-sm text-[#8b949e] text-center py-10 bg-[#0d1117]/50 rounded-xl border border-[#30363d]/50">
                            Файлы не найдены в каталоге.
                        </div>
                    @else
                        <div class="space-y-1.5 max-h-[580px] overflow-y-auto pr-1">
                            <template x-for="f in filteredFiles" :key="f.name">
                                <button type="button" @click="open(f)"
                                        class="w-full text-left flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl border transition-all cursor-pointer group"
                                        :class="isOpen(f)
                                            ? 'border-[#58a6ff] bg-gradient-to-r from-[#1f6feb]/20 to-[#1f6feb]/5 shadow-md shadow-[#1f6feb]/10 ring-1 ring-[#58a6ff]/40'
                                            : 'border-[#30363d]/80 bg-[#0d1117] hover:border-[#484f58] hover:bg-[#161b22]'">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="text-base shrink-0" x-html="getFileIcon(f.name)"></span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="mono text-[13px] font-medium text-[#e6edf3] truncate group-hover:text-[#58a6ff] transition-colors"
                                                      x-text="f.name"></span>
                                                <span x-show="isOpen(f) && isDirty"
                                                      class="w-2 h-2 rounded-full bg-[#d29922] animate-pulse shrink-0"
                                                      title="Есть несохранённые изменения"></span>
                                            </div>
                                            <div class="text-[10px] text-[#8b949e] flex items-center gap-2 mt-0.5">
                                                <span x-text="fmtSize(f.size)"></span>
                                                <span x-show="isMainFile(f.name)" class="text-[#d29922]"><span class="inline-flex items-center gap-1"><x-icon name="sparkles" class="w-3 h-3 text-amber-400" /> Важный</span></span>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge mono text-[9px] px-1.5 py-0.5 rounded-md border shrink-0"
                                          :class="getFileBadge(f.name).cls"
                                          x-text="getFileBadge(f.name).text"></span>
                                </button>
                            </template>
                        </div>
                    @endif

                    {{-- Справка внизу сайдбара --}}
                    <div class="pt-3 border-t border-[#21262d] flex items-start gap-2 text-[11px] text-[#8b949e] leading-relaxed">
                        <x-icon name="info" class="w-3.5 h-3.5 text-amber-400" />
                        <span>При сохранении файла автоматически создаётся резервная копия. Изменения вступают в силу после перезапуска сервера.</span>
                    </div>
                </div>

                {{-- ========================================================= --}}
                {{-- ПРАВАЯ КОЛОНКА: Универсальный Редактор                    --}}
                {{-- ========================================================= --}}
                <div class="panel p-3.5 sm:p-5 md:p-6 lg:col-span-8 rounded-2xl flex flex-col gap-4 sm:gap-5 min-h-[560px]">

                    {{-- Состояние: Файл не выбран --}}
                    <template x-if="!current">
                        <div class="flex flex-col items-center justify-center text-center py-20 my-auto">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#21262d] border border-[#30363d] flex items-center justify-center text-2xl sm:text-3xl mb-3 shadow-inner">
                                <x-icon name="file-text" class="w-6 h-6" />
                            </div>
                            <h3 class="text-base sm:text-lg font-bold text-[#e6edf3]">Файл конфигурации не выбран</h3>
                            <p class="text-xs sm:text-sm text-[#8b949e] max-w-md mt-1 mb-4">
                                Выберите файл из списка выше или слева, чтобы открыть визуальный редактор.
                            </p>
                            @if (count($files) > 0)
                                <div class="flex items-center gap-2 flex-wrap justify-center">
                                    <template x-for="f in files.filter(f => isMainFile(f.name)).slice(0, 3)" :key="f.name">
                                        <button type="button" @click="open(f)" class="btn btn-sm flex items-center gap-1.5">
                                            <span x-html="getFileIcon(f.name)"></span>
                                            <span x-text="f.name"></span>
                                        </button>
                                    </template>
                                </div>
                            @endif
                        </div>
                    </template>

                    {{-- Состояние: Файл открыт --}}
                    <template x-if="current">
                        <div class="flex flex-col gap-4 sm:gap-5">

                            {{-- Панель заголовка файла и режимов --}}
                            <div class="flex flex-col gap-3 pb-3 sm:pb-4 border-b border-[#30363d]">
                                {{-- 1-я строка: Имя файла, размер, бейджи --}}
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-[#21262d] border border-[#30363d] flex items-center justify-center text-base sm:text-xl shrink-0">
                                            <span x-html="getFileIcon(current.name)"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="mono text-sm sm:text-lg font-bold text-[#e6edf3] truncate" x-text="current.name"></span>
                                                <span x-show="isDirty"
                                                      class="badge badge-yellow flex items-center gap-1 py-0.5 text-[10px]">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#d29922] animate-ping"></span>
                                                    Не сохранено
                                                </span>
                                            </div>
                                            <div class="text-[10px] sm:text-[11px] text-[#8b949e] flex items-center gap-1.5 mt-0.5">
                                                <span x-text="fmtSize(current.size)"></span>
                                                <span>•</span>
                                                <span class="mono" x-text="current.encoding === 'utf-16le' ? 'UTF-16LE' : 'UTF-8'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Главная кнопка Сохранить (на десктопе) --}}
                                    <div class="hidden sm:flex items-center gap-2">
                                        <button type="button" class="btn btn-primary flex items-center gap-1.5 py-1.5 px-4 font-semibold text-xs shadow-md shadow-[#238636]/20"
                                                :disabled="busy" @click="save()">
                                            <span x-show="!busy" class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить</span>
                                            <span x-show="busy" class="animate-pulse">Сохранение…</span>
                                            <span class="text-[10px] opacity-75 hidden md:inline">(Ctrl+S)</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- 2-я строка: Переключатель режимов (Визуальный / Код / Diff) --}}
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                    <div class="flex items-center p-1 rounded-xl bg-[#0d1117] border border-[#30363d] w-full sm:w-auto">
                                        <button type="button" @click="setMode('visual')"
                                                class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="mode === 'visual' ? 'bg-[#1f6feb] text-white shadow-md shadow-[#1f6feb]/30' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                                            <x-icon name="sparkles" class="w-3.5 h-3.5 inline mr-1 text-sky-400" />
                                            <span>Визуальный</span>
                                        </button>
                                        <button type="button" @click="setMode('raw')"
                                                class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="mode === 'raw' ? 'bg-[#238636] text-white shadow-md shadow-[#238636]/30' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                                            <x-icon name="terminal" class="w-3.5 h-3.5 inline mr-1 text-emerald-400" />
                                            <span>Код</span>
                                        </button>
                                        <button type="button" @click="setMode('diff')"
                                                class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="mode === 'diff' ? 'bg-[#d29922] text-black font-bold shadow-md shadow-[#d29922]/30' : 'text-[#8b949e] hover:text-[#e6edf3]'">
                                            <x-icon name="search" class="w-3.5 h-3.5 inline mr-1 text-purple-400" />
                                            <span>Diff</span>
                                        </button>
                                    </div>

                                    {{-- Быстрые действия --}}
                                    <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                        <button type="button" @click="copyContent()" class="btn btn-xs flex items-center gap-1">
                                            <span class="inline-flex items-center gap-1.5"><x-icon name="copy" class="w-3.5 h-3.5" /> Копировать</span>
                                        </button>
                                        <button type="button" @click="createBackupNow()" :disabled="busy" class="btn btn-xs flex items-center gap-1">
                                            <span class="inline-flex items-center gap-1.5"><x-icon name="package" class="w-3.5 h-3.5" /> Бэкап</span>
                                        </button>
                                        <button type="button" x-show="isDirty" @click="resetChanges()" class="btn btn-xs text-[#f85149] hover:bg-[#da3633]/20 border-[#f85149]/40 flex items-center gap-1">
                                            <span>↩️</span> Сброс
                                        </button>
                                        <template x-if="fileType === 'json'">
                                            <button type="button" @click="formatJson()" class="btn btn-xs">Форматировать</button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- ================================================= --}}
                            {{-- РЕЖИМ 1: ВИЗУАЛЬНЫЙ РЕДАКТОР (Для человека)      --}}
                            {{-- ================================================= --}}
                            <div x-show="mode === 'visual'" class="space-y-4 sm:space-y-5">

                                {{-- --- ВИД 1.1: ДЛЯ INI ФАЙЛОВ (ServerSettings.ini и др.) --- --}}
                                <template x-if="fileType === 'ini'">
                                    <div class="space-y-4 sm:space-y-5">
                                        {{-- Поиск параметров внутри активного файла --}}
                                        <div class="flex flex-col sm:flex-row gap-2.5 items-center justify-between">
                                            <div class="relative w-full sm:max-w-md">
                                                <span class="absolute left-3 top-2.5 text-[#8b949e]"><x-icon name="search" class="w-3.5 h-3.5" /></span>
                                                <input type="text" x-model="searchQuery"
                                                       placeholder="Поиск параметров..."
                                                       class="input pl-8 text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                                                <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 text-xs text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                                            </div>

                                            <div class="flex items-center justify-between w-full sm:w-auto gap-2 text-xs">
                                                <button type="button" @click="expandAllSections()" class="btn btn-xs">Развернуть</button>
                                                <button type="button" @click="collapseAllSections()" class="btn btn-xs">Свернуть</button>
                                                <button type="button" @click="showAddSectionModal = true" class="btn btn-xs btn-accent">+ Секция</button>
                                            </div>
                                        </div>

                                        {{-- Быстрая навигация по секциям (Чипсы с горизонтальным скроллом) --}}
                                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                                            <template x-for="sec in iniSections" :key="sec.name">
                                                <a :href="'#sec-' + sec.name"
                                                   class="px-2.5 py-1.5 rounded-lg bg-[#0d1117] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff] transition-all shrink-0 flex items-center gap-1.5">
                                                    <span x-text="sec.icon || 'file-text'"></span>
                                                    <span x-text="sec.title || sec.name"></span>
                                                    <span class="text-[10px] opacity-60" x-text="'(' + sec.items.length + ')'"></span>
                                                </a>
                                            </template>
                                        </div>

                                        {{-- Список секций и их параметров --}}
                                        <div class="space-y-3.5">
                                            <template x-for="(sec, secIdx) in filteredSections" :key="sec.name">
                                                <div :id="'sec-' + sec.name" class="rounded-xl border border-[#30363d] bg-[#0d1117]/60 overflow-hidden transition-all shadow-sm">
                                                    {{-- Заголовок секции --}}
                                                    <div class="px-3.5 py-2.5 sm:px-4 sm:py-3 bg-[#161b22] border-b border-[#30363d]/80 flex items-center justify-between gap-2.5 cursor-pointer select-none"
                                                         @click="toggleSection(sec.name)">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <span class="text-base sm:text-lg shrink-0" x-text="sec.icon || 'file-text'"></span>
                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="font-bold text-[#e6edf3] text-xs sm:text-sm" x-text="sec.title || sec.name"></span>
                                                                    <span class="mono text-[10px] text-[#58a6ff] bg-[#1f6feb]/10 px-1.5 py-0.2 rounded border border-[#1f6feb]/20" x-text="'[' + sec.name + ']'"></span>
                                                                    <span class="badge badge-gray text-[9px]" x-text="sec.items.length"></span>
                                                                </div>
                                                                <div class="text-[10px] sm:text-[11px] text-[#8b949e] truncate mt-0.5" x-text="sec.desc"></div>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center gap-1.5 shrink-0">
                                                            <button type="button" @click.stop="openAddParam(sec.name)"
                                                                    class="btn btn-xs hover:bg-[#21262d] text-[#58a6ff] text-[10px] sm:text-xs" title="Добавить параметр">
                                                                +
                                                            </button>
                                                            <span class="text-xs text-[#8b949e] transition-transform duration-200"
                                                                  :class="sec.collapsed ? '-rotate-90' : 'rotate-0'" class="inline-block transition-transform duration-200"><x-icon name="chevron-down" class="w-3.5 h-3.5" /></span>
                                                        </div>
                                                    </div>

                                                    {{-- Тело секции (Список параметров) --}}
                                                    <div x-show="!sec.collapsed" class="p-3 sm:p-4 space-y-3">
                                                        <template x-if="sec.items.length === 0">
                                                            <div class="text-xs text-[#8b949e] text-center py-4 italic">
                                                                В этой секции нет параметров.
                                                            </div>
                                                        </template>

                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            <template x-for="(item, itemIdx) in sec.items" :key="item.key">
                                                                <div class="p-3 rounded-xl border border-[#30363d]/70 bg-[#161b22]/70 hover:border-[#484f58] transition-all flex flex-col justify-between gap-2">
                                                                    <div class="flex items-start justify-between gap-2">
                                                                        <div class="min-w-0">
                                                                            <div class="font-semibold text-xs text-[#e6edf3] truncate" x-text="item.title || item.key"></div>
                                                                            <div class="mono text-[10px] text-[#8b949e] truncate" x-text="item.key"></div>
                                                                        </div>
                                                                        <button type="button" @click="removeParam(secIdx, itemIdx)"
                                                                                class="text-[11px] text-[#8b949e] hover:text-[#f85149] p-1 rounded hover:bg-[#3a1a1a]"
                                                                                title="Удалить параметр"><x-icon name="x" class="w-3.5 h-3.5 text-[#f85149]" /></button>
                                                                    </div>

                                                                    <div x-show="item.desc" class="text-[10px] sm:text-[11px] text-[#8b949e] leading-snug" x-text="item.desc"></div>

                                                                    {{-- Контрол ввода по типу данных --}}
                                                                    <div class="pt-1.5 border-t border-[#21262d]">
                                                                        {{-- 1. Boolean (Переключатель) --}}
                                                                        <template x-if="item.type === 'boolean'">
                                                                            <div class="flex items-center justify-between gap-2 py-0.5">
                                                                                <span class="text-xs font-medium" :class="item.value ? 'text-[#3fb950]' : 'text-[#8b949e]'"
                                                                                      x-text="item.value ? 'Вкл (True)' : 'Выкл (False)'"></span>
                                                                                <label class="toggle">
                                                                                    <input type="checkbox" x-model="item.value" @change="onParamChange()">
                                                                                    <span class="toggle-slider"></span>
                                                                                </label>
                                                                            </div>
                                                                        </template>

                                                                        {{-- 2. Number (Числовой инпут со степпером) --}}
                                                                        <template x-if="item.type === 'number'">
                                                                            <div class="flex items-center gap-1.5">
                                                                                <input type="number" x-model="item.value" @input="onParamChange()"
                                                                                       :step="item.step || 1" :min="item.min" :max="item.max"
                                                                                       class="input text-xs py-1.5 px-2.5 mono bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                                                                            </div>
                                                                        </template>

                                                                        {{-- 3. Select (Выпадающий список) --}}
                                                                        <template x-if="item.type === 'select'">
                                                                            <select x-model="item.value" @change="onParamChange()"
                                                                                    class="input text-xs py-1.5 px-2.5 mono bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                                                                                <template x-for="opt in item.options" :key="opt">
                                                                                    <option :value="opt" x-text="opt"></option>
                                                                                </template>
                                                                            </select>
                                                                        </template>

                                                                        {{-- 4. Multiline Text (Многострочный текст) --}}
                                                                        <template x-if="item.type === 'multiline'">
                                                                            <textarea x-model="item.value" @input="onParamChange()" rows="2"
                                                                                      class="input text-xs py-1.5 px-2.5 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] leading-relaxed resize-y"></textarea>
                                                                        </template>

                                                                        {{-- 5. String (Строковый инпут) --}}
                                                                        <template x-if="item.type !== 'boolean' && item.type !== 'number' && item.type !== 'select' && item.type !== 'multiline'">
                                                                            <input type="text" x-model="item.value" @input="onParamChange()"
                                                                                   class="input text-xs py-1.5 px-2.5 mono bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- --- ВИД 1.2: ДЛЯ СПИСКОВ (AdminUsers.ini, Whitelist.ini, BanList.ini) --- --}}
                                <template x-if="fileType === 'list'">
                                    <div class="space-y-4">
                                        <div class="p-3.5 sm:p-4 rounded-xl border border-[#30363d] bg-[#161b22]/70">
                                            <h3 class="font-bold text-[#e6edf3] text-xs sm:text-sm mb-1">Управление списком пользователей (SteamID64)</h3>
                                            <p class="text-[11px] sm:text-xs text-[#8b949e]">
                                                Каждая запись представляет собой 17-значный SteamID игрока (например, <code class="mono text-[#58a6ff]">76561198012345678</code>).
                                            </p>

                                            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                                                <input type="text" x-model="newListItem" @keydown.enter.prevent="addListItem()"
                                                       placeholder="SteamID64 (17 цифр)..."
                                                       class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff] flex-1">
                                                <button type="button" @click="addListItem()" class="btn btn-primary text-xs py-2 px-4 whitespace-nowrap">
                                                    + Добавить
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Список участников --}}
                                        <div class="panel p-3.5 sm:p-4 rounded-xl">
                                            <div class="flex items-center justify-between gap-2 mb-2.5">
                                                <span class="text-xs font-semibold text-[#e6edf3]">Записей: <span class="text-[#58a6ff]" x-text="listItems.length"></span></span>
                                            </div>

                                            <div class="space-y-1.5 max-h-[400px] overflow-y-auto pr-1">
                                                <template x-for="(item, idx) in listItems" :key="idx">
                                                    <div class="flex items-center justify-between gap-2 p-2 rounded-lg border border-[#30363d] bg-[#0d1117]">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <x-icon name="user" class="w-3.5 h-3.5 text-[#8b949e]" />
                                                            <span class="mono text-xs font-semibold text-[#e6edf3] truncate" x-text="item.value"></span>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 shrink-0">
                                                            <button type="button" @click="navigator.clipboard.writeText(item.value); window.toast && window.toast('SteamID скопирован', 'info')"
                                                                    class="btn btn-xs"><x-icon name="copy" class="w-3 h-3 text-[#8b949e]" /></button>
                                                            <button type="button" @click="removeListItem(idx)" class="btn btn-xs text-[#f85149]"><x-icon name="x" class="w-3 h-3" /></button>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- --- ВИД 1.3: ДЛЯ JSON ФАЙЛОВ --- --}}
                                <template x-if="fileType === 'json'">
                                    <div class="space-y-3">
                                        <div class="p-3 rounded-xl border border-[#30363d] bg-[#161b22]/70 flex items-center justify-between gap-2">
                                            <span class="text-xs text-[#8b949e]">Структура JSON конфигурации:</span>
                                            <span x-show="isJsonValid" class="badge badge-green text-[10px]"><x-icon name="check-circle" class="w-3 h-3 text-[#3fb950] inline-block mr-1" /> Валидный JSON</span>
                                            <span x-show="!isJsonValid" class="badge badge-red text-[10px] inline-flex items-center gap-1"><x-icon name="alert-triangle" class="w-3 h-3" /> Ошибка</span>
                                        </div>

                                        <div class="p-3 rounded-xl border border-[#30363d] bg-[#0d1117] font-mono text-xs overflow-x-auto max-h-[480px]">
                                            <pre class="text-[#e6edf3] leading-relaxed" x-text="content"></pre>
                                        </div>
                                    </div>
                                </template>

                                {{-- --- ВИД 1.4: ДЛЯ ДРУГИХ ТЕКСТОВЫХ ФАЙЛОВ --- --}}
                                <template x-if="fileType === 'text'">
                                    <div class="space-y-2">
                                        <textarea x-model="content" spellcheck="false"
                                                  class="input mono text-xs leading-relaxed bg-[#0d1117] border-[#30363d] w-full"
                                                  style="min-height: 440px; resize: vertical; white-space: pre;"></textarea>
                                    </div>
                                </template>
                            </div>

                            {{-- ================================================= --}}
                            {{-- РЕЖИМ 2: ИСХОДНЫЙ КОД (Текстовый редактор)        --}}
                            {{-- ================================================= --}}
                            <div x-show="mode === 'raw'" class="space-y-2.5">
                                <div class="relative rounded-xl border border-[#30363d] bg-[#0d1117] overflow-hidden focus-within:border-[#58a6ff] transition-colors">
                                    <textarea x-model="content" spellcheck="false"
                                              class="w-full bg-transparent p-3 sm:p-4 text-[#e6edf3] mono text-xs leading-relaxed outline-none"
                                              style="min-height: 480px; resize: vertical; white-space: pre; tab-size: 4; font-family: 'Consolas', 'Monaco', monospace;"></textarea>
                                </div>
                            </div>

                            {{-- ================================================= --}}
                            {{-- РЕЖИМ 3: DIFF (Сравнение изменений)               --}}
                            {{-- ================================================= --}}
                            <div x-show="mode === 'diff'" class="space-y-3">
                                <div class="rounded-xl border border-[#30363d] bg-[#0d1117] overflow-hidden font-mono text-xs max-h-[480px] overflow-y-auto">
                                    <template x-if="diffItems.length === 0">
                                        <div class="text-xs text-[#8b949e] text-center py-10">
                                            Изменений нет — файл совпадает с версией на сервере.
                                        </div>
                                    </template>

                                    <template x-for="(d, dIdx) in diffItems" :key="dIdx">
                                        <div class="flex items-start px-2.5 py-0.5 border-b border-[#21262d]/50 leading-relaxed font-mono"
                                             :class="{
                                                 'bg-[#2ea043]/15 text-[#3fb950]': d.type === 'add',
                                                 'bg-[#f85149]/15 text-[#f85149]': d.type === 'del',
                                                 'text-[#8b949e]': d.type === 'same'
                                             }">
                                            <span class="w-8 select-none text-[9px] opacity-40 shrink-0 text-right pr-1.5" x-text="d.num"></span>
                                            <span class="w-4 select-none font-bold shrink-0" x-text="d.type === 'add' ? '+' : (d.type === 'del' ? '-' : ' ')"></span>
                                            <span class="whitespace-pre-wrap break-all flex-1" x-text="d.line"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

            {{-- ========================================================= --}}
            {{-- СЕКЦИЯ: ЛОКАЛЬНЫЕ БЭКАПЫ И ИСТОРИЯ ВЕРСИЙ                --}}
            {{-- ========================================================= --}}
            <div class="panel p-4 sm:p-5 md:p-6 rounded-2xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 mb-3.5">
                    <div class="flex items-center gap-2">
                        <x-icon name="package" class="w-5 h-5 text-purple-400" />
                        <div>
                            <h2 class="font-bold text-[#e6edf3] text-sm sm:text-base">История локальных бэкапов</h2>
                            <div class="text-[11px] text-[#8b949e]">Снимки перед каждым сохранением на сервер</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer bg-[#0d1117] border border-[#30363d] px-2.5 py-1.5 rounded-lg select-none text-[11px]">
                            <input type="checkbox" x-model="backupFilterCurrent" class="rounded">
                            <span class="text-[#e6edf3]">Только текущий файл</span>
                        </label>
                        <input type="text" x-model="backupSearch" placeholder="Поиск по бэкапам..."
                               class="input text-xs py-1 px-2 bg-[#0d1117] border-[#30363d] w-36 sm:w-44">
                    </div>
                </div>

                @if (count($backups) === 0)
                    <div class="text-xs sm:text-sm text-[#8b949e] text-center py-8 bg-[#0d1117]/40 rounded-xl border border-[#30363d]/40">
                        Бэкапов пока нет — они создаются автоматически перед сохранением.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-[#30363d] bg-[#0d1117]">
                        <table class="table min-w-full text-xs">
                            <thead>
                                <tr class="bg-[#101623] border-b border-[#30363d]">
                                    <th class="py-2.5 px-3 text-left font-semibold text-[#8b949e] uppercase text-[10px]">Файл</th>
                                    <th class="py-2.5 px-3 text-left font-semibold text-[#8b949e] uppercase text-[10px]">Дата</th>
                                    <th class="py-2.5 px-3 text-right font-semibold text-[#8b949e] uppercase text-[10px]">Размер</th>
                                    <th class="py-2.5 px-3 text-right font-semibold text-[#8b949e] uppercase text-[10px]">Действие</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#21262d]">
                                <template x-for="b in filteredBackups" :key="b.path">
                                    <tr class="hover:bg-[#161b22]">
                                        <td class="py-2 px-3 font-medium text-[#e6edf3]">
                                            <div class="flex items-center gap-1.5">
                                                <span x-html="getFileIcon(b.file)"></span>
                                                <span class="mono text-xs truncate max-w-[140px] sm:max-w-none" x-text="b.file"></span>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 mono text-[#8b949e]" x-text="fmtStamp(b.stamp)"></td>
                                        <td class="py-2 px-3 text-right mono text-[#8b949e]" x-text="fmtSize(b.size)"></td>
                                        <td class="py-2 px-3 text-right">
                                            <button type="button" class="btn btn-xs hover:border-[#3fb950] text-[#3fb950] hover:bg-[#2ea043]/10"
                                                    :disabled="busy" @click="restore(b.path)">
                                                <span class="inline-flex items-center gap-1.5"><x-icon name="refresh-cw" class="w-3.5 h-3.5" /> Восстановить</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ========================================================= --}}
            {{-- ПЛАВАЮЩАЯ НИЖНЯЯ ПАНЕЛЬ СОХРАНЕНИЯ (НА МОБИЛЬНЫХ ЭКРАНАХ) --}}
            {{-- ========================================================= --}}
            <template x-if="current">
                <div class="fixed bottom-0 left-0 right-0 p-3 bg-[#101623]/95 border-t border-[#30363d] backdrop-blur-md z-40 lg:hidden flex items-center justify-between gap-2 shadow-2xl">
                    <div class="min-w-0 flex items-center gap-1.5">
                        <span class="text-base shrink-0" x-html="getFileIcon(current.name)"></span>
                        <div class="min-w-0">
                            <div class="mono text-xs font-bold text-[#e6edf3] truncate" x-text="current.name"></div>
                            <div class="text-[10px] text-[#8b949e] flex items-center gap-1">
                                <span x-show="isDirty" class="text-[#d29922] font-semibold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#d29922] animate-pulse"></span><span>Не сохранено</span></span>
                                <span x-show="!isDirty" class="text-[#3fb950]">Сохранено</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" class="btn btn-primary text-xs py-2 px-4 font-bold shadow-lg shadow-[#238636]/30 flex items-center gap-1.5"
                                :disabled="busy" @click="save()">
                            <span x-show="!busy" class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить</span>
                            <span x-show="busy" class="animate-pulse">Сохранение…</span>
                        </button>
                    </div>
                </div>
            </template>
        @endif

        {{-- ========================================================= --}}
        {{-- МОДАЛЬНЫЕ ОКНА                                            --}}
        {{-- ========================================================= --}}

        {{-- Модалка: Добавить параметр в INI --}}
        <div x-show="showAddParamModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
             @click.self="showAddParamModal = false">
            <div class="panel p-5 rounded-2xl w-full max-w-md border border-[#30363d] bg-[#161b22] shadow-2xl space-y-3.5">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-sm sm:text-base text-[#e6edf3]">Добавить параметр</h3>
                    <button type="button" @click="showAddParamModal = false" class="text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-4 h-4" /></button>
                </div>
                <div class="text-xs text-[#8b949e]">
                    В секцию <span class="mono font-bold text-[#58a6ff]" x-text="'[' + newParamSection + ']'"></span>
                </div>
                <div class="space-y-2.5">
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Имя параметра (Key)</label>
                        <input type="text" x-model="newParamKey" placeholder="например, MaxAllowedPuppets"
                               class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1">Значение (Value)</label>
                        <input type="text" x-model="newParamValue" placeholder="например, 64 или True"
                               class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showAddParamModal = false" class="btn btn-sm">Отмена</button>
                    <button type="button" @click="addCustomParam()" class="btn btn-sm btn-primary">Добавить</button>
                </div>
            </div>
        </div>

        {{-- Модалка: Добавить новую секцию в INI --}}
        <div x-show="showAddSectionModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
             @click.self="showAddSectionModal = false">
            <div class="panel p-5 rounded-2xl w-full max-w-md border border-[#30363d] bg-[#161b22] shadow-2xl space-y-3.5">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-sm sm:text-base text-[#e6edf3]">Создать новую секцию</h3>
                    <button type="button" @click="showAddSectionModal = false" class="text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-4 h-4" /></button>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#8b949e] mb-1">Имя секции</label>
                    <input type="text" x-model="newSectionName" placeholder="например, CustomSettings"
                           class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showAddSectionModal = false" class="btn btn-sm">Отмена</button>
                    <button type="button" @click="addNewSection()" class="btn btn-sm btn-accent">Создать секцию</button>
                </div>
            </div>
        </div>

    </div>
@endsection
