<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApiTokenCommand extends Command
{
    protected $signature = 'api:token {--generate : Сгенерировать новый токен API}';

    protected $description = 'Просмотр или генерация токена API для внешних приложений';

    public function handle(): int
    {
        $setting = DB::table('app_settings')->where('key', 'api_token')->first();
        $token = $setting?->value;

        if ($this->option('generate') || ! $token) {
            $token = 'scum_'.bin2hex(random_bytes(16));
            DB::table('app_settings')->updateOrInsert(
                ['key' => 'api_token'],
                ['value' => $token, 'created_at' => now(), 'updated_at' => now()]
            );
            $this->info('Новый токен API успешно создан:');
        } else {
            $this->info('Текущий токен API для внешних приложений:');
        }

        $this->line("<comment>{$token}</comment>");
        $this->newLine();
        $this->line('Использование в сторонних приложениях:');
        $this->line("  Заголовок:  X-API-Key: {$token}");
        $this->line("  Или Bearer: Authorization: Bearer {$token}");
        $this->line("  Или URL:    ?api_token={$token}");

        return self::SUCCESS;
    }
}
