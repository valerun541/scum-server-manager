<?php

namespace App\Services\Scum;

use App\Support\SecureValues;
use Illuminate\Support\Facades\Cache;

/**
 * Порту  electron-store: хранит AppConfig и BotData в SQLite.
 * - AppConfig  — настройки приложения/плагинов (rcon, hostFtp, packs, plugins, lolka...)
 * - BotData    — персистентные игровые данные (packs, vip, teleports, saveHome,
 *                packCooldowns, homeLocations, rating, rewards, calibration)
 */
class SettingsService
{
    private const APP_KEY = 'app_config';

    private const BOT_KEY = 'bot_data';

    /** Поля AppConfig, которые хранятся зашифрованными (пароли, webhook-токены). */
    private const SECRET_PATHS = [
        'rcon.password',
        'hostFtp.password',
        'lolka.adminLogWebhook',
        'lolka.chatWebhook',
        'lolka.commandWebhook',
        'lolka.vehicleWebhook',
        'lolka.loginWebhook',
        'lolka.serverStatusWebhook',
    ];

    /** Пароль панели хранится как bcrypt/argon2-хэш (обратного чтения нет). */
    private const HASH_PATHS = [
        'webPanel.password',
    ];

    public function getAppConfig(): array
    {
        $cfg = $this->read(self::APP_KEY) + $this->defaultAppConfig();
        if (isset($cfg['lolka']) && is_array($cfg['lolka'])) {
            if (empty($cfg['lolka']['webhooks']) || ! is_array($cfg['lolka']['webhooks'])) {
                $whList = [];
                $labels = [
                    'commandWebhook' => 'Выполненные команды',
                    'adminLogWebhook' => 'Логи администраторов',
                    'chatWebhook' => 'Чат сервера',
                    'vehicleWebhook' => 'Транспорт и машины',
                    'loginWebhook' => 'Вход и выход игроков',
                    'serverStatusWebhook' => 'Статус сервера',
                ];
                foreach ($labels as $k => $lbl) {
                    if (! empty($cfg['lolka'][$k]) && is_string($cfg['lolka'][$k]) && trim($cfg['lolka'][$k]) !== '') {
                        $whList[] = [
                            'name' => $lbl,
                            'key' => $k,
                            'url' => trim($cfg['lolka'][$k]),
                        ];
                    }
                }
                if (! empty($whList)) {
                    $cfg['lolka']['webhooks'] = $whList;
                }
            }
        }

        return $cfg;
    }

    public function saveAppConfig(array $partial): void
    {
        $this->mergeWrite(self::APP_KEY, $partial);
    }

    public function setAppConfig(array $full): void
    {
        $this->write(self::APP_KEY, $full);
    }

    public function getBotData(): array
    {
        return $this->read(self::BOT_KEY) + $this->defaultBotData();
    }

    public function saveBotData(array $partial): void
    {
        $this->mergeWrite(self::BOT_KEY, $partial);
    }

    // ===== Удобные кислотные сеттеры BattleData =====
    public function setBotSections(array $sections): void
    {
        $this->mergeWrite(self::BOT_KEY, $sections);
    }

    private function read(string $key): array
    {
        $raw = Cache::remember('settings_raw:'.$key, (int) config('panel.cache.settings_ttl', 300), function () use ($key) {
            $value = \DB::table('app_settings')->where('key', $key)->value('value');
            if (empty($value)) {
                return null;
            }
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        });
        if ($raw === null) {
            return [];
        }

        // Расшифровка после чтения кэша — в файловом кэше секреты остаются зашифрованными.
        return $this->decryptSecrets($raw);
    }

    private function mergeWrite(string $key, array $partial): void
    {
        $existing = $this->read($key);
        $merged = $this->recursiveMerge($existing, $partial);
        $this->write($key, $merged);
        Cache::forget('settings_raw:'.$key);
    }

    /**
     * Замена значений с учётом пустых массивов: array_replace_recursive считает
     * пустой массив «нечего заменять» и оставляет старое значение — это ломает
     * очистку списков (например, чёрного списка рейтинга).
     */
    private function recursiveMerge(array $base, array $override): array
    {
        foreach ($override as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                if ($v === []) {
                    $base[$k] = [];
                } else {
                    $base[$k] = $this->recursiveMerge($base[$k], $v);
                }
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    private function write(string $key, array $value): void
    {
        \DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($this->encryptSecrets($value), JSON_UNESCAPED_UNICODE), 'updated_at' => now()],
        );
        Cache::forget('settings_raw:'.$key);
    }

    private function decryptSecrets(array $config): array
    {
        foreach (self::SECRET_PATHS as $path) {
            $parts = explode('.', $path);
            if (isset($config[$parts[0]][$parts[1]]) && is_string($config[$parts[0]][$parts[1]])) {
                $config[$parts[0]][$parts[1]] = SecureValues::decrypt($config[$parts[0]][$parts[1]]);
            }
        }

        return $config;
    }

