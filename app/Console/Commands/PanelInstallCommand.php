<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PanelInstallCommand extends Command
{
    protected  = 'panel:install {--force : Перезаписать существующую базу данных}';

    protected  = 'Интерактивный мастер первоначальной установки и настройки SCUM Server Manager';

    public function handle(): int
    {
        ->newLine();
        ->line('<fg=cyan;options=bold>=============================================================</>');
        ->line('<fg=cyan;options=bold>   SCUM SERVER MANAGER (SSM) — МАСТЕР УСТАНОВКИ             </>');
        ->line('<fg=cyan;options=bold>=============================================================</>');
        ->newLine();

        // 1. Проверка и создание .env
        ->info('1/5. Настройка файла конфигурации (.env)...');
         = base_path('.env');
         = base_path('.env.example');

        if (!File::exists()) {
            if (File::exists()) {
                File::copy(, );
                ->line('  <fg=green>✓</> Файл .env успешно создан из .env.example');
            } else {
                ->error('  ✗ Файл .env.example не найден!');
                return self::FAILURE;
            }
        } else {
            ->line('  <fg=yellow>ℹ</> Файл .env уже существует, пропускаем.');
        }

        // 2. Генерация APP_KEY
        ->info('2/5. Проверка ключа шифрования приложения (APP_KEY)...');
        if (empty(config('app.key'))) {
            ->call('key:generate', ['--force' => true]);
            ->line('  <fg=green>✓</> Ключ APP_KEY сгенерирован.');
        } else {
            ->line('  <fg=yellow>ℹ</> Ключ APP_KEY уже установлен.');
        }

        // 3. Создание файла SQLite базы данных
        ->info('3/5. Подготовка локальной базы данных SQLite...');
         = database_path();
         = database_path('database.sqlite');

        if (!File::isDirectory()) {
            File::makeDirectory(, 0755, true);
        }

        if (!File::exists()) {
            File::put(, '');
            ->line('  <fg=green>✓</> Создан файл базы данных: ' . );
        } else {
            ->line('  <fg=yellow>ℹ</> Файл базы данных уже существует.');
        }

        // 4. Выполнение миграций
        ->info('4/5. Применение миграций схемы базы данных...');
         = ['--force' => true];
        if (->option('force')) {
            ->call('migrate:fresh', );
        } else {
            ->call('migrate', );
        }
        ->line('  <fg=green>✓</> Все таблицы успешно созданы.');

        // 5. Создание учетной записи администратора
        ->info('5/5. Создание учетной записи первого администратора...');
        ->call('panel:user');

        // Итог
        ->newLine();
        ->line('<fg=green;options=bold>=============================================================</>');
        ->line('<fg=green;options=bold>   ✓ УСТАНОВКА УСПЕШНО ЗАВЕРШЕНА!                           </>');
        ->line('<fg=green;options=bold>=============================================================</>');
        ->newLine();
        ->line('Для запуска панели в режиме разработки выполните:');
        ->line('  <fg=bright-cyan>php artisan serve</>');
        ->line('И откройте в браузере: <fg=bright-blue>http://127.0.0.1:8000</>');
        ->newLine();

        return self::SUCCESS;
    }
}
