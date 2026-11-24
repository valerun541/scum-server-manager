<?php

namespace App\Services\Scum;

/**
 * Учёт времени онлайн и экономики игроков — порт ratingManager.ts
 */
class RatingService
{
    /** @var array<string, array{steamId:string,playerName:string,startedAt:int}> */
    private array $activeSessions = [];

    /** Предел одновременных сессий — защита от бесконечного роста, если logout не распознан */
    private const MAX_SESSIONS = 500;

    /** Максимальный возраст сессии без обновления (сек) */
    private const SESSION_STALE_SECONDS = 86400;

    public function __construct(private SettingsService $settings) {}

    /** @return array{packs:array,vip:array,teleportLocations:array,saveHome:array,packCooldowns:array,homeLocations:array,rating:array,rewards:array,calibration:array} */
    private function botData(): array
    {
        return $this->settings->getBotData();
    }

    public function ensurePlayer(string $steamId, string $playerName): void
    {
        if (! $steamId) {
            return;
        }
        $data = $this->botData();
        $rating = $data['rating'];
        $updated = false;
        $idx = null;
        foreach ($rating as $i => $r) {
            if ($r['steamId'] === $steamId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            $rating[] = [
                'steamId' => $steamId,
                'playerName' => $playerName,
                'playTimeSeconds' => 0,
                'money' => 0,
                'gold' => 0,
                'fame' => 0,
                'lastUpdated' => now()->toISOString(),
            ];
            $updated = true;
        } elseif ($rating[$idx]['playerName'] !== $playerName) {
            $rating[$idx]['playerName'] = $playerName;
            $updated = true;
        }
        if ($updated) {
            $this->settings->saveBotData(['rating' => $rating]);
        }
        if (! isset($this->activeSessions[$steamId])) {
            $this->activeSessions[$steamId] = ['steamId' => $steamId, 'playerName' => $playerName, 'startedAt' => time()];
        }
    }

    public function playerConnected(string $steamId, string $playerName): void
    {
        $this->ensurePlayer($steamId, $playerName);
        $this->activeSessions[$steamId] = ['steamId' => $steamId, 'playerName' => $playerName, 'startedAt' => time()];
        $this->pruneSessions();
    }

    public function playerDisconnected(string $steamId): void
    {
        $session = $this->activeSessions[$steamId] ?? null;
        if (! $session) {
            return;
        }
        $duration = (int) (time() - $session['startedAt']);
        unset($this->activeSessions[$steamId]);
        $data = $this->botData();
        $rating = $data['rating'];
        foreach ($rating as $i => $r) {
            if ($r['steamId'] === $steamId) {
                $rating[$i]['playTimeSeconds'] = (int) ($r['playTimeSeconds'] ?? 0) + $duration;
                $rating[$i]['lastUpdated'] = now()->toISOString();
                break;
            }
        }
        $this->settings->saveBotData(['rating' => $rating]);
    }

    public function getActiveSessions(): array
    {
        return $this->activeSessions;
    }

    /**
     * Ограничивает размер $activeSessions: если набралось больше MAX_SESSIONS,
     * удаляет самые старые сессии (без потери накопленного playTime — он
     * записывается в rating при выходе игрока, а старые «зависшие» сессии —
     * артефакты нераспознанного logout).
     */
    private function pruneSessions(): void
    {
        if (count($this->activeSessions) <= self::MAX_SESSIONS) {
            return;
        }
        $now = time();
        uasort($this->activeSessions, fn ($a, $b) => $a['startedAt'] <=> $b['startedAt']);
        $excess = count($this->activeSessions) - self::MAX_SESSIONS;
        foreach ($this->activeSessions as $sid => $s) {
            if ($excess <= 0) {
                break;
            }
            if (($now - $s['startedAt']) > self::SESSION_STALE_SECONDS) {
                unset($this->activeSessions[$sid]);
                $excess--;
            }
        }
    }

    public function updateEconomy(string $steamId, float $money, float $gold, float $fame): void
    {
        $data = $this->botData();
        $rating = $data['rating'];
        foreach ($rating as $i => $r) {
            if ($r['steamId'] === $steamId) {
                $rating[$i]['money'] = $money;
                $rating[$i]['gold'] = $gold;
                $rating[$i]['fame'] = $fame;
                $rating[$i]['lastUpdated'] = now()->toISOString();
                break;
            }
        }
        $this->settings->saveBotData(['rating' => $rating]);
    }

    /** @return list<array{steamId: string, playerName: string, playTimeSeconds: int, money?: float, gold?: float, fame?: float, lastUpdated?: string}> */
    public function getLeaderboard(): array
    {
        $rating = $this->botData()['rating'];
        $blacklist = $this->settings->getAppConfig()['plugins']['ratingBlacklist'] ?? [];
        $filtered = array_values(array_filter($rating, fn ($r) => ! in_array($r['steamId'], $blacklist)));
        usort($filtered, fn ($a, $b) => ($b['playTimeSeconds'] ?? 0) <=> ($a['playTimeSeconds'] ?? 0));

        return $filtered;
    }

    public function getTotalOnlineSeconds(): int
    {
        $total = 0;
        foreach ($this->botData()['rating'] as $r) {
            $total += (int) ($r['playTimeSeconds'] ?? 0);
        }

        return $total;
    }

    public function formatPlayTime(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return "{$hours}ч {$mins}м";
        }

        return "{$mins}м";
    }
}
