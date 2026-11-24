<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Планировщик (schedule:work) — периодические задачи.
// Тики плагинов/расписаний требуют RCON-сокет: schedule ставит задачу в очередь
// scum_jobs, а RCON-демон scum:rcon (единственный владелец сокета) её исполняет.
Schedule::command('scum:dispatch-tick')->everyMinute()->withoutOverlapping()->onOneServer();

// Обслуживание БД/кэша не требует сокета — планировщик исполняет напрямую.
Schedule::command('scum:maintenance')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
