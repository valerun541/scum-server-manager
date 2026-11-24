@php
    $active = request()->route()?->getName() ?? '';
    $standalone = in_array($active, ['servers.index', 'servers.list']);
    $isLogsActive = str_starts_with($active, 'logs.');
    $isPluginsActive = str_starts_with($active, 'plugins.') || $active === 'packs.index';
@endphp
<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SCUM Server Manager') — Панель управления</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{
    pluginsOpen: {{ $isPluginsActive ? 'true' : 'true' }},
    logsOpen: {{ $isLogsActive ? 'true' : 'false' }}
}" class="bg-[#090d14] text-[#e6edf3] font-sans antialiased selection:bg-[#1f6feb]/30 selection:text-[#58a6ff]">

    <div class="flex min-h-screen">
        @if (!$standalone)
        <!-- ===== Mobile Top Bar ===== -->
        <div class="lg:hidden fixed top-0 inset-x-0 z-30 flex items-center justify-between px-4 py-2.5 bg-[#090d14]/95 backdrop-blur-md border-b border-[#21262d] shadow-lg">
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-sm px-2.5 py-1 text-base bg-[#161b22] border-[#30363d] text-[#e6edf3]" aria-label="Меню" @click="$store.ui.toggleSidebar()">
                    <x-icon name="sidebar" class="w-4 h-4" />
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-md bg-gradient-to-br from-[#1f6feb] to-[#238636] flex items-center justify-center text-xs font-bold text-white shadow-sm">
                        S
                    </div>
                    <span class="font-bold text-sm tracking-tight text-[#e6edf3]">SCUM Panel</span>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button type="button" @click="$store.ui.openCmdPalette()" class="p-1.5 rounded-lg bg-[#161b22] border border-[#30363d] text-[#8b949e] hover:text-[#e6edf3]">
                    <x-icon name="search" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Backdrop for mobile -->
        <div class="lg:hidden fixed inset-0 z-40 bg-black/80 backdrop-blur-sm"
             x-show="$store.ui.sidebarOpen"
             x-transition.opacity
             @click="$store.ui.closeSidebar()"
             x-cloak></div>

        <!-- ===== Премиальный Сайдбар (Поддержка Mini-Collapse режима) ===== -->
        <aside class="app-sidebar fixed inset-y-0 left-0 z-40 shrink-0 border-r border-[#21262d] bg-[#0c1017] flex flex-col h-screen overflow-hidden shadow-2xl lg:w-64"
               :class="[
                   $store.ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                   $store.ui.sidebarCollapsed ? 'is-collapsed !w-20' : ''
               ]">
            
            {{-- Шапка сайдбара --}}
            <div class="p-3.5 border-b border-[#21262d] flex items-center justify-between gap-2.5 bg-[#0e131d]/80 shrink-0 h-16">
                <a href="{{ route('servers.index') }}" class="flex items-center gap-3 min-w-0 group" :class="$store.ui.sidebarCollapsed ? 'justify-center w-full' : ''">
                    <div class="w-9 h-9 shrink-0 rounded-xl bg-gradient-to-br from-[#1f6feb] via-[#238636] to-[#d29922] p-[1.5px] shadow-lg shadow-[#1f6feb]/20 group-hover:shadow-[#58a6ff]/40 transition-all">
                        <div class="w-full h-full rounded-[10px] bg-[#090d14] flex items-center justify-center text-sm font-black text-[#58a6ff]">
                            SC
                        </div>
                    </div>
                    <div class="min-w-0" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity>
                        <div class="font-bold text-sm text-[#e6edf3] tracking-tight group-hover:text-[#58a6ff] transition-colors truncate">SCUM Manager</div>
                        <div class="text-[10px] text-[#3fb950] font-medium flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                            Online Panel v2.0
                        </div>
                    </div>
                </a>

                {{-- Кнопка закрытия на смартфонах --}}
                <button type="button" @click="$store.ui.closeSidebar()" class="lg:hidden text-[#8b949e] hover:text-[#e6edf3] p-1.5 rounded-lg hover:bg-[#161b22]">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            {{-- Навигация с 4 чистыми хабами --}}
            <nav class="flex-1 p-2.5 space-y-1 overflow-y-auto" @click="window.innerWidth < 1024 && $store.ui.closeSidebar()">
                
                {{-- 1. СЕКЦИЯ: МОНИТОРИНГ --}}
                <div class="sidebar-section" x-show="!$store.ui.sidebarCollapsed">
                    <span>Мониторинг</span>
                </div>
                @foreach ($monitoringNav as $item)
                    <a href="{{ route($item['route']) }}"
                       @class(['sidebar-nav-item', 'active' => $active === $item['route']])
                       :class="$store.ui.sidebarCollapsed ? 'justify-center px-0' : ''"
                       title="{{ $item['name'] }}">
                        <span class="sidebar-nav-ico">
                            <x-icon :name="$item['icon']" class="w-4 h-4" />
                        </span>
                        <span class="truncate flex-1" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity>{{ $item['name'] }}</span>
                    </a>
                @endforeach

                {{-- 2. СЕКЦИЯ: УПРАВЛЕНИЕ И ИНСТРУМЕНТЫ --}}
                <div class="sidebar-section mt-3" x-show="!$store.ui.sidebarCollapsed">
                    <span>Управление</span>
                </div>
                @foreach ($managementNav as $item)
                    <a href="{{ route($item['route']) }}"
                       @class(['sidebar-nav-item', 'active' => $active === $item['route']])
                       :class="$store.ui.sidebarCollapsed ? 'justify-center px-0' : ''"
                       title="{{ $item['name'] }}">
                        <span class="sidebar-nav-ico">
                            <x-icon :name="$item['icon']" class="w-4 h-4" />
                        </span>
                        <span class="truncate flex-1" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity>{{ $item['name'] }}</span>
                    </a>
                @endforeach

                {{-- 3. СЕКЦИЯ: ПЛАГИНЫ --}}
                @if (!empty($pluginPages))
                    <div class="sidebar-section mt-3 cursor-pointer select-none"
                         @click.stop="pluginsOpen = !pluginsOpen"
                         x-show="!$store.ui.sidebarCollapsed">
                        <div class="flex items-center gap-1.5">
                            <span>Плагины</span>
                            <span class="badge badge-blue text-[9px] px-1.5 py-0">{{ count($pluginPages) }}</span>
                        </div>
                        <span class="text-[10px] text-[#6e7681] transition-transform duration-200" :class="pluginsOpen ? 'rotate-0' : '-rotate-90'">
                            <x-icon name="chevron-down" class="w-3 h-3" />
                        </span>
                    </div>

                    <div x-show="pluginsOpen || $store.ui.sidebarCollapsed" class="space-y-0.5 pt-0.5">
                        @foreach ($pluginPages as $item)
                            <a href="{{ route($item['route']) }}"
                               @class(['sidebar-nav-item', 'active' => $active === $item['route']])
                               :class="$store.ui.sidebarCollapsed ? 'justify-center px-0' : ''"
                               title="{{ $item['name'] }}">
                                <span class="sidebar-nav-ico">
                                    <x-icon :name="$item['icon']" class="w-4 h-4" />
                                </span>
                                <span class="truncate flex-1" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity>{{ $item['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- 4. СЕКЦИЯ: ЛОГИ СЕРВЕРА --}}
                @if (!empty($logLinks))
                    <div class="sidebar-section mt-3 cursor-pointer select-none"
                         @click.stop="logsOpen = !logsOpen"
                         x-show="!$store.ui.sidebarCollapsed">
                        <div class="flex items-center gap-1.5">
                            <span>Логи сервера</span>
                            <span class="badge badge-gray text-[9px] px-1.5 py-0">{{ count($logLinks) }}</span>
                        </div>
                        <span class="text-[10px] text-[#6e7681] transition-transform duration-200" :class="logsOpen ? 'rotate-0' : '-rotate-90'">
                            <x-icon name="chevron-down" class="w-3 h-3" />
                        </span>
                    </div>

                    <div x-show="logsOpen || $store.ui.sidebarCollapsed" class="space-y-0.5 pt-0.5">
                        @foreach ($logLinks as $l)
                            <a href="{{ route($l['route']) }}"
                               @class(['sidebar-nav-item', 'active' => $active === $l['route']])
                               :class="$store.ui.sidebarCollapsed ? 'justify-center px-0' : ''"
                               title="{{ $l['name'] }}">
                                <span class="sidebar-nav-ico">
                                    <x-icon :name="$l['icon']" class="w-4 h-4" />
                                </span>
                                <span class="truncate flex-1 text-xs" x-show="!$store.ui.sidebarCollapsed" x-transition.opacity>{{ $l['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

            </nav>

            {{-- Подвал сайдбара: переключатель ширины + профиль --}}
            <div class="p-3 border-t border-[#21262d] bg-[#090d14]/90 shrink-0 space-y-2">
                {{-- Кнопка сворачивания сайдбара (Desktop) --}}
                <button type="button"
                        @click="$store.ui.toggleCollapse()"
                        class="hidden lg:flex items-center gap-2.5 w-full px-2 py-1.5 rounded-lg text-xs text-[#8b949e] hover:text-[#e6edf3] hover:bg-[#161b22] transition-colors"
                        :class="$store.ui.sidebarCollapsed ? 'justify-center' : 'justify-between'">
                    <span class="flex items-center gap-2" x-show="!$store.ui.sidebarCollapsed">
                        <x-icon name="sidebar" class="w-4 h-4" />
                        <span>Свернуть меню</span>
                    </span>
                    <span x-show="$store.ui.sidebarCollapsed">
                        <x-icon name="sidebar" class="w-4 h-4" />
                    </span>
                    <span class="p-1 rounded bg-[#21262d] text-[#8b949e]" x-show="!$store.ui.sidebarCollapsed"><x-icon name="arrow-right" class="w-3 h-3 rotate-180" /></span>
                </button>

                <div class="flex items-center justify-between gap-2 px-1 pt-1 border-t border-[#21262d]" x-show="!$store.ui.sidebarCollapsed">
                    <a href="{{ route('account.settings') }}" class="flex items-center gap-2.5 min-w-0 group hover:opacity-95 transition-all flex-1" title="Настройки учетной записи">
                        <div class="w-7 h-7 rounded-lg bg-[#161b22] border border-[#30363d] group-hover:border-[#58a6ff]/50 flex items-center justify-center text-xs text-[#58a6ff] shrink-0">
                            <x-icon name="shield" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-[#e6edf3] group-hover:text-[#58a6ff] truncate transition-colors">Администратор</div>
                            <div class="text-[10px] text-[#8b949e] group-hover:text-[#58a6ff]/80 truncate">Безопасность & IP</div>
                        </div>
                    </a>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" title="Выйти" class="p-1.5 text-[#8b949e] hover:text-[#f85149] hover:bg-[#da3633]/15 rounded-lg transition-colors">
                            <x-icon name="logout" class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        @endif

        <!-- ===== Основная рабочая область ===== -->
        <div class="app-main min-h-screen flex-1 flex flex-col min-w-0 lg:ml-64"
             :class="{ 'sidebar-collapsed': $store.ui.sidebarCollapsed && !{{ $standalone ? 'true' : 'false' }} }"
             @if ($standalone) style="margin-left: 0 !important;" @endif>

            @if (!$standalone)
            <!-- ===== Современный Header / Topbar ===== -->
            <header class="sticky top-0 z-20 h-16 border-b border-[#21262d] bg-[#0c1017]/90 backdrop-blur-md px-4 sm:px-6 flex items-center justify-between gap-4">
                
                {{-- Левая часть: Breadcrumb + Быстрый поиск Ctrl+K --}}
                <div class="flex items-center gap-4 min-w-0">
                    <div class="hidden sm:flex items-center gap-2 text-xs font-medium text-[#8b949e]">
                        <a href="{{ route('servers.index') }}" class="hover:text-[#e6edf3] transition-colors">Панель</a>
                        <span>/</span>
                        <span class="text-[#e6edf3] font-semibold truncate max-w-[200px]">@yield('title', 'Дашборд')</span>
                    </div>

                    {{-- Кнопка Command Palette (Ctrl+K) --}}
                    <button type="button"
                            @click="$store.ui.openCmdPalette()"
                            class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-[#161b22] border border-[#30363d]/80 text-xs text-[#8b949e] hover:text-[#e6edf3] hover:border-[#58a6ff]/40 transition-all shadow-sm group">
                        <x-icon name="search" class="w-3.5 h-3.5 text-[#8b949e] group-hover:text-[#58a6ff] transition-colors" />
                        <span class="hidden md:inline">Быстрый переход...</span>
                        <span class="flex items-center gap-0.5 text-[10px] font-mono bg-[#21262d] px-1.5 py-0.5 rounded border border-[#30363d] text-[#c9d1d9]">
                            <span>Ctrl</span><span>K</span>
                        </span>
                    </button>
                </div>

                {{-- Правая часть: Статусы сервера + Быстрые действия --}}
                <div class="flex items-center gap-3 shrink-0">
                    
                    {{-- Индикатор статуса сервера --}}
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-[#161b22] border border-[#30363d] text-xs">
                        <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-pulse"></span>
                        <span class="text-[#8b949e] text-[11px]">Сервер:</span>
                        <span class="font-semibold text-[#e6edf3] text-[11px]">Online</span>
                    </div>

                    {{-- Быстрый переход в RCON --}}
                    <a href="{{ route('rcon.index') }}"
                       class="btn btn-sm bg-[#161b22] border-[#30363d] hover:border-[#58a6ff]/50 hover:text-[#58a6ff] text-xs gap-1.5"
                       title="Открыть RCON консоль">
                        <x-icon name="terminal" class="w-3.5 h-3.5" />
                        <span class="hidden md:inline">Консоль</span>
                    </a>

                    {{-- Ссылка на профиль / настройки безопасности --}}
                    <a href="{{ route('account.settings') }}"
                       class="w-8 h-8 rounded-xl bg-[#161b22] border border-[#30363d] hover:border-[#58a6ff]/50 flex items-center justify-center text-[#8b949e] hover:text-[#58a6ff] transition-all"
                       title="Белый список IP и настройки">
                        <x-icon name="settings" class="w-4 h-4" />
                    </a>
                </div>
            </header>
            @endif

            <!-- ===== Контент страницы ===== -->
            <main class="flex-1 p-4 sm:p-6 md:p-8 max-w-[1700px] w-full mx-auto">
                @hasSection('header-actions')
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-[#e6edf3]">@yield('title', 'Панель управления')</h1>
                            @hasSection('subtitle')
                                <p class="text-xs md:text-sm text-[#8b949e] mt-1">@yield('subtitle')</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">@yield('header-actions')</div>
                    </div>
                @endif

                <div class="w-full">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <!-- ===== Компонент Command Palette (Ctrl+K) ===== -->
    <x-command-palette />

    <!-- ===== Toast-уведомления ===== -->
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none" x-cloak>
        <template x-for="t in $store.toasts.items" :key="t.id">
            <div x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="pointer-events-auto p-4 rounded-xl shadow-2xl border backdrop-blur-md flex items-start gap-3"
                 :class="{
                     'bg-[#161b22]/95 border-[#2ea043]/60 text-[#3fb950] shadow-[#2ea043]/10': t.type === 'success',
                     'bg-[#161b22]/95 border-[#da3633]/60 text-[#f85149] shadow-[#da3633]/10': t.type === 'error',
                     'bg-[#161b22]/95 border-[#d29922]/60 text-[#d29922] shadow-[#d29922]/10': t.type === 'warning',
                     'bg-[#161b22]/95 border-[#388bfd]/60 text-[#58a6ff] shadow-[#388bfd]/10': t.type === 'info'
                 }">
                <div class="shrink-0 mt-0.5">
                    <template x-if="t.type === 'success'"><x-icon name="check" class="w-4 h-4 text-[#3fb950]" /></template>
                    <template x-if="t.type === 'error'"><x-icon name="alert-triangle" class="w-4 h-4 text-[#f85149]" /></template>
                    <template x-if="t.type === 'warning'"><x-icon name="alert-triangle" class="w-4 h-4 text-[#d29922]" /></template>
                    <template x-if="t.type === 'info'"><x-icon name="info" class="w-4 h-4 text-[#58a6ff]" /></template>
                </div>
                <div class="flex-1 text-sm font-medium leading-relaxed break-words text-[#e6edf3]" x-text="t.message"></div>
                <button type="button" @click="$store.toasts.remove(t.id)" class="text-[#8b949e] hover:text-[#e6edf3] shrink-0 leading-none"><x-icon name="x" class="w-3.5 h-3.5" /></button>
            </div>
        </template>
    </div>

    <!-- Инициализация Flash-сообщений Laravel в Toasts -->
    <script>
        document.addEventListener('alpine:init', () => {
            @if (session('success'))
                Alpine.store('toasts').add(@json(session('success')), 'success');
            @endif
            @if (session('error'))
                Alpine.store('toasts').add(@json(session('error')), 'error', 5000);
            @endif
            @if (session('info'))
                Alpine.store('toasts').add(@json(session('info')), 'info');
            @endif
            @if (session('warning'))
                Alpine.store('toasts').add(@json(session('warning')), 'warning', 4500);
            @endif
        });
    </script>
    @stack('scripts')

    <!-- ===== Универсальное модальное окно ===== -->
    <div id="modal-root" x-show="$store.ui.modalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4" x-cloak>
        <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" @click="$store.ui.closeModal()"></div>
        <div class="relative w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-hidden"
             @keydown.escape.window="$store.ui.closeModal()">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#30363d] bg-[#0d1117]/60">
                <div id="modal-title" class="font-bold text-[#e6edf3]" x-text="$store.ui.modalTitle">…</div>
                <button type="button" class="btn btn-sm p-1.5 rounded-lg text-[#8b949e] hover:text-[#e6edf3]" @click="$store.ui.closeModal()">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <div id="modal-body" class="flex-1 overflow-y-auto p-4 md:p-5 bg-[#0d1117]" x-html="$store.ui.modalBody"></div>
            <div class="px-5 py-3 border-t border-[#30363d] bg-[#0d1117]/60 flex justify-end gap-2">
                <button type="button" class="btn btn-sm" @click="$store.ui.closeModal()">Закрыть</button>
            </div>
        </div>
    </div>
</body>
</html>
