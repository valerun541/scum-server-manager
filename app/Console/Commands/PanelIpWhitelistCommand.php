<?php

namespace App\Console\Commands;

use App\Services\Security\IpWhitelistService;
use Illuminate\Console\Command;

class PanelIpWhitelistCommand extends Command
{
    protected $signature = 'panel:ip-whitelist 
                            {action=list : Действие: list, add, remove, enable, disable}
                            {ip? : IP-адрес или CIDR-диапазон (например 95.31.181.58 или 192.168.1.0/24)}
                            {--label= : Описание или комментарий к IP}';

    protected $description = 'Управление белым списком IP для входа в панель управления SSM';

    public function handle(IpWhitelistService $whitelistService): int
    {
        $action = strtolower(trim($this->argument('action')));
        $ip = $this->argument('ip');
        $label = $this->option('label') ?: '';

        switch ($action) {
            case 'list':
                $enabled = $whitelistService->isEnabled();
                $entries = $whitelistService->getAllowedEntries();

                $this->info('Статус белого списка IP: '.($enabled ? 'ВКЛЮЧЕН (Активен) ✅' : 'ВЫКЛЮЧЕН (Доступ открыт) ⚠️'));
                $this->newLine();

                if (empty($entries)) {
                    $this->warn('Список разрешенных IP пуст.');

                    return self::SUCCESS;
                }

                $rows = [];
                foreach ($entries as $index => $item) {
                    $rows[] = [
                        $index + 1,
                        $item['ip'] ?? '',
                        $item['label'] ?? '—',
                        $item['created_at'] ?? '—',
                        $item['last_seen_at'] ?? '—',
                    ];
                }

                $this->table(['#', 'IP / Подсеть (CIDR)', 'Описание', 'Добавлен', 'Посл. активность'], $rows);

                return self::SUCCESS;

            case 'enable':
                $whitelistService->setEnabled(true);
                $this->info('Белый список IP успешно ВКЛЮЧЕН. Доступ разрешен только для доверенных адресов.');

                return self::SUCCESS;

            case 'disable':
                $whitelistService->setEnabled(false);
                $this->warn('Белый список IP ВЫКЛЮЧЕН. Доступ к панели открыт для всех IP.');

                return self::SUCCESS;

            case 'add':
                if (empty($ip)) {
                    $ip = $this->ask('Введите IP-адрес или CIDR подсеть для добавления (например 95.31.181.58)');
                }
                if (empty($label)) {
                    $label = $this->ask('Описание / Метка', 'Администратор CLI');
                }

                $res = $whitelistService->addEntry($ip, $label);
                if ($res['ok']) {
                    $this->info($res['message']);

                    return self::SUCCESS;
                }
                $this->error($res['message']);

                return self::FAILURE;

            case 'remove':
            case 'delete':
                if (empty($ip)) {
                    $ip = $this->ask('Введите IP-адрес или CIDR подсеть для удаления');
                }

                $res = $whitelistService->removeEntry($ip);
                if ($res['ok']) {
                    $this->info($res['message']);

                    return self::SUCCESS;
                }
                $this->error($res['message']);

                return self::FAILURE;

            default:
                $this->error("Неизвестное действие: {$action}. Доступные действия: list, add, remove, enable, disable");

                return self::FAILURE;
        }
    }
}
