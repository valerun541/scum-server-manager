@echo off
REM Запуск демонов SCUM Server Manager (локальная разработка на Windows).
REM Аналог Supervisor: php artisan schedule:work + scum:rcon + scum:watch.
REM Остановка: Ctrl+C в каждом окне, либо закрыть окна.
setlocal
cd /d "%~dp0..\.."

echo [1/3] Scheduler (schedule:work) — ставит тики плагинов в очередь scum_jobs...
start "SCUM Scheduler" php artisan schedule:work

echo [2/3] RCON daemon — единственный владелец RCON-сокета...
start "SCUM RCON" php artisan scum:rcon --poll=150

echo [3/3] FTP watch daemon — мониторинг логов...
start "SCUM Watch" php artisan scum:watch --poll=250

echo Done. Processes are running in separate windows.
pause