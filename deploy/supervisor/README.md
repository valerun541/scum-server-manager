# Supervisor для демонов SCUM Server Manager (Ubuntu/Debian)

Скопируйте конфиги в /etc/supervisor/conf.d/ и перезапустите:

```bash
sudo cp deploy/supervisor/scum-rcon.conf deploy/supervisor/scum-watch.conf deploy/supervisor/scum-scheduler.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Три процесса:
- **scum-rcon** — единственный владелец RCON-сокета; исполняет очередь `scum_jobs` (чат-команды, события, тики плагинов, команды веб-консоли) и держит кэш онлайн-игроков.
- **scum-watch** — FTP-мониторинг логов; ставит события/чат-команды в очередь, синхронизирует SCUM.db, подогревает кэш карты.
- **scum-scheduler** — `schedule:work`: раз в минуту ставит `scum:dispatch-tick` (тик расписаний/плагинов) и раз в 15 минут `scum:maintenance` (чистка scum_jobs и кэша).

Проверка локально (Windows) — см. `deploy/windows/start-demons.bat`.

Supervisor автоматически перезапустит демон при падении (например, по таймауту или после `queue:restart`), поэтому `autorestart=true`.