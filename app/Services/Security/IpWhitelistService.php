<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IpWhitelistService
{
    public const SETTING_KEY = 'ip_whitelist';

    private const CACHE_KEY = 'security_ip_whitelist';

    private const CACHE_TTL = 300; // 5 минут

    /**
     * Получить конфигурацию белого списка IP.
     */
    public function getSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $value = DB::table('app_settings')->where('key', self::SETTING_KEY)->value('value');
            if (empty($value)) {
                return $this->defaultSettings();
            }
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return $this->defaultSettings();
            }

            return array_merge($this->defaultSettings(), $decoded);
        });
    }

    /**
     * Сохранить конфигурацию белого списка IP.
     */
    public function saveSettings(array $settings): void
    {
        $merged = array_merge($this->getSettings(), $settings);

        DB::table('app_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'updated_at' => now(),
            ]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Проверить, включен ли белый список.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->getSettings()['enabled'] ?? false);
    }

    /**
     * Включить или выключить белый список.
     */
    public function setEnabled(bool $enabled, ?string $currentIp = null): array
    {
        $settings = $this->getSettings();

        // Защита от самоблокировки: если включаем белый список, а текущего IP нет в списке — автоматически добавляем
        if ($enabled && ! empty($currentIp) && ! $this->isIpInList($currentIp, $settings['ips'] ?? [])) {
            $settings['ips'][] = [
                'ip' => $currentIp,
                'label' => 'Автоматически добавлен при включении защиты',
                'created_at' => now()->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
            ];
        }

        $settings['enabled'] = $enabled;
        $this->saveSettings($settings);

        return [
            'ok' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Белый список IP успешно активирован' : 'Белый список IP отключен',
        ];
    }

    /**
     * Получить список всех разрешенных записей IP / подсетей.
     */
    public function getAllowedEntries(): array
    {
        $settings = $this->getSettings();

        return $settings['ips'] ?? [];
    }

    /**
     * Проверить, разрешен ли доступ для данного IP.
     */
    public function isAllowed(?string $clientIp): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (empty($clientIp)) {
            return false;
        }

        $clientIp = trim($clientIp);

        // Всегда разрешаем локальные запросы
        if (in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        $entries = $this->getAllowedEntries();
        foreach ($entries as $entry) {
            $pattern = trim($entry['ip'] ?? '');
            if (empty($pattern)) {
                continue;
            }

            if ($this->matchIp($clientIp, $pattern)) {
                $this->touchLastSeen($pattern);

                return true;
            }
        }

        return false;
    }

    /**
     * Добавить IP или CIDR-подсеть в белый список.
     */
    public function addEntry(string $ip, string $label = ''): array
    {
        $ip = trim($ip);
        $label = trim($label);

        if (! $this->validateIpOrCidr($ip)) {
            return [
                'ok' => false,
                'message' => 'Некорректный формат IP-адреса или CIDR-подсети (например: 95.31.181.58 или 192.168.1.0/24)',
            ];
        }

        $settings = $this->getSettings();
        $ips = $settings['ips'] ?? [];

        foreach ($ips as $entry) {
            if (strtolower(trim($entry['ip'] ?? '')) === strtolower($ip)) {
                return [
                    'ok' => false,
                    'message' => 'Данный IP-адрес или подсеть уже есть в белом списке',
                ];
            }
        }

        $ips[] = [
            'ip' => $ip,
            'label' => $label ?: 'Доверенный IP/подсеть',
            'created_at' => now()->toIso8601String(),
            'last_seen_at' => null,
        ];

        $settings['ips'] = array_values($ips);
        $this->saveSettings($settings);

        return [
            'ok' => true,
            'message' => "Адрес {$ip} успешно добавлен в белый список",
        ];
    }

    /**
     * Удалить IP или CIDR-подсеть из белого списка.
     */
    public function removeEntry(string $ip): array
    {
        $ip = trim($ip);
        $settings = $this->getSettings();
        $ips = $settings['ips'] ?? [];
        $found = false;

        $filtered = [];
        foreach ($ips as $entry) {
            if (strtolower(trim($entry['ip'] ?? '')) === strtolower($ip)) {
                $found = true;

                continue;
            }
            $filtered[] = $entry;
        }

        if (! $found) {
            return [
                'ok' => false,
                'message' => 'Указанный IP-адрес не найден в белом списке',
            ];
        }

        $settings['ips'] = $filtered;
        $this->saveSettings($settings);

        return [
            'ok' => true,
            'message' => "Адрес {$ip} успешно удален из белого списка",
        ];
    }

    /**
     * Обновить время последней активности IP (с троттлингом кэша 60 сек).
     */
    public function touchLastSeen(string $matchedPattern): void
    {
        $cacheTouchKey = 'ip_touch:'.md5($matchedPattern);
        if (Cache::has($cacheTouchKey)) {
            return;
        }
        Cache::put($cacheTouchKey, true, 60);

        try {
            $settings = $this->getSettings();
            $ips = $settings['ips'] ?? [];
            $changed = false;

            foreach ($ips as &$entry) {
                if (strtolower(trim($entry['ip'] ?? '')) === strtolower($matchedPattern)) {
                    $entry['last_seen_at'] = now()->toIso8601String();
                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                $settings['ips'] = $ips;
                DB::table('app_settings')->updateOrInsert(
                    ['key' => self::SETTING_KEY],
                    [
                        'value' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'updated_at' => now(),
                    ]
                );
                Cache::forget(self::CACHE_KEY);
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to touchLastSeen: '.$e->getMessage());
        }
    }

    /**
     * Проверить валидность IP (v4, v6) или CIDR.
     */
    public function validateIpOrCidr(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (str_contains($ip, '/')) {
            $parts = explode('/', $ip, 2);
            if (count($parts) !== 2) {
                return false;
            }
            [$subnet, $mask] = $parts;
            if (! is_numeric($mask)) {
                return false;
            }
            $mask = (int) $mask;

            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $mask >= 0 && $mask <= 32;
            }

            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $mask >= 0 && $mask <= 128;
            }
        }

        return false;
    }

    /**
     * Сопоставить клиентский IP с паттерном (точный IP или CIDR-диапазон).
     */
    public function matchIp(string $clientIp, string $allowedPattern): bool
    {
        $allowedPattern = trim($allowedPattern);
        $clientIp = trim($clientIp);

        if ($clientIp === $allowedPattern) {
            return true;
        }

        if (! str_contains($allowedPattern, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $allowedPattern, 2);
        $mask = (int) $mask;

        // IPv4 CIDR
        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($mask < 0 || $mask > 32) {
                return false;
            }
            if ($mask === 0) {
                return true;
            }
            $ipLong = ip2long($clientIp);
            $subnetLong = ip2long($subnet);
            $maskBits = ~((1 << (32 - $mask)) - 1);

            return ($ipLong & $maskBits) === ($subnetLong & $maskBits);
        }

        // IPv6 CIDR
        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($mask < 0 || $mask > 128) {
                return false;
            }
            if ($mask === 0) {
                return true;
            }
            $ipBin = inet_pton($clientIp);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $bytes = intdiv($mask, 8);
            $extraBits = $mask % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($extraBits > 0) {
                $maskByte = chr((0xFF << (8 - $extraBits)) & 0xFF);

                return (ord($ipBin[$bytes]) & ord($maskByte)) === (ord($subnetBin[$bytes]) & ord($maskByte));
            }

            return true;
        }

        return false;
    }

    private function isIpInList(string $clientIp, array $entries): bool
    {
        foreach ($entries as $entry) {
            $pattern = trim($entry['ip'] ?? '');
            if (! empty($pattern) && $this->matchIp($clientIp, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Настройки по умолчанию.
     */
    public function defaultSettings(): array
    {
        return [
            'enabled' => true,
            'ips' => [
                [
                    'ip' => '95.31.181.58',
                    'label' => 'Основной IP администратора',
                    'created_at' => now()->toIso8601String(),
                    'last_seen_at' => now()->toIso8601String(),
                ],
                [
                    'ip' => '127.0.0.1',
                    'label' => 'Локальный доступ (Localhost)',
                    'created_at' => now()->toIso8601String(),
                    'last_seen_at' => null,
                ],
            ],
            'mode' => 'all',
        ];
    }
}
