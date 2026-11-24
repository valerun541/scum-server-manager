<?php

namespace App\Console\Commands;

use App\Services\Scum\AutomationService;
use App\Services\Scum\PluginService;
use Illuminate\Console\Command;

class ScumProcessSchedules extends Command
{
    protected $signature = 'scum:process-schedules';

    protected $description = 'Обработка расписаний автоматизации, авто-дропа и часовых наград';

    public function handle(AutomationService $automation, PluginService $plugins): int
    {
        try {
            $automation->processSchedules();
        } catch (\Throwable $e) {
            $this->error('[automation] '.$e->getMessage());
        }
        try {
            $plugins->autoDropTick();
        } catch (\Throwable $e) {
            $this->error('[airdrop] '.$e->getMessage());
        }
        try {
            $plugins->hourlyRewardsTick();
        } catch (\Throwable $e) {
            $this->error('[rewards] '.$e->getMessage());
        }
        try {
            $plugins->topRewardsTick();
        } catch (\Throwable $e) {
            $this->error('[top] '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
