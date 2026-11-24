<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SessionManagerService
{
    private string $sessionPath;

    public function __construct()
    {
        $this->sessionPath = storage_path('framework/sessions');
    }

    public function trackCurrentSession(Request $request): void
    {
        $session = $request->session();
        if (! $session->has('login_ip')) {
            $session->put('login_ip', $request->ip());
            $session->put('login_user_agent', (string) $request->userAgent());
            $session->put('login_at', now()->toDateTimeString());
        }
        $session->put('last_activity_at', now()->toDateTimeString());
    }

    /**
     * @return array<int, array{id: string, ip: string, user_agent: string, browser: string, platform: string, login_at: string, last_activity: string, is_current: bool}>
     */
    public function getActiveSessions(string $currentSessionId): array
    {
        if (! File::isDirectory($this->sessionPath)) {
            return [];
        }

        $files = File::files($this->sessionPath);
        $sessions = [];
        $cutoff = time() - (config('session.lifetime', 120) * 60);

        foreach ($files as $file) {
            $mtime = $file->getMTime();
            if ($mtime < $cutoff) {
                continue;
            }

            $id = $file->getFilename();
            $content = @file_get_contents($file->getPathname());
            if (! $content) {
                continue;
            }

            $payload = @unserialize($content);
            if (! is_array($payload) || empty($payload['scum_auth'])) {
                continue;
            }

            $ip = (string) ($payload['login_ip'] ?? '127.0.0.1');
            $ua = (string) ($payload['login_user_agent'] ?? 'Unknown');
            $loginAt = (string) ($payload['login_at'] ?? date('Y-m-d H:i:s', $mtime));
            $lastActivity = date('d.m.Y H:i', $mtime);

            $browser = 'Браузер';
            if (str_contains($ua, 'Edg/')) {
                $browser = 'Microsoft Edge';
            } elseif (str_contains($ua, 'Chrome/')) {
                $browser = 'Google Chrome';
            } elseif (str_contains($ua, 'Firefox/')) {
                $browser = 'Mozilla Firefox';
            } elseif (str_contains($ua, 'Safari/')) {
                $browser = 'Apple Safari';
            } elseif (str_contains($ua, 'YaBrowser/')) {
                $browser = 'Yandex Browser';
            }

            $platform = 'ОС';
            if (str_contains($ua, 'Windows')) {
                $platform = 'Windows';
            } elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) {
                $platform = 'macOS';
            } elseif (str_contains($ua, 'Linux')) {
                $platform = 'Linux';
            } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
                $platform = 'iOS';
            } elseif (str_contains($ua, 'Android')) {
                $platform = 'Android';
            }

            $sessions[] = [
                'id' => $id,
                'ip' => $ip,
                'user_agent' => $ua,
                'browser' => $browser,
                'platform' => $platform,
                'login_at' => $loginAt,
                'last_activity' => $lastActivity,
                'is_current' => ($id === $currentSessionId),
            ];
        }

        // Sort: current session first, then by last_activity desc
        usort($sessions, function ($a, $b) {
            if ($a['is_current']) {
                return -1;
            }
            if ($b['is_current']) {
                return 1;
            }

            return strcmp($b['last_activity'], $a['last_activity']);
        });

        return $sessions;
    }

    public function revokeOtherSessions(string $currentSessionId): int
    {
        if (! File::isDirectory($this->sessionPath)) {
            return 0;
        }

        $files = File::files($this->sessionPath);
        $count = 0;

        foreach ($files as $file) {
            $id = $file->getFilename();
            if ($id === $currentSessionId) {
                continue;
            }

            $content = @file_get_contents($file->getPathname());
            if ($content) {
                $payload = @unserialize($content);
                if (is_array($payload) && ! empty($payload['scum_auth'])) {
                    @unlink($file->getPathname());
                    $count++;
                }
            }
        }

        return $count;
    }
}
