<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Доступ ограничен — SCUM Server Manager</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[#090d14] text-[#e6edf3] font-sans antialiased relative overflow-hidden">
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#da3633]/15 blur-[120px] pointer-events-none"></div>

    <div class="relative w-full max-w-md p-8 sm:p-10 rounded-3xl border border-[#21262d] bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl text-center space-y-5">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-[#da3633]/15 border border-[#f85149]/30 text-[#f85149] flex items-center justify-center shadow-lg shadow-[#da3633]/20">
            <x-icon name="shield" class="w-8 h-8" />
        </div>

        <div>
            <div class="text-xs font-mono font-semibold uppercase tracking-widest text-[#f85149] mb-1">HTTP 403 Forbidden</div>
            <h1 class="text-2xl font-black text-[#e6edf3] tracking-tight">Доступ ограничен</h1>
        </div>

        <p class="text-sm text-[#8b949e] leading-relaxed">
            Вход в панель управления SCUM Server Manager защищён политикой безопасности по белым спискам IP-адресов.
        </p>

        @if (!empty($clientIp))
            <div class="p-3 rounded-xl bg-[#161b22] border border-[#21262d] text-xs flex items-center justify-between">
                <span class="text-[#8b949e]">Ваш IP-адрес:</span>
                <span class="mono font-bold text-[#58a6ff]">{{ $clientIp }}</span>
            </div>
        @endif

        <div class="pt-2 text-xs text-[#8b949e]">
            Для авторизации добавьте ваш IP в список доверенных через CLI или обратитесь к администратору сервера.
        </div>
    </div>
</body>
</html>
