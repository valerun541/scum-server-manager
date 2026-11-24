@extends('layouts.app')

@section('title', 'Настройки учетной записи и безопасность')

@section('content')
<div class="space-y-6 w-full" x-data="{ activeTab: 'whitelist' }">

    {{-- Навигация по вкладкам (Unified Segmented Pill Bar) --}}
    <div class="flex items-center justify-between gap-3 mb-6 flex-wrap">
        <div class="flex items-center gap-1.5 p-1.5 bg-[#0c1017] border border-[#21262d] rounded-2xl overflow-x-auto custom-scrollbar shadow-sm">
            <button type="button" @click="activeTab = 'whitelist'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0"
                    :class="activeTab === 'whitelist' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' : 'text-[#8b949e] border-transparent hover:bg-[#161b22] hover:text-[#e6edf3]'">
                <x-icon name="shield" class="w-3.5 h-3.5" />
                <span>Белый список IP</span>
                <span class="badge badge-gray text-[10px]">{{ count($entries) }}</span>
            </button>

            <button type="button" @click="activeTab = '2fa'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0"
                    :class="activeTab === '2fa' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' : 'text-[#8b949e] border-transparent hover:bg-[#161b22] hover:text-[#e6edf3]'">
                <x-icon name="lock" class="w-3.5 h-3.5" />
                <span>Защита 2FA</span>
                @if ($totpEnabled)
                    <span class="badge badge-green text-[10px]">ВКЛ</span>
                @else
                    <span class="badge badge-yellow text-[10px]">ВЫКЛ</span>
                @endif
            </button>

            <button type="button" @click="activeTab = 'profile'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0"
                    :class="activeTab === 'profile' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' : 'text-[#8b949e] border-transparent hover:bg-[#161b22] hover:text-[#e6edf3]'">
                <x-icon name="key" class="w-3.5 h-3.5" />
                <span>Логин и Пароль</span>
            </button>

            <button type="button" @click="activeTab = 'session'"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border shrink-0"
                    :class="activeTab === 'session' ? 'bg-[#1f6feb]/20 text-[#58a6ff] border-[#388bfd]/40 shadow-sm' : 'text-[#8b949e] border-transparent hover:bg-[#161b22] hover:text-[#e6edf3]'">
                <x-icon name="info" class="w-3.5 h-3.5" />
                <span>Сессии</span>
                <span class="badge badge-gray text-[10px]">{{ count($activeSessions ?? []) }}</span>
            </button>
        </div>

        <div>
            <a href="{{ route('servers.index') }}" class="btn btn-sm bg-[#161b22] border-[#30363d] hover:border-[#58a6ff]/40 text-xs text-[#8b949e] hover:text-[#e6edf3] gap-2 transition-colors">
                <x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
                <span>Вернуться к серверам</span>
            </a>
        </div>
    </div>

    {{-- ===== ВКЛАДКА 1: БЕЛЫЙ СПИСОК IP ===== --}}
    <div x-show="activeTab === 'whitelist'" class="space-y-6">

        {{-- 1. Баннер статуса фильтрации --}}
        <div class="relative overflow-hidden rounded-2xl border {{ $enabled ? 'border-[#2ea043]/40 bg-gradient-to-r from-[#0d1e13] via-[#0c1619] to-[#0d121c]' : 'border-[#d29922]/40 bg-gradient-to-r from-[#1c160c] via-[#141210] to-[#0d121c]' }} p-5 sm:p-6 shadow-2xl">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 relative z-10">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl {{ $enabled ? 'bg-[#2ea043]/15 border border-[#2ea043]/40 text-[#3fb950]' : 'bg-[#d29922]/15 border border-[#d29922]/40 text-[#d29922]' }} flex items-center justify-center shrink-0 shadow-lg">
                        <x-icon name="shield" class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="text-lg sm:text-xl font-bold text-[#e6edf3] tracking-tight">Фильтрация доступа по IP</h2>
                            @if ($enabled)
                                <span class="badge badge-green">ВКЛЮЧЕНА (АКТИВНА)</span>
                            @else
                                <span class="badge badge-yellow">ОТКЛЮЧЕНА (ДОСТУП ОТКРЫТ)</span>
                            @endif
                        </div>
                        <p class="text-xs sm:text-sm text-[#8b949e] leading-relaxed max-w-3xl">
                            Защита веб-панели и точки входа: доступ разрешён строго доверенным IP-адресам и подсетям. Запросы из неавторизованных сетей блокируются с кодом <code class="text-[#f85149] font-mono">403 Forbidden</code>.
                        </p>
                    </div>
                </div>

                <form action="{{ route('account.whitelist.toggle') }}" method="POST" class="shrink-0">
                    @csrf
                    <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                    @if ($enabled)
                        <button type="submit" class="btn btn-danger font-semibold px-5 py-2.5 shadow-lg shadow-[#da3633]/20 w-full sm:w-auto">
                            <x-icon name="power" class="w-4 h-4" />
                            <span>Отключить фильтрацию</span>
                        </button>
                    @else
                        <button type="submit" class="btn btn-primary font-semibold px-5 py-2.5 shadow-lg shadow-[#238636]/20 w-full sm:w-auto">
                            <x-icon name="check" class="w-4 h-4" />
                            <span>Включить фильтрацию</span>
                        </button>
                    @endif
                </form>
            </div>
        </div>

        {{-- 2. Основная сетка: Таблица (8 col) + Боковая панель (4 col) --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            
            {{-- Левая колонка (8 из 12) --}}
            <div class="xl:col-span-8 space-y-6 min-w-0">

                {{-- Форма добавления доверенного IP / CIDR --}}
                <div class="rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-6 shadow-xl">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-[#21262d]">
                        <div class="w-8 h-8 rounded-xl bg-[#1f6feb]/15 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center shrink-0">
                            <x-icon name="plus" class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#e6edf3]">Добавить доверенный IP или CIDR-диапазон</h3>
                            <p class="text-xs text-[#8b949e]">Одиночный адрес IPv4/IPv6 или маска корпоративной подсети</p>
                        </div>
                    </div>

                    <form action="{{ route('account.whitelist.add') }}" method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        @csrf
                        <div class="md:col-span-6">
                            <label class="block text-xs text-[#8b949e] mb-1.5 font-medium">IP адрес или CIDR <span class="text-[#f85149]">*</span></label>
                            <input type="text" name="ip" required placeholder="Например: 95.31.181.58 или 192.168.1.0/24"
                                   class="input text-sm font-mono" />
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs text-[#8b949e] mb-1.5 font-medium">Комментарий / Назначение</label>
                            <input type="text" name="label" placeholder="Офис, Домашний VPN, Администратор"
                                   class="input text-sm" />
                        </div>

                        <div class="md:col-span-2 flex items-end">
                            <button type="submit" class="btn btn-primary w-full py-2.5 text-sm font-semibold shadow-md shadow-[#238636]/15">
                                <span>Добавить</span>
                            </button>
                        </div>
                    </form>
                    <div class="mt-3 text-[11px] text-[#6e7681] flex items-center gap-1.5">
                        <x-icon name="info" class="w-3.5 h-3.5 text-[#58a6ff] shrink-0" />
                        <span>Поддерживаются форматы: одиночный <code class="text-[#8b949e]">95.31.181.58</code>, локалхост <code class="text-[#8b949e]">127.0.0.1</code> или подсеть <code class="text-[#8b949e]">95.31.0.0/16</code>.</span>
                    </div>
                </div>

                {{-- Таблица разрешенных адресов --}}
                <div class="rounded-2xl border border-[#21262d] bg-[#161b22] overflow-hidden shadow-xl">
                    <div class="p-4 sm:p-5 border-b border-[#21262d] flex items-center justify-between gap-3 bg-[#0d121c]/60">
                        <div class="flex items-center gap-2.5">
                            <x-icon name="shield" class="w-4 h-4 text-[#58a6ff]" />
                            <h3 class="font-bold text-sm text-[#e6edf3]">Разрешенные IP-адреса и подсети</h3>
                            <span class="badge badge-gray text-xs font-mono">{{ count($entries) }}</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="table w-full">
                            <thead>
                                <tr class="bg-[#090d14]/70">
                                    <th class="w-12 text-center text-xs">#</th>
                                    <th class="text-xs">IP / Подсеть</th>
                                    <th class="text-xs">Описание</th>
                                    <th class="text-xs">Дата добавления</th>
                                    <th class="text-xs">Последняя активность</th>
                                    <th class="w-20 text-right text-xs">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#21262d]">
                                @forelse ($entries as $index => $item)
                                    @php
                                        $isThisCurrent = ($item['ip'] === $currentIp);
                                    @endphp
                                    <tr class="transition-colors {{ $isThisCurrent ? 'bg-[#1f6feb]/10 hover:bg-[#1f6feb]/15' : 'hover:bg-[#1c212c]' }}">
                                        <td class="text-center text-xs text-[#6e7681]">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-sm font-semibold text-[#58a6ff]">{{ $item['ip'] ?? '' }}</span>
                                                @if ($isThisCurrent)
                                                    <span class="badge badge-blue text-[9px]">Вы здесь</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-xs text-[#e6edf3] font-medium">
                                            {{ $item['label'] ?? '—' }}
                                        </td>
                                        <td class="text-xs text-[#8b949e]">
                                            @if (!empty($item['created_at']))
                                                {{ \Carbon\Carbon::parse($item['created_at'])->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-xs text-[#8b949e]">
                                            @if (!empty($item['last_seen_at']))
                                                <span class="text-[#3fb950] font-medium flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] shrink-0"></span>
                                                    {{ \Carbon\Carbon::parse($item['last_seen_at'])->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}
                                                </span>
                                            @else
                                                <span class="text-[#6e7681]">Нет данных</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <form action="{{ route('account.whitelist.delete') }}" method="POST" onsubmit="return confirm('Удалить {{ $item['ip'] }} из белого списка?');">
                                                @csrf
                                                <input type="hidden" name="ip" value="{{ $item['ip'] }}">
                                                <button type="submit" class="btn btn-xs btn-danger text-xs px-2.5 py-1" title="Удалить правило">
                                                    <x-icon name="trash" class="w-3.5 h-3.5 text-[#f85149]" />
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-10 text-center text-[#8b949e]">
                                            <div class="w-14 h-14 rounded-2xl bg-[#21262d] border border-[#30363d] text-[#6e7681] flex items-center justify-center mx-auto mb-3 shadow-md">
                                                <x-icon name="shield" class="w-7 h-7" />
                                            </div>
                                            <div class="font-semibold text-sm text-[#e6edf3]">Белый список пуст</div>
                                            <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">
                                                При включении фильтрации доступ будет разрешён только для локальных запросов (127.0.0.1).
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- Правая колонка: Виджеты статуса, текущего IP и CLI команд (4 из 12) --}}
            <div class="xl:col-span-4 space-y-6">

                {{-- Виджет текущего IP клиента --}}
                <div class="rounded-2xl border border-[#21262d] bg-[#161b22] p-5 shadow-xl space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#21262d] border border-[#30363d] text-[#58a6ff] flex items-center justify-center shrink-0">
                            <x-icon name="server" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs text-[#8b949e] font-medium">Ваш текущий IP-адрес:</div>
                            <div class="text-base font-bold text-[#58a6ff] font-mono truncate">{{ $currentIp }}</div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center justify-between">
                        <span class="text-xs text-[#8b949e]">Статус авторизации:</span>
                        @if ($isCurrentIpAllowed)
                            <span class="badge badge-green">В белом списке</span>
                        @else
                            <span class="badge badge-red">Не добавлен</span>
                        @endif
                    </div>

                    @if (!$isCurrentIpAllowed)
                        <form action="{{ route('account.whitelist.quickAdd') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-accent w-full py-2.5 text-xs font-semibold shadow-lg shadow-[#1f6feb]/20">
                                <x-icon name="plus" class="w-4 h-4" />
                                <span>Добавить мой IP в 1 клик</span>
                            </button>
                        </form>
                    @else
                        <div class="text-[11px] text-[#3fb950] flex items-center gap-1.5 font-medium">
                            <x-icon name="check" class="w-3.5 h-3.5 shrink-0" />
                            <span>Ваш адрес гарантированно имеет постоянный доступ к веб-панели.</span>
                        </div>
                    @endif
                </div>

                {{-- Виджет аналитики правил безопасности --}}
                <div class="rounded-2xl border border-[#21262d] bg-[#161b22] p-5 shadow-xl space-y-3">
                    <h4 class="text-xs font-bold text-[#8b949e] uppercase tracking-wider">Метрики безопасности</h4>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d]">
                            <div class="text-[11px] text-[#8b949e]">Всего правил</div>
                            <div class="text-xl font-black text-[#e6edf3] font-mono mt-0.5">{{ count($entries) }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d]">
                            <div class="text-[11px] text-[#8b949e]">Фильтр IP</div>
                            <div class="text-sm font-bold {{ $enabled ? 'text-[#3fb950]' : 'text-[#d29922]' }} mt-1">
                                {{ $enabled ? 'Активен' : 'Отключен' }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Экстренное управление через SSH (Artisan CLI) --}}
                <div class="rounded-2xl border border-[#21262d] bg-[#161b22] p-5 shadow-xl space-y-3">
                    <div class="flex items-center gap-2">
                        <x-icon name="terminal" class="w-4 h-4 text-[#58a6ff]" />
                        <h4 class="text-xs font-bold text-[#e6edf3] uppercase tracking-wider">Экстренное управление (CLI)</h4>
                    </div>
                    <p class="text-xs text-[#8b949e]">
                        Если доступ был случайно заблокирован, используйте терминал сервера по SSH:
                    </p>

                    <div class="space-y-2 text-[11px] font-mono">
                        <div class="p-2.5 rounded-xl bg-[#090d14] border border-[#21262d] text-[#58a6ff] flex items-center justify-between">
                            <span class="truncate">php artisan panel:ip-whitelist list</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-[#090d14] border border-[#21262d] text-[#3fb950] flex items-center justify-between">
                            <span class="truncate">php artisan panel:ip-whitelist add &lt;IP&gt;</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-[#090d14] border border-[#21262d] text-[#d29922] flex items-center justify-between">
                            <span class="truncate">php artisan panel:ip-whitelist disable</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    {{-- ===== ВКЛАДКА: ДВУХФАКТОРНАЯ АУТЕНТИФИКАЦИЯ (2FA) ===== --}}
    <div x-show="activeTab === '2fa'" class="space-y-6" x-cloak x-data="{
        setupStep: false,
        loadingQr: false,
        qrCode: '',
        secret: '',
        showCodes: false,
        fetchSetup() {
            this.loadingQr = true;
            this.setupStep = true;
            fetch('{{ route('account.2fa.setup') }}')
                .then(r => r.json())
                .then(d => {
                    this.qrCode = d.qr;
                    this.secret = d.secret;
                    this.loadingQr = false;
                })
                .catch(() => {
                    this.loadingQr = false;
                    alert('Ошибка загрузки данных настройки 2FA');
                });
        }
    }">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            
            {{-- Основной блок 2FA (7 col) --}}
            <div class="xl:col-span-7 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-7 shadow-xl space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-[#21262d] flex-wrap gap-3">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-[#21262d] border border-[#30363d] text-[#58a6ff] flex items-center justify-center shrink-0">
                            <x-icon name="shield" class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#e6edf3]">Двухфакторная защита (TOTP)</h3>
                            <p class="text-xs text-[#8b949e]">Защита учётной записи через Google Authenticator, Aegis или Bitwarden</p>
                        </div>
                    </div>
                    @if ($totpEnabled)
                        <span class="badge badge-green flex items-center gap-1.5 py-1 px-3">
                            <span class="w-2 h-2 rounded-full bg-[#3fb950] animate-pulse"></span>
                            <span>ВКЛЮЧЕНА (АКТИВНА)</span>
                        </span>
                    @else
                        <span class="badge badge-yellow flex items-center gap-1.5 py-1 px-3">
                            <span>ОТКЛЮЧЕНА</span>
                        </span>
                    @endif
                </div>

                @if ($totpEnabled)
                    {{-- 2FA уже включена --}}
                    <div class="p-4 rounded-xl bg-[#0d1e13] border border-[#2ea043]/30 space-y-3">
                        <div class="flex items-center gap-2 text-sm font-bold text-[#3fb950]">
                            <x-icon name="check" class="w-4 h-4" />
                            <span>Учётная запись надёжно защищена</span>
                        </div>
                        <p class="text-xs text-[#8b949e] leading-relaxed">
                            При каждом входе в систему запрашивается временный 6-значный одноразовый код (RFC 6238). Даже в случае компрометации пароля злоумышленник не сможет войти в панель.
                        </p>
                    </div>

                    {{-- Резервные коды восстановления --}}
                    @if (!empty($recoveryCodes))
                        <div class="p-4 rounded-xl bg-[#0d121c] border border-[#21262d] space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#e6edf3] flex items-center gap-1.5">
                                    <x-icon name="key" class="w-4 h-4 text-[#d29922]" />
                                    <span>Резервные коды восстановления ({{ count($recoveryCodes) }} шт.)</span>
                                </span>
                                <button type="button" @click="showCodes = !showCodes" class="btn btn-xs text-xs">
                                    <span x-text="showCodes ? 'Скрыть коды' : 'Показать коды'"></span>
                                </button>
                            </div>
                            <p class="text-[11px] text-[#8b949e]">
                                Используйте их, если потеряете доступ к приложению аутентификатора. Каждый код можно использовать один раз.
                            </p>
                            <div x-show="showCodes" class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 font-mono text-xs">
                                @foreach ($recoveryCodes as $rc)
                                    <div class="p-2 rounded-lg bg-[#161b22] border border-[#30363d] text-center text-[#58a6ff] select-all">
                                        {{ $rc }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Форма отключения 2FA --}}
                    <div class="pt-4 border-t border-[#21262d]">
                        <h4 class="text-xs font-bold text-[#f85149] uppercase tracking-wider mb-2">Отключение 2FA</h4>
                        <p class="text-xs text-[#8b949e] mb-3">Для деактивации двухфакторной защиты введите текущий пароль администратора:</p>
                        <form action="{{ route('account.2fa.disable') }}" method="POST" class="flex gap-2.5 max-w-md">
                            @csrf
                            <input type="password" name="password" required placeholder="Текущий пароль" class="input text-xs flex-1" />
                            <button type="submit" class="btn btn-danger text-xs font-semibold shrink-0">Отключить 2FA</button>
                        </form>
                    </div>
                @else
                    {{-- 2FA отключена --}}
                    <div class="space-y-4">
                        <div class="p-4 rounded-xl bg-[#1c160c] border border-[#d29922]/30 space-y-2">
                            <div class="text-xs font-bold text-[#d29922] flex items-center gap-1.5">
                                <x-icon name="alert-triangle" class="w-4 h-4" />
                                <span>Рекомендуется включить защиту</span>
                            </div>
                            <p class="text-xs text-[#8b949e] leading-relaxed">
                                Защитите панель управления сервером SCUM от подбора пароля. Для настройки потребуется смартфон с приложением Google Authenticator, Aegis, Authy или Bitwarden.
                            </p>
                        </div>

                        <div x-show="!setupStep">
                            <button type="button" @click="fetchSetup()" class="btn btn-primary text-xs py-2.5 px-5 font-bold shadow-lg shadow-[#238636]/20 flex items-center gap-2">
                                <x-icon name="shield" class="w-4 h-4" />
                                <span>Настроить двухфакторную аутентификацию</span>
                            </button>
                        </div>

                        {{-- Окно шагов активации --}}
                        <div x-show="setupStep" class="p-5 rounded-xl border border-[#30363d] bg-[#0d121c] space-y-5" x-cloak>
                            <div class="text-xs font-bold text-[#e6edf3] flex items-center justify-between pb-2 border-b border-[#21262d]">
                                <span>Шаг 1 из 2: Сканирование QR-кода</span>
                                <button type="button" @click="setupStep = false" class="text-[#8b949e] hover:text-[#e6edf3]"><x-icon name="x" class="w-4 h-4" /></button>
                            </div>

                            <div x-show="loadingQr" class="text-xs text-[#8b949e] py-8 text-center">
                                Генерация QR-кода...
                            </div>

                            <div x-show="!loadingQr" class="space-y-4">
                                <div class="flex flex-col sm:flex-row items-center gap-5">
                                    <div class="p-2.5 rounded-2xl bg-white shadow-lg shrink-0">
                                        <img :src="qrCode" alt="2FA QR Code" class="w-40 h-40" />
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <p class="text-[#e6edf3] font-semibold">1. Отсканируйте QR-код в приложении аутентификатора на смартфоне.</p>
                                        <p class="text-[#8b949e]">Или введите секретный ключ вручную:</p>
                                        <div class="p-2 rounded-lg bg-[#161b22] border border-[#30363d] font-mono text-[#58a6ff] text-xs font-bold tracking-wider select-all" x-text="secret"></div>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-[#21262d]">
                                    <form action="{{ route('account.2fa.enable') }}" method="POST" class="space-y-3">
                                        @csrf
                                        <label class="block text-xs font-semibold text-[#e6edf3]">
                                            Шаг 2: Введите 6-значный код из приложения для подтверждения
                                        </label>
                                        <div class="flex gap-2 max-w-xs">
                                            <input type="text" name="code" required maxlength="6" placeholder="000000"
                                                   class="input text-center font-mono text-base font-bold tracking-widest text-[#58a6ff] py-1.5" />
                                            <button type="submit" class="btn btn-primary text-xs font-bold px-4 shrink-0">
                                                Активировать
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Боковая панель преимуществ 2FA (5 col) --}}
            <div class="xl:col-span-5 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold text-[#e6edf3] uppercase tracking-wider">
                    <x-icon name="shield" class="w-4 h-4 text-[#58a6ff]" />
                    <span>Стандарт безопасности RFC 6238</span>
                </div>
                <p class="text-xs text-[#8b949e] leading-relaxed">
                    Двухфакторная защита генерирует временные криптографические ключи, меняющиеся каждые 30 секунд.
                </p>

                <div class="space-y-2.5 text-xs">
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Работает оффлайн без SMS и интернета на телефоне</span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Совместимость с любыми TOTP-приложениями</span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Резервные коды на случай утери смартфона</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ===== ВКЛАДКА 2: ЛОГИН И ПАРОЛЬ ===== --}}
    <div x-show="activeTab === 'profile'" class="space-y-6" x-cloak>
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            
            {{-- Форма учетных данных (7 col) --}}
            <div class="xl:col-span-7 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-7 shadow-xl space-y-5">
                <div class="flex items-center gap-3.5 pb-4 border-b border-[#21262d]">
                    <div class="w-10 h-10 rounded-xl bg-[#21262d] border border-[#30363d] text-[#58a6ff] flex items-center justify-center shrink-0">
                        <x-icon name="user" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#e6edf3]">Учетная запись администратора</h3>
                        <p class="text-xs text-[#8b949e]">Изменение логина и пароля для авторизации в панели SSM</p>
                    </div>
                </div>

                <form action="{{ route('account.profile.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">
                            Имя пользователя (Логин) <span class="text-[#f85149]">*</span>
                        </label>
                        <input type="text" name="username" value="{{ old('username', $username) }}" required
                               class="input text-sm font-semibold" placeholder="admin" />
                        @error('username')
                            <div class="text-xs text-[#f85149] mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="pt-2 border-t border-[#21262d]">
                        <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">
                            Текущий пароль <span class="text-[#f85149]">* (для подтверждения изменений)</span>
                        </label>
                        <input type="password" name="current_password" required
                               class="input text-sm" placeholder="••••••••" />
                        @error('current_password')
                            <div class="text-xs text-[#f85149] mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">
                                Новый пароль <span class="text-[#6e7681]">(опционально)</span>
                            </label>
                            <input type="password" name="new_password"
                                   class="input text-sm" placeholder="••••••••" />
                            @error('new_password')
                                <div class="text-xs text-[#f85149] mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1.5">
                                Подтверждение пароля
                            </label>
                            <input type="password" name="new_password_confirmation"
                                   class="input text-sm" placeholder="••••••••" />
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="btn btn-primary px-6 py-2.5 font-semibold text-sm shadow-lg shadow-[#238636]/20">
                            <x-icon name="check" class="w-4 h-4" />
                            <span>Сохранить изменения</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Рекомендации по безопасности (5 col) --}}
            <div class="xl:col-span-5 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold text-[#e6edf3] uppercase tracking-wider">
                    <x-icon name="shield" class="w-4 h-4 text-[#58a6ff]" />
                    <span>Стандарты безопасности пароля</span>
                </div>
                <p class="text-xs text-[#8b949e] leading-relaxed">
                    Для защиты панели управления сервером SCUM от подбора и атак методом грубой силы придерживайтесь корпоративных рекомендаций:
                </p>

                <div class="space-y-2.5 text-xs">
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Минимум 8-12 символов разного регистра</span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Наличие цифр и спецсимволов (!@#$%^&*)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center gap-2.5 text-[#c9d1d9]">
                        <x-icon name="check" class="w-4 h-4 text-[#3fb950] shrink-0" />
                        <span>Сочетание с фильтрацией белого списка IP</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ===== ВКЛАДКА 3: ТЕКУЩАЯ СЕССИЯ ===== --}}
    <div x-show="activeTab === 'session'" class="space-y-6" x-cloak>
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            
            {{-- Информация о сеансе (7 col) --}}
            <div class="xl:col-span-7 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-7 shadow-xl space-y-5">
                <div class="flex items-center gap-3.5 pb-4 border-b border-[#21262d]">
                    <div class="w-10 h-10 rounded-xl bg-[#21262d] border border-[#30363d] text-[#3fb950] flex items-center justify-center shrink-0">
                        <x-icon name="info" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#e6edf3]">Параметры текущей сессии</h3>
                        <p class="text-xs text-[#8b949e]">Информация о вашем браузере, IP-адресе и времени входа</p>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3.5 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center justify-between">
                        <span class="text-[#8b949e]">IP-адрес клиента:</span>
                        <span class="font-mono text-[#58a6ff] font-bold text-sm">{{ $currentIp }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center justify-between">
                        <span class="text-[#8b949e]">Статус авторизации:</span>
                        <span class="badge badge-green font-semibold">Активная сессия</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-[#0d121c] border border-[#21262d] flex flex-col gap-1.5">
                        <span class="text-[#8b949e]">Браузер (User-Agent):</span>
                        <span class="text-[#e6edf3] font-mono text-[11px] break-all">{{ request()->userAgent() }}</span>
                    </div>
                </div>

                {{-- Список активных сессий устройств --}}
                <div class="pt-4 border-t border-[#21262d] space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#e6edf3]">Активные сеансы входа ({{ count($activeSessions ?? []) }})</span>
                        @if (count($activeSessions ?? []) > 1)
                            <form action="{{ route('account.sessions.revokeOthers') }}" method="POST" onsubmit="return confirm('Завершить все остальные сеансы на других устройствах?')">
                                @csrf
                                <button type="submit" class="btn btn-xs bg-[#da3633]/15 text-[#f85149] border-[#da3633]/40 hover:bg-[#da3633]/30">
                                    Завершить другие сеансы
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="space-y-2">
                        @foreach ($activeSessions ?? [] as $s)
                            <div class="p-3 rounded-xl bg-[#0d121c] border border-[#21262d] flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#161b22] border border-[#30363d] flex items-center justify-center text-[#8b949e]">
                                        <x-icon name="monitor" class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[#e6edf3] flex items-center gap-2">
                                            <span>{{ $s['browser'] }} ({{ $s['platform'] }})</span>
                                            @if ($s['is_current'])
                                                <span class="badge badge-green text-[9px] py-0">Текущий</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-[#8b949e] font-mono mt-0.5">
                                            <span>IP: {{ $s['ip'] }}</span>
                                            <span class="text-[#484f58]">·</span>
                                            <span>Активность: {{ $s['last_activity'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4 flex justify-between items-center border-t border-[#21262d]">
                    <span class="text-xs text-[#8b949e]">Завершить текущий сеанс входа:</span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-danger text-xs py-2 px-4 shadow-md shadow-[#da3633]/15">
                            <x-icon name="logout" class="w-4 h-4" />
                            <span>Выйти из системы</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Панель безопасности сеанса (5 col) --}}
            <div class="xl:col-span-5 rounded-2xl border border-[#21262d] bg-[#161b22] p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold text-[#e6edf3] uppercase tracking-wider">
                    <x-icon name="lock" class="w-4 h-4 text-[#58a6ff]" />
                    <span>Защита сеансов</span>
                </div>
                <p class="text-xs text-[#8b949e] leading-relaxed">
                    Сессия защищена шифрованием cookie (AES-256), защитой от межсайтовой подделки запросов (CSRF), флагом <code class="text-[#58a6ff]">HttpOnly</code> и привязкой к IP.
                </p>

                <div class="p-3.5 rounded-xl bg-[#0d121c] border border-[#21262d] text-xs text-[#8b949e] space-y-2">
                    <div class="flex items-center justify-between">
                        <span>Срок жизни сессии:</span>
                        <span class="font-mono text-[#e6edf3] font-semibold">120 минут</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Флаг SameSite:</span>
                        <span class="font-mono text-[#e6edf3] font-semibold">Lax</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>CSRF-валидация:</span>
                        <span class="text-[#3fb950] font-semibold">Включена</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
