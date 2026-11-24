<?php

namespace App\Console\Commands;

use App\Services\Scum\FtpLogWatcherService;
use App\Services\Scum\ServerService;
use Illuminate\Console\Command;

class ScumSyncLogs extends Command
{
    protected $signature = 'scum:sync-logs {--force : Сбросить смещения и перечитать все исторические логи с нуля}';

    protected $description = 'Синхронизация всех логов с FTP-сервера (исторических и новых) в базу данных';

    public function handle(FtpLogWatcherService $watcher, ServerService $servers): int
    {
        $this->info('[SYNC-LOGS] Запуск синхронизации логов игрового сервера...');
        $servers->ensureActiveConfigInSettings();
        $watcher->reloadConfig();

        $force = (bool) $this->option('force');
        if ($force) {
            $this->warn('[SYNC-LOGS] Включён флаг --force: смещения сброшены, все файлы читаются с 0.');
        }

        if (! $watcher->connect()) {
            $this->error('[SYNC-LOGS] Не удалось подключиться к FTP: '.$watcher->status()['lastError']);

            return self::FAILURE;
        }

        $result = $watcher->syncAllLogs($force);

        $this->info('[SYNC-LOGS] '.$result['message']);
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Всего записей в чате (chat_events)', $result['chat_count']],
                ['Всего записей событий (log_events)', $result['log_count']],
            ]
        );

        return self::SUCCESS;
    }
}
