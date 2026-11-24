<?php

namespace App\Console\Commands;

use App\Services\Scum\ScumJobService;
use Illuminate\Console\Command;

/**
 * Ставит в очередь scum_jobs периодический тик плагинов/расписаний.
 * Планировщик (schedule:work) вызывает эту команду каждую минуту, а исполняет
 * тик RCON-демон scum:rcon — единственный владелец RCON-сокета.
 * Отдельный процесс не может слать RCON-команды: он конфликтовал бы за соединение.
 */
class ScumDispatchTick extends Command
{
    protected $signature = 'scum:dispatch-tick';

    protected $description = 'Поставить в очередь scum_jobs периодический тик плагинов и расписаний';

    public function handle(ScumJobService $jobs): int
    {
        $jobs->enqueue('tick', [
            'tasks' => ['schedules', 'airdrop', 'hourly', 'top'],
            'at' => now()->toISOString(),
        ], priority: 1);

        return self::SUCCESS;
    }
}
