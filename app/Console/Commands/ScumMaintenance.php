<?php

namespace App\Console\Commands;

use App\Services\Scum\ScumJobService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Обслуживание БД и кэша — не требует RCON/FTP-сокета, поэтому исполняется
 * планировщиком напрямую, а не внутри демонов.
 */
class ScumMaintenance extends Command
{
    protected $signature = 'scum:maintenance';

    protected $description = 'Чистка старых задач scum_jobs и истёкших строк кэша (без RCON)';

    public function handle(ScumJobService $jobs): int
    {
        try {
            $deleted = $jobs->cleanupOld();
            $this->info("scum_jobs cleaned: {$deleted}");
        } catch (\Throwable $e) {
            $this->error('job cleanup: '.$e->getMessage());
        }

        try {
            // Ротация и чистка старых логов RCON команд (храним последние 20,000 записей)
            $prunedLogs = DB::table('rcon_command_logs')
                ->where('id', '<', function ($query) {
                    $query->selectRaw('MAX(id) - 20000')->from('rcon_command_logs');
                })
                ->delete();
            if ($prunedLogs > 0) {
                $this->info("rcon_command_logs pruned: {$prunedLogs}");
            }
        } catch (\Throwable $e) {
            $this->error('rcon_command_logs cleanup: '.$e->getMessage());
        }

        try {
            // Чистка старых игровых событий (старше 14 дней)
            DB::table('log_events')->where('created_at', '<', now()->subDays(14))->delete();
            DB::table('chat_events')->where('created_at', '<', now()->subDays(14))->delete();
        } catch (\Throwable $e) {
        }

        try {
            // Cache::forever-ключи (последние срабатывания расписаний) не истекают —
            // оставляем их, удаляем только истёкшие строки.
            $pruned = DB::table('cache')->where('expiration', '<', time())->delete();
            $this->info("cache pruned: {$pruned}");
        } catch (\Throwable $e) {
            $this->error('cache prune: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
