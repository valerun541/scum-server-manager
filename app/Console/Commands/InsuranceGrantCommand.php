<?php

namespace App\Console\Commands;

use App\Services\Scum\InsuranceService;
use Illuminate\Console\Command;

class InsuranceGrantCommand extends Command
{
    protected $signature = 'insurance:grant {steamId : Steam ID игрока} {claims=1 : Количество страховых возвратов} {--days=30 : Срок действия страховки в днях} {--name= : Имя игрока}';

    protected $description = 'Выдать страховой полис на транспорт игроку';

    public function handle(InsuranceService $insurance): int
    {
        $steamId = (string) $this->argument('steamId');
        $claims = max(1, (int) $this->argument('claims'));
        $days = (int) $this->option('days');
        $name = $this->option('name');

        $this->info("Выдача страховки игроку {$steamId}...");

        $res = $insurance->grantInsurance(
            steamId: $steamId,
            playerName: $name,
            purchaseId: 'CLI_MANUAL',
            claims: $claims,
            durationDays: $days
        );

        if ($res['success']) {
            $this->info("УСПЕХ: {$res['message']}");

            return self::SUCCESS;
        }

        $this->error("ОШИБКА: {$res['message']}");

        return self::FAILURE;
    }
}
