<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 Ошибка сервера — SCUM Server Manager</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[#090d14] text-[#e6edf3] font-sans antialiased relative overflow-hidden">
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#da3633]/15 blur-[120px] pointer-events-none"></div>

    <div class="relative w-full max-w-md p-8 sm:p-10 rounded-3xl border border-[#21262d] bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl text-center space-y-6">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-[#da3633]/15 border border-[#f85149]/30 text-[#f85149] flex items-center justify-center shadow-lg shadow-[#da3633]/20">
            <x-icon name="alert-triangle" class="w-8 h-8" />
        </div>

        <div>
            <div class="text-xs font-mono font-semibold uppercase tracking-widest text-[#f85149] mb-1">HTTP 500 Server Error</div>
            <h1 class="text-2xl font-black text-[#e6edf3] tracking-tight">Ошибка сервера</h1>
        </div>

        <p class="text-sm text-[#8b949e] leading-relaxed">
            Произошла внутренняя ошибка при обработке запроса. Мы уже зафиксировали инцидент в системном журнале.
        </p>

        <div class="pt-2 flex flex-col sm:flex-row gap-3 justify-center">
            <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#238636] hover:bg-[#2ea043] text-white text-sm font-semibold transition shadow-lg shadow-[#238636]/20">
                <x-icon name="refresh-cw" class="w-4 h-4" />
                <span>Повторить</span>
            </button>
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#30363d] bg-[#161b22] hover:bg-[#21262d] text-[#c9d1d9] text-sm font-semibold transition">
                <x-icon name="home" class="w-4 h-4" />
                <span>На главную</span>
            </a>
        </div>
    </div>
</body>
</html>
