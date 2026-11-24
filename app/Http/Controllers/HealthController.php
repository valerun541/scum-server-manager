<?php

namespace App\Http\Controllers;

use App\Services\System\SystemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __construct(private SystemMetricsService $metrics) {}

    public function check(): JsonResponse
    {
        $dbOk = true;
        $dbError = null;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        $metrics = $this->metrics->getMetrics();
        $diskOk = $metrics['disk']['percent'] < 95.0;
        $ramOk = $metrics['ram']['percent'] < 98.0;

        $daemons = $metrics['services']['daemons'];
        $daemonsOk = ! in_array(false, $daemons, true);

        $isHealthy = $dbOk && $diskOk && $ramOk;
        $status = $isHealthy ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'app' => 'SCUM Server Manager',
            'version' => '2.0-enterprise',
            'checks' => [
                'database' => [
                    'status' => $dbOk ? 'ok' : 'error',
                    'error' => $dbError,
                ],
                'disk' => [
                    'status' => $diskOk ? 'ok' : 'warning',
                    'used_percent' => $metrics['disk']['percent'],
                    'free_gb' => $metrics['disk']['free_gb'],
                ],
                'memory' => [
                    'status' => $ramOk ? 'ok' : 'warning',
                    'used_percent' => $metrics['ram']['percent'],
                    'free_mb' => $metrics['ram']['free_mb'],
                ],
                'daemons' => [
                    'status' => $daemonsOk ? 'ok' : 'warning',
                    'list' => $daemons,
                ],
            ],
            'metrics' => [
                'uptime' => $metrics['uptime']['formatted'],
                'cpu_load' => [
                    '1m' => $metrics['cpu']['load_1m'],
                    '5m' => $metrics['cpu']['load_5m'],
                    '15m' => $metrics['cpu']['load_15m'],
                ],
            ],
        ], $isHealthy ? 200 : 503);
    }
}
