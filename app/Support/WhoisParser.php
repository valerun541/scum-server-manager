<?php

namespace App\Support;

class WhoisParser
{
    /**
     * Разбирает текстовый ответ RCON-команды Whois в ассоциативный массив.
     *
     * @return array{
     *     name?: string,
     *     steamId?: string,
     *     fame?: float,
     *     money?: float,
     *     gold?: float,
     *     kills?: int,
     *     deaths?: int,
     *     kd?: float,
     *     puppetKills?: int,
     *     headshotKills?: int,
     *     playtime?: string,
     *     squad?: string,
     *     vehiclesCount?: int
     * }
     */
    public static function parse(string $text): array
    {
        $whois = [];
        $lines = array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== '');

        foreach ($lines as $line) {
            if (preg_match('/^Whois:\s*(.+?)\s*\(SteamID\s*(\d+)\)/i', $line, $h)) {
                $whois['name'] = trim($h[1]);
                $whois['steamId'] = $h[2];

                continue;
            }

            if (preg_match('/^Fame:\s*([\d.]+)(?:\s+Money:\s*([\d.]+))?(?:\s+Gold:\s*([\d.]+))?/i', $line, $f)) {
                $whois['fame'] = (float) $f[1];
                if (isset($f[2])) {
                    $whois['money'] = (float) $f[2];
                }
                if (isset($f[3])) {
                    $whois['gold'] = (float) $f[3];
                }

                continue;
            }

            if (preg_match('/^Kills:\s*(\d+)(?:\s+Deaths:\s*(\d+))?(?:\s+K\/D:\s*([\d.]+))?(?:\s+Puppets:\s*(\d+))?(?:\s+HS:\s*(\d+))?/i', $line, $k)) {
                $whois['kills'] = (int) $k[1];
                if (isset($k[2])) {
                    $whois['deaths'] = (int) $k[2];
                }
                if (isset($k[3])) {
                    $whois['kd'] = (float) $k[3];
                }
                if (isset($k[4])) {
                    $whois['puppetKills'] = (int) $k[4];
                }
                if (isset($k[5])) {
                    $whois['headshotKills'] = (int) $k[5];
                }

                continue;
            }

            if (preg_match('/^Vehicles?\s+owned\s*:\s*(\d+)/i', $line, $v)) {
                $whois['vehiclesCount'] = (int) $v[1];

                continue;
            }

            if (! preg_match('/^([^:]+):\s*(.+)$/', $line, $kv)) {
                continue;
            }

            $key = strtolower(trim($kv[1]));
            $val = trim($kv[2]);

            if ($key === 'name') {
                $whois['name'] = $val;
            } elseif ($key === 'steam id' || $key === 'steamid') {
                $whois['steamId'] = $val;
            } elseif ($key === 'fame') {
                $whois['fame'] = (float) $val;
            } elseif ($key === 'money') {
                $whois['money'] = (float) $val;
            } elseif ($key === 'gold') {
                $whois['gold'] = (float) $val;
            } elseif ($key === 'kills') {
                $whois['kills'] = (int) $val;
            } elseif ($key === 'deaths') {
                $whois['deaths'] = (int) $val;
            } elseif ($key === 'k-d' || $key === 'kd') {
                $whois['kd'] = (float) $val;
            } elseif (str_contains($key, 'puppet') && str_contains($key, 'kill')) {
                $whois['puppetKills'] = (int) $val;
            } elseif (str_contains($key, 'headshot') || str_contains($key, 'head')) {
                $whois['headshotKills'] = (int) $val;
            } elseif ($key === 'playtime' || $key === 'time played') {
                $whois['playtime'] = $val;
            } elseif ($key === 'squad') {
                $whois['squad'] = $val;
            }
        }

        return $whois;
    }

    /**
     * Быстрое извлечение очков славы (fame) из ответа Whois.
     */
    public static function extractFame(string $text): float
    {
        if (preg_match('/\bfame[:\s]\s*([\d.]+)/i', $text, $m)) {
            return (float) $m[1];
        }

        return 0.0;
    }

    /**
     * Быстрое извлечение имени игрока из ответа Whois.
     */
    public static function extractName(string $text): ?string
    {
        if (preg_match('/^Whois:\s*(.+?)\s*(?:\(SteamID|\r|\n|$)/i', $text, $m)) {
            $name = trim($m[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }
}
