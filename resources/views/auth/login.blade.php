<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход в систему — SCUM Server Manager</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6 bg-[#090d14] text-[#e6edf3] font-sans relative overflow-x-hidden selection:bg-[#1f6feb]/30 selection:text-[#58a6ff]"
      x-data="{
          showPass: false,
          username: '{{ old('username', '') }}',
          password: ''
      }">

    {{-- Фоновые градиентные сферы --}}
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#1f6feb]/15 blur-[120px] pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[500px] h-[500px] rounded-full bg-[#2ea043]/10 blur-[120px] pointer-events-none"></div>

    {{-- Основной контейнер авторизации (Enterprise Clean Card) --}}
    <div class="relative w-full max-w-md my-auto z-10">
        <div class="rounded-3xl border border-[#21262d] bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl shadow-black/80 overflow-hidden p-6 sm:p-9">
            
            {{-- Шапка: Логотип и Название --}}
            <div class="text-center mb-7">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb] via-[#388bfd] to-[#238636] p-[2px] shadow-lg shadow-[#1f6feb]/30 mx-auto mb-4">
                    <div class="w-full h-full rounded-[14px] bg-[#090d14] flex items-center justify-center text-lg font-black text-[#58a6ff]">
                        SC
                    </div>
                </div>

                <h1 class="text-2xl font-black text-[#e6edf3] tracking-tight">SCUM Server Manager</h1>
                <p class="text-xs sm:text-sm text-[#8b949e] mt-1.5">Введите учётные данные для доступа к панели</p>

                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-medium bg-[#161b22] border border-[#21262d] text-[#3fb950] mt-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                    <span>Панель управления v2.0</span>
                </div>
            </div>

            {{-- Ошибки валидации (только при реальных ошибках) --}}
            @if ($errors->any())
                <div class="mb-5 p-3.5 rounded-xl border border-[#f85149]/40 bg-[#f85149]/10 text-[#f85149] text-xs flex items-start gap-2.5">
                    <x-icon name="x" class="w-4 h-4 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @session('error')
                <div class="mb-5 p-3.5 rounded-xl border border-[#f85149]/40 bg-[#f85149]/10 text-[#f85149] text-xs flex items-start gap-2.5">
                    <x-icon name="x" class="w-4 h-4 shrink-0 mt-0.5" />
                    <div>{{ $value }}</div>
                </div>
            @endsession

            {{-- Форма авторизации --}}
            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Поле логина --}}
                <div>
                    <label for="username" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2">
                        Имя пользователя
                    </label>
                    <div class="relative flex items-center rounded-xl border border-[#30363d] focus-within:border-[#58a6ff] focus-within:ring-2 focus-within:ring-[#58a6ff]/20 bg-[#0d121c] overflow-hidden transition-all shadow-sm">
                        <div class="pl-3.5 pr-2 text-[#8b949e] shrink-0">
                            <x-icon name="user" class="w-4 h-4" />
                        </div>
                        <input type="text"
                               id="username" name="username"
                               x-model="username"
                               placeholder="Введите логин"
                               class="w-full bg-transparent py-3 pr-4 text-sm text-[#e6edf3] focus:outline-none placeholder:text-[#484f58]"
                               autofocus required autocomplete="username">
                    </div>
                </div>

                {{-- Поле пароля --}}
                <div>
                    <label for="password" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2">
                        Пароль администратора
                    </label>
                    <div class="relative flex items-center rounded-xl border border-[#30363d] focus-within:border-[#58a6ff] focus-within:ring-2 focus-within:ring-[#58a6ff]/20 bg-[#0d121c] overflow-hidden transition-all shadow-sm">
                        <div class="pl-3.5 pr-2 text-[#8b949e] shrink-0">
                            <x-icon name="lock" class="w-4 h-4" />
                        </div>
                        <input :type="showPass ? 'text' : 'password'"
                               id="password" name="password"
                               x-model="password"
                               placeholder="Введите пароль"
                               class="w-full bg-transparent py-3 pr-12 text-sm text-[#e6edf3] focus:outline-none placeholder:text-[#484f58]"
                               required autocomplete="current-password">
                        <button type="button"
                                @click="showPass = !showPass"
                                class="absolute right-3 p-1.5 text-[#8b949e] hover:text-[#58a6ff] transition-colors rounded-lg hover:bg-[#161b22]"
                                title="Показать / скрыть пароль">
                            <span x-show="!showPass"><x-icon name="eye" class="w-4 h-4" /></span>
                            <span x-show="showPass"><x-icon name="eye-off" class="w-4 h-4" /></span>
                        </button>
                    </div>
                </div>

                {{-- Кнопка входа --}}
                <div class="pt-2">
                    <button type="submit"
                            class="w-full min-h-[48px] bg-gradient-to-r from-[#1f6feb] to-[#238636] hover:from-[#388bfd] hover:to-[#2ea043] border border-[#388bfd]/50 text-white rounded-xl py-3 px-4 font-bold text-sm shadow-lg shadow-[#1f6feb]/20 hover:shadow-[#1f6feb]/35 transition-all duration-200 transform active:scale-[0.985] flex items-center justify-center gap-2.5 cursor-pointer">
                        <span>Войти в панель</span>
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>
            </form>
        </div>

        {{-- Копирайт футер --}}
        <div class="text-center text-[11px] sm:text-xs text-[#8b949e] mt-6 flex items-center justify-center gap-2">
            <span>SCUM Server Manager © {{ date('Y') }}</span>
            <span>•</span>
            <span class="text-[#3fb950] font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                <span>Защищённая среда</span>
            </span>
        </div>
    </div>
</body>
</html>