    private function encryptSecrets(array $config): array
    {
        foreach (self::SECRET_PATHS as $path) {
            $parts = explode('.', $path);
            if (isset($config[$parts[0]][$parts[1]]) && is_string($config[$parts[0]][$parts[1]])) {
                $config[$parts[0]][$parts[1]] = SecureValues::encrypt($config[$parts[0]][$parts[1]]);
            }
        }
        foreach (self::HASH_PATHS as $path) {
            $parts = explode('.', $path);
            if (isset($config[$parts[0]][$parts[1]]) && is_string($config[$parts[0]][$parts[1]])) {
                $config[$parts[0]][$parts[1]] = SecureValues::hashPassword($config[$parts[0]][$parts[1]]);
            }
        }

        return $config;
    }

    public function defaultAppConfig(): array
    {
        return [
            'hostFtp' => [
                'enabled' => false,
                'host' => '',
                'port' => 21,
                'username' => '',
                'password' => '',
                'logPath' => 'SCUM/Saved/SaveFiles/Logs',
                'scumLogPath' => 'SCUM/Saved/Logs/SCUM.log',
                'pollIntervalMs' => (int) config('panel.ftp.poll_interval_ms', 2000),
                'rotationCheckSec' => 30,
                'scumDbSyncSec' => (int) config('panel.ftp.scumdb_sync_sec', 60),
                'scumDbPath' => 'SCUM/Saved/SaveFiles/SCUM.db',
                'basePath' => '',
                'secure' => false,
                'protocol' => 'ftp',
            ],
            'rcon' => ['enabled' => false, 'host' => '', 'port' => 28015, 'password' => ''],
            'webPanel' => ['enabled' => false, 'port' => 8080, 'username' => 'admin', 'password' => 'scum'],
            'packs' => [
                'starter' => ['enabled' => true, 'items' => [], 'cooldownHours' => 0],
                'daily' => ['enabled' => true, 'items' => [], 'cooldownHours' => 24],
            ],
            'plugins' => [
                'teleport' => ['enabled' => true, 'locations' => []],
                'vip' => [
                    'enabled' => true,
                    'players' => [],
                    'starterBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0],
                    'dailyBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0],
                ],
                'saveHome' => ['enabled' => true, 'maxLocations' => 1, 'vipMaxLocations' => 3, 'teleportPrice' => 0],
                'airdrop' => [
                    'enabled' => true,
                    'chestItem' => 'Improvised_Metal_Chest',
                    'minItems' => 3,
                    'maxItems' => 8,
                    'cooldownMinutes' => 60,
                    'autoDropEnabled' => false,
                    'autoDropIntervalMinutes' => (int) config('panel.plugins.auto_drop_interval_minutes', 30),
                    'autoDropMinPlayers' => 2,
                ],
                'rewards' => [
                    'enabled' => true,
                    'hourlyEnabled' => true,
                    'hourlyGold' => 0, 'hourlyMoney' => 0, 'hourlyFame' => 0,
                    'topEnabled' => true,
                    'topIntervalDays' => (int) config('panel.plugins.top_interval_days', 1),
                    'topCount' => 3,
                    'topGold' => 100, 'topMoney' => 1000, 'topFame' => 0,
                ],
                'chatSender' => 'AMUR bot',
                'ratingBlacklist' => [],
            ],
            'lolka' => [
                'enabled' => true,
                'webhooks' => [
                    [
                        'name' => 'Выполненные команды',
                        'key' => 'commandWebhook',
                        'url' => 'https://lolka.app/api/webhooks/832042433053696/ODMyMDQyNDMzMDUzNjk2.bKtq3s1QtN-88R5AedAOBXgIv87U25sgRT1CN_od73A',
                    ],
                ],
                'adminLogWebhook' => '',
                'chatWebhook' => '',
                'commandWebhook' => 'https://lolka.app/api/webhooks/832042433053696/ODMyMDQyNDMzMDUzNjk2.bKtq3s1QtN-88R5AedAOBXgIv87U25sgRT1CN_od73A',
                'vehicleWebhook' => '',
                'loginWebhook' => '',
                'serverStatusWebhook' => '',
            ],
            'theme' => 'dark',
            'language' => 'ru',
        ];
    }

    public function defaultBotData(): array
    {
        return [
            'packs' => $this->defaultAppConfig()['packs'],
            'vip' => ['enabled' => true, 'players' => [], 'starterBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0], 'dailyBonus' => ['items' => [], 'money' => 0, 'gold' => 0, 'fame' => 0]],
            'teleportLocations' => [],
            'saveHome' => ['enabled' => true, 'maxLocations' => 1, 'vipMaxLocations' => 3, 'teleportPrice' => 0],
            'packCooldowns' => [],
            'homeLocations' => [],
            'rating' => [],
            'rewards' => ['lastHourlyReward' => [], 'lastTopRewardTime' => 0, 'playerTimeData' => []],
            'calibration' => [],
        ];
    }
}
