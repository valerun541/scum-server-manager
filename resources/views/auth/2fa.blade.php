<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>2FA Подтверждение — SCUM Server Manager</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6 bg-[#090d14] text-[#e6edf3] font-sans relative overflow-x-hidden selection:bg-[#1f6feb]/30 selection:text-[#58a6ff]">

    {{-- Фоновые градиентные сферы --}}
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#1f6feb]/15 blur-[120px] pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[500px] h-[500px] rounded-full bg-[#2ea043]/10 blur-[120px] pointer-events-none"></div>

    {{-- Основной контейнер 2FA --}}
    <div class="relative w-full max-w-md my-auto z-10">
        <div class="rounded-3xl border border-[#21262d] bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl shadow-black/80 overflow-hidden p-6 sm:p-9">
            
            {{-- Шапка --}}
            <div class="text-center mb-7">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb] via-[#388bfd] to-[#238636] p-[2px] shadow-lg shadow-[#1f6feb]/30 mx-auto mb-4">
                    <div class="w-full h-full rounded-[14px] bg-[#090d14] flex items-center justify-center text-[#58a6ff]">
                        <x-icon name="shield" class="w-7 h-7" />
                    </div>
                </div>

                <h1 class="text-2xl font-black text-[#e6edf3] tracking-tight">Двухфакторная защита</h1>
                <p class="text-xs sm:text-sm text-[#8b949e] mt-1.5 leading-relaxed">
                    Введите 6-значный код из Google Authenticator или резервный ключ восстановления
                </p>

                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-medium bg-[#161b22] border border-[#21262d] text-[#3fb950] mt-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                    <span>Защита TOTP RFC 6238</span>
                </div>
            </div>

            {{-- Ошибки валидации --}}
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

            {{-- Форма ввода кода --}}
            <form action="{{ route('login.2fa.post') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="code" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2 text-center">
                        Код аутентификатора
                    </label>
                    <div class="relative flex items-center rounded-xl border border-[#30363d] focus-within:border-[#58a6ff] focus-within:ring-2 focus-within:ring-[#58a6ff]/20 bg-[#0d121c] overflow-hidden transition-all shadow-sm">
                        <input type="text"
                               id="code" name="code"
                               placeholder="000000"
                               maxlength="12"
                               class="w-full bg-transparent py-3.5 px-4 text-center font-mono text-2xl font-black tracking-[0.25em] text-[#58a6ff] focus:outline-none placeholder:text-[#30363d]"
                               autofocus required autocomplete="one-time-code">
                    </div>
                    <p class="text-[11px] text-[#8b949e] text-center mt-2">
                        Или введите 8-значный резервный код (например, <code class="text-[#e6edf3] font-mono">A1B2-C3D4</code>)
                    </p>
                </div>

                {{-- Кнопка подтверждения --}}
                <div class="pt-2 space-y-2">
                    <button type="submit"
                            class="w-full min-h-[48px] bg-gradient-to-r from-[#1f6feb] to-[#238636] hover:from-[#388bfd] hover:to-[#2ea043] border border-[#388bfd]/50 text-white rounded-xl py-3 px-4 font-bold text-sm shadow-lg shadow-[#1f6feb]/20 hover:shadow-[#1f6feb]/35 transition-all duration-200 transform active:scale-[0.985] flex items-center justify-center gap-2.5 cursor-pointer">
                        <span>Подтвердить вход</span>
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>

                    <a href="{{ route('login') }}"
                       class="w-full py-2.5 rounded-xl border border-[#21262d] bg-[#161b22] hover:bg-[#21262d] text-xs text-[#8b949e] hover:text-[#e6edf3] font-medium transition-colors flex items-center justify-center gap-1.5">
                        <x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
                        <span>Вернуться к форме входа</span>
                    </a>
                </div>
            </form>
        </div>

        {{-- Копирайт футер --}}
        <div class="text-center text-[11px] sm:text-xs text-[#8b949e] mt-6 flex items-center justify-center gap-2">
            <span>SCUM Server Manager © {{ date('Y') }}</span>
            <span>•</span>
            <span class="text-[#3fb950] font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3fb950] animate-pulse"></span>
                <span>Защищённая сессия</span>
            </span>
        </div>
    </div>
</body>
</html>
