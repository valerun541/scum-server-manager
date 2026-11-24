<?php

namespace App\Services\System;

use App\Services\Scum\DaemonService;
use Illuminate\Support\Facades\Cache;

class SystemMetricsService
{
    public function __construct(private DaemonService $daemons) {}

    /**
     * @return array{
     *     uptime: array{seconds: int, formatted: string},
     *     cpu: array{cores: int, load_1m: float, load_5m: float, load_15m: float, percent: float},
     *     ram: array{total_mb: int, used_mb: int, free_mb: int, percent: float},
     *     disk: array{total_gb: float, used_gb: float, free_gb: float, percent: float},
     *     services: array{nginx: bool, php_fpm: bool, daemons: array<string, bool>}
     * }
     */
    public function getMetrics(): array
    {
        return Cache::remember('system_host_metrics', 3, function () {
            return $this->collectMetrics();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function collectMetrics(): array
    {
        // 1. Uptime
        $uptimeStr = @file_get_contents('/proc/uptime');
        $uptimeSec = $uptimeStr ? (int) explode(' ', $uptimeStr)[0] : 0;
        $days = intdiv($uptimeSec, 86400);
        $hours = intdiv($uptimeSec % 86400, 3600);
        $minutes = intdiv($uptimeSec % 3600, 60);
        $uptimeFormatted = $days > 0 ? "{$days} д {$hours} ч" : "{$hours} ч {$minutes} мин";

        // 2. CPU
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        $cores = $cpuInfo ? max(1, substr_count($cpuInfo, 'processor')) : 1;
        $loads = sys_getloadavg();
        $load1m = round($loads[0] ?? 0.0, 2);
        $load5m = round($loads[1] ?? 0.0, 2);
        $load15m = round($loads[2] ?? 0.0, 2);
        $cpuPercent = min(100.0, round(($load1m / $cores) * 100, 1));

        // 3. RAM
        $meminfo = @file_get_contents('/proc/meminfo');
        $totalMem = 0;
        $availMem = 0;
        if ($meminfo) {
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
                $totalMem = (int) $m[1];
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
                $availMem = (int) $m[1];
            }
        }
        $usedMem = max(0, $totalMem - $availMem);
        $ramPercent = $totalMem > 0 ? round(($usedMem / $totalMem) * 100, 1) : 0.0;

        // 4. Disk
        $diskPath = base_path();
        $totalDisk = (float) (@disk_total_space($diskPath) ?: 1);
        $freeDisk = (float) (@disk_free_space($diskPath) ?: 0);
        $usedDisk = max(0.0, $totalDisk - $freeDisk);
        $diskPercent = round(($usedDisk / $totalDisk) * 100, 1);

        // 5. Daemons via DaemonService
        $daemons = [
            'scum-rcon' => $this->daemons->isRunning(DaemonService::RCON),
            'scum-watch' => $this->daemons->isRunning(DaemonService::WATCH),
        ];

        return [
            'uptime' => [
                'seconds' => $uptimeSec,
                'formatted' => $uptimeFormatted,
            ],
            'cpu' => [
                'cores' => $cores,
                'load_1m' => $load1m,
                'load_5m' => $load5m,
                'load_15m' => $load15m,
                'percent' => $cpuPercent,
            ],
            'ram' => [
                'total_mb' => (int) round($totalMem / 1024),
                'used_mb' => (int) round($usedMem / 1024),
                'free_mb' => (int) round($availMem / 1024),
                'percent' => $ramPercent,
            ],
            'disk' => [
                'total_gb' => round($totalDisk / 1073741824, 1),
                'used_gb' => round($usedDisk / 1073741824, 1),
                'free_gb' => round($freeDisk / 1073741824, 1),
                'percent' => $diskPercent,
            ],
            'services' => [
                'nginx' => true,
                'php_fpm' => true,
                'daemons' => $daemons,
            ],
        ];
    }
}
