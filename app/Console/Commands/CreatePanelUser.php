<?php

namespace App\Console\Commands;

use App\Services\Scum\SettingsService;
use App\Support\SecureValues;
use Illuminate\Console\Command;

class CreatePanelUser extends Command
{
    protected $signature = 'panel:user {--username= : Имя пользователя для входа в панель} {--password= : Пароль для входа в панель}';

    protected $description = 'Создать/обновить пользователя панели администратора (webPanel)';

    public function handle(SettingsService $settings): int
    {
        $username = $this->option('username');
        $password = $this->option('password');

        if (empty($username)) {
            $username = $this->ask('Имя пользователя для панели администратора');
        }
        if (empty($password)) {
            $password = $this->secret('Пароль для панели администратора');
            $confirm = $this->secret('Повторите пароль');
        } else {
            $confirm = $password;
        }
        if ($password !== $confirm) {
            $this->error('Пароли не совпадают');

            return self::FAILURE;
        }
        if (empty($username) || empty($password)) {
            $this->error('Имя пользователя и пароль не должны быть пустыми');

            return self::FAILURE;
        }

        $settings->saveAppConfig([
            'webPanel' => [
                'enabled' => true,
                'username' => $username,
                'password' => SecureValues::hashPassword($password),
            ],
        ]);

        $this->info("Пользователь панели «{$username}» сохранён. Вход: логин = {$username}");

        return self::SUCCESS;
    }
}
