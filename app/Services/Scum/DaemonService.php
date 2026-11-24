<?php

namespace App\Services\Scum;

use Illuminate\Support\Facades\Log;

/**
 * Определяет, реально ли запущены демоны scum:watch и scum:rcon.
 * Демоны пишут heartbeat (PID + время) в storage/daemons/*.json каждый цикл,
 * а статус проверяет: файл свежий + процесс с этим PID жив.
 * Это заменяет 300-секундный TTL state-файлов — при смерти демона статус
 * становится «остановлен» сразу, а не через 5 минут.
 */
class DaemonService
{
    private const HEARTBEAT_TTL = 30;

    public const WATCH = 'watch';

    public const RCON = 'rcon';

    /**
     * Помечает демон как живой (вызывается демоном в каждом цикле).
     */
    public function heartbeat(string $name): void
    {
        try {
            $path = $this->path($name);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $data = [
                'pid' => (int) getmypid(),
                'at' => time(),
                'ts' => now()->toISOString(),
            ];
            @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
        } catch (\Throwable $e) {
            Log::channel('scum')->error("[Daemon] heartbeat {$name} error: ".$e->getMessage());
        }
    }

    /**
     * Живой ли демон: PID в файле совпадает с запущенным процессом.
     */
    public function isRunning(string $name): bool
    {
        $info = $this->info($name);

        return ! empty($info['alive']) && ! empty($info['processAlive']);
    }

    /**
     * Полная информация о демоне для панели.
     */
    public function status(string $name): array
    {
        $info = $this->info($name);

        return [
            'running' => ! empty($info['alive']) && ! empty($info['processAlive']),
            'pid' => $info['pid'] ?? null,
            'heartbeatAt' => $info['at'] ?? null,
            'lastTs' => $info['ts'] ?? null,
            'stale' => ! empty($info['alive']) && empty($info['processAlive']),
        ];
    }

    private function info(string $name): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return ['alive' => false, 'pid' => null, 'at' => null, 'processAlive' => false, 'ts' => null];
        }
        try {
            $raw = @file_get_contents($path);
            $data = $raw ? json_decode($raw, true) : null;
            if (! is_array($data)) {
                return ['alive' => false, 'pid' => null, 'at' => null, 'processAlive' => false, 'ts' => null];
            }
            $pid = (int) ($data['pid'] ?? 0);
            $at = (int) ($data['at'] ?? 0);
            $alive = (time() - $at) < (int) config('panel.daemon.heartbeat_ttl', self::HEARTBEAT_TTL);
            $processAlive = $pid > 0 && $this->isProcessAlive($pid);

            return [
                'alive' => $alive,
                'pid' => $pid,
                'at' => $at,
                'ts' => $data['ts'] ?? null,
                'processAlive' => $processAlive,
            ];
        } catch (\Throwable $e) {
            return ['alive' => false, 'pid' => null, 'at' => null, 'processAlive' => false, 'ts' => null];
        }
    }

    private function isProcessAlive(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            // Linux/macOS — проверка сигнала 0
            return @posix_kill($pid, 0);
        }
        // Windows — проверяем через tasklist
        $output = [];
        $code = 0;
        @exec('tasklist /FI "PID eq '.$pid.'" /NH 2>NUL', $output, $code);

        // В Windows tasklist возвращает имя процесса, если он существует,
        // или "INFO: No tasks are running..." если нет
        return ! empty($output)
            && ! str_contains(strtolower(implode(' ', $output)), 'no tasks are running')
            && preg_match('/\b'.preg_quote((string) $pid, '/').'\b/', implode(' ', $output));
    }

    private function path(string $name): string
    {
        return storage_path('daemons/'.basename($name).'.json');
    }
}
