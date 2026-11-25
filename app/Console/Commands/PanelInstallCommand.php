<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PanelInstallCommand extends Command
{
    protected $signature = 'panel:install {--force : Перезаписать существующую базу данных}';

    protected $description = 'Интерактивный мастер первоначальной установки и настройки SCUM Server Manager';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>=============================================================</>');
        $this->line('<fg=cyan;options=bold>   SCUM SERVER MANAGER (SSM) — МАСТЕР УСТАНОВКИ             </>');
        $this->line('<fg=cyan;options=bold>=============================================================</>');
        $this->newLine();

        // 1. Проверка и создание .env
        $this->info('1/5. Настройка файла конфигурации (.env)...');
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (! File::exists($envPath)) {
            if (File::exists($envExamplePath)) {
                File::copy($envExamplePath, $envPath);
                $this->line('  <fg=green>✓</> Файл .env успешно создан из .env.example');
            } else {
                $this->error('  ✗ Файл .env.example не найден!');

                return self::FAILURE;
            }
        } else {
            $this->line('  <fg=yellow>ℹ</> Файл .env уже существует, пропускаем.');
        }

        // 2. Генерация APP_KEY
        $this->info('2/5. Проверка ключа шифрования приложения (APP_KEY)...');
        if (empty(config('app.key'))) {
            $this->call('key:generate', ['--force' => true]);
            $this->line('  <fg=green>✓</> Ключ APP_KEY сгенерирован.');
        } else {
            $this->line('  <fg=yellow>ℹ</> Ключ APP_KEY уже установлен.');
        }

        // 3. Создание файла SQLite базы данных
        $this->info('3/5. Подготовка локальной базы данных SQLite...');
        $dbDir = database_path();
        $dbPath = database_path('database.sqlite');

        if (! File::isDirectory($dbDir)) {
            File::makeDirectory($dbDir, 0755, true);
        }

        if (! File::exists($dbPath)) {
            File::put($dbPath, '');
            $this->line('  <fg=green>✓</> Создан файл базы данных: '.$dbPath);
        } else {
            $this->line('  <fg=yellow>ℹ</> Файл базы данных уже существует.');
        }

        // Гарантируем актуальный путь к SQLite в рамках текущего процесса
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $dbPath,
        ]);

        // 4. Выполнение миграций
        $this->info('4/5. Применение миграций схемы базы данных...');
        $migrateParams = ['--force' => true];
        if ($this->option('force')) {
            $this->call('migrate:fresh', $migrateParams);
        } else {
            $this->call('migrate', $migrateParams);
        }
        $this->line('  <fg=green>✓</> Все таблицы успешно созданы.');

        // 5. Создание учетной записи администратора
        $this->info('5/5. Создание учетной записи первого администратора...');
        $this->call('panel:user');

        // Итог
        $this->newLine();
        $this->line('<fg=green;options=bold>=============================================================</>');
        $this->line('<fg=green;options=bold>   ✓ УСТАНОВКА УСПЕШНО ЗАВЕРШЕНА!                           </>');
        $this->line('<fg=green;options=bold>=============================================================</>');
        $this->newLine();
        $this->line('Для запуска панели выполните:');
        $this->line('  <fg=bright-cyan>php artisan serve</>');
        $this->line('И откройте в браузере: <fg=bright-blue>http://127.0.0.1:8000</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
