<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Таймауты и интервалы панели SCUM Manager
    |--------------------------------------------------------------------------
    |
    | Централизованные настройки времени всех выполняемых запросов панели:
    | HTTP, RCON, FTP/SFTP, кэш, поллинг демонов, карта, БД, rate-limit.
    | Переопределяются через .env (PANEL_*). Значения по умолчанию
    | совпадают с прежними хардкодами — поведение не меняется.
    |
    */

    'rcon' => [
        'connect_timeout' => (int) env('PANEL_RCON_CONNECT_TIMEOUT', 5),   // сек
        'auth_timeout' => (int) env('PANEL_RCON_AUTH_TIMEOUT', 3),          // сек
        'command_timeout' => (int) env('PANEL_RCON_COMMAND_TIMEOUT', 15),   // сек
        'response_debounce_ms' => (int) env('PANEL_RCON_DEBOUNCE_MS', 100),
        'reconnect_delay' => (int) env('PANEL_RCON_RECONNECT_DELAY', 5),    // сек
        'state_ttl' => (int) env('PANEL_RCON_STATE_TTL', 300),              // сек, кэш статуса подключения
        'poll_interval_ms' => (int) env('PANEL_RCON_POLL_MS', 150),          // мс, scum:rcon --poll
    ],

    'ftp' => [
        'connect_timeout' => (int) env('PANEL_FTP_CONNECT_TIMEOUT', 15), // сек, SFTP
        'poll_interval_ms' => (int) env('PANEL_FTP_POLL_MS', 500),        // мс, scum:watch --poll
        'state_ttl' => (int) env('PANEL_FTP_STATE_TTL', 300),             // сек, кэш FTP-состояния
        'pending_ttl' => (int) env('PANEL_FTP_PENDING_TTL', 3600),         // сек, защита локальных правок от перекачки
        'scumdb_sync_sec' => (int) env('PANEL_SCUMDB_SYNC_SEC', 60),       // сек, период фонового синка SCUM.db демоном
        'scumdb_ttl' => (int) env('PANEL_SCUMDB_TTL', 300),                // сек, TTL свежей копии в веб-запросах (ensureFreshScumDb)
    ],

    'sftp' => [
        'timeout' => (int) env('PANEL_SFTP_TIMEOUT', 15), // сек
    ],

    'http' => [
        'default_timeout' => (int) env('PANEL_HTTP_TIMEOUT', 10),               // сек
        'automation_timeout' => (int) env('PANEL_HTTP_AUTOMATION_TIMEOUT', 10), // сек, AutomationService Http::timeout
        'automation_pool_timeout' => (int) env('PANEL_HTTP_AUTOMATION_POOL_TIMEOUT', 15),
        'wargm_timeout' => (int) env('PANEL_HTTP_WARGM_TIMEOUT', 30),           // сек, WargmService
        'steam_timeout' => (int) env('PANEL_HTTP_STEAM_TIMEOUT', 6),            // сек, SteamProfileService
        'lolka_timeout' => (int) env('PANEL_HTTP_LOLKA_TIMEOUT', 10),           // сек, LolkaService
        'workflow_timeout' => (int) env('PANEL_WORKFLOW_TIMEOUT', 30),          // сек, AutomationService workflow
    ],

    'cache' => [
        'settings_ttl' => (int) env('PANEL_CACHE_SETTINGS_TTL', 300),          // сек, SettingsService raw
        'automation_workflows_ttl' => (int) env('PANEL_CACHE_WORKFLOWS_TTL', 5), // сек
        'player_fame_ttl' => (int) env('PANEL_CACHE_FAME_TTL', 30),             // сек
        'server_probe_ttl' => (int) env('PANEL_CACHE_PROBE_TTL', 15),           // сек, ServersController RCON/FTP probe
        'online_players_ttl' => (int) env('PANEL_CACHE_ONLINE_TTL', 10),        // сек, PluginService
        'chest_ttl' => (int) env('PANEL_CACHE_CHEST_TTL', 3600),                // сек
        'chest_grace_sec' => (int) env('PANEL_CACHE_CHEST_GRACE_SEC', 60),
        'listing_ttl' => (int) env('PANEL_CACHE_LISTING_TTL', 30),              // сек, ServerConfigController
        'steam_avatar_ok_ttl' => (int) env('PANEL_CACHE_STEAM_OK_TTL', 86400),  // сек
        'steam_avatar_fail_ttl' => (int) env('PANEL_CACHE_STEAM_FAIL_TTL', 3600),
    ],

    'daemon' => [
        'heartbeat_ttl' => (int) env('PANEL_DAEMON_HEARTBEAT_TTL', 30), // сек
    ],

    'db' => [
        'busy_timeout' => (int) env('PANEL_DB_BUSY_TIMEOUT', 5), // сек, SQLite busy_timeout
    ],

    'map' => [
        'stats_poll_ms' => (int) env('PANEL_MAP_STATS_POLL_MS', 30000), // мс, интервал обновления бейджей карты
    ],

    'rate_limit' => [
        'login_per_minute' => (int) env('PANEL_RL_LOGIN', 30),
        'panel_per_minute' => (int) env('PANEL_RL_PANEL', 120),
        'jobs_per_minute' => (int) env('PANEL_RL_JOBS', 600),
    ],

    'auth' => [
        'max_attempts' => (int) env('PANEL_AUTH_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('PANEL_AUTH_LOCKOUT_SECONDS', 7200), // 2 часа блокировки
    ],

    'plugins' => [
        'auto_drop_interval_minutes' => (int) env('PANEL_PLUGINS_AUTO_DROP_INTERVAL', 30),
        'top_interval_days' => (int) env('PANEL_PLUGINS_TOP_INTERVAL_DAYS', 1),
    ],

];
