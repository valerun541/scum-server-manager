<?php

namespace App\Services\Scum;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Получение Steam-аватарки по SteamID64 из публичного профиля Steam Community.
 * Без API-ключа: используется XML-выдача страницы профиля (`?xml=1`).
 * Результат кэшируется в storage/app/steam_profiles/<steamId>.json.
 */
class SteamProfileService
{
    private const TTL_OK = 86400;

    private const TTL_FAIL = 3600;

    public function avatar(string $steamId): ?string
    {
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return null;
        }
        $cached = $this->readCache($steamId);
        if ($cached !== null) {
            return $cached['avatar'] ?? null;
        }
        $avatar = $this->fetchAvatar($steamId);
        $this->writeCache($steamId, $avatar, $avatar !== null ? (int) config('panel.cache.steam_avatar_ok_ttl', self::TTL_OK) : (int) config('panel.cache.steam_avatar_fail_ttl', self::TTL_FAIL));

        return $avatar;
    }

    private function fetchAvatar(string $steamId): ?string
    {
        try {
            $resp = Http::timeout((int) config('panel.http.steam_timeout', 6))
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'application/xml,text/xml,text/html;q=0.9,*/*;q=0.8',
                ])
                ->get('https://steamcommunity.com/profiles/'.$steamId.'/?xml=1');

            if (! $resp->successful()) {
                return null;
            }
            $body = $resp->body();
            if (str_contains($body, 'no_key') || str_contains($body, 'Please try again later')) {
                return null;
            }
            if (preg_match('#<avatarFull>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</avatarFull>#s', $body, $m)) {
                $url = trim($m[1]);

                return $url !== '' ? $url : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function cachePath(string $steamId): string
    {
        return storage_path('app/steam_profiles/'.$steamId.'.json');
    }

    private function readCache(string $steamId): ?array
    {
        $path = $this->cachePath($steamId);
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (! is_array($data) || empty($data['time']) || empty($data['ttl'])) {
            return null;
        }
        if (time() - (int) $data['time'] > (int) $data['ttl']) {
            return null;
        }

        return $data;
    }

    private function writeCache(string $steamId, ?string $avatar, int $ttl): void
    {
        $dir = dirname($this->cachePath($steamId));
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->cachePath($steamId), json_encode([
            'time' => time(),
            'ttl' => $ttl,
            'avatar' => $avatar,
        ], JSON_UNESCAPED_SLASHES));
    }
}
