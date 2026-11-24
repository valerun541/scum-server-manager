<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 Сессия истекла — SCUM Server Manager</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[#090d14] text-[#e6edf3] font-sans antialiased relative overflow-hidden">
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-[#d29922]/15 blur-[120px] pointer-events-none"></div>

    <div class="relative w-full max-w-md p-8 sm:p-10 rounded-3xl border border-[#21262d] bg-[#0c1017]/95 backdrop-blur-xl shadow-2xl text-center space-y-6">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-[#d29922]/15 border border-[#e3b341]/30 text-[#e3b341] flex items-center justify-center shadow-lg shadow-[#d29922]/20">
            <x-icon name="lock" class="w-8 h-8" />
        </div>

        <div>
            <div class="text-xs font-mono font-semibold uppercase tracking-widest text-[#e3b341] mb-1">HTTP 419 Page Expired</div>
            <h1 class="text-2xl font-black text-[#e6edf3] tracking-tight">Сессия истекла</h1>
        </div>

        <p class="text-sm text-[#8b949e] leading-relaxed">
            Срок действия защитного токена безопасности (CSRF) истек из-за длительного отсутствия активности.
        </p>

        <div class="pt-2 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#238636] hover:bg-[#2ea043] text-white text-sm font-semibold transition shadow-lg shadow-[#238636]/20">
                <x-icon name="logout" class="w-4 h-4" />
                <span>Войти снова</span>
            </a>
            <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#30363d] bg-[#161b22] hover:bg-[#21262d] text-[#c9d1d9] text-sm font-semibold transition">
                <x-icon name="refresh-cw" class="w-4 h-4" />
                <span>Обновить</span>
            </button>
        </div>
    </div>
</body>
</html>
