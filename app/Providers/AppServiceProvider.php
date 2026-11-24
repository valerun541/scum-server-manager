<?php

namespace App\Providers;

use App\Services\Scum\RconService;
use App\Support\SslOptions;
use App\View\Composers\LayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Один RCON-сокет на процесс: иначе демон (ScumWatch + ChatCommandProcessor)
        // держит несколько соединений, а сервер SCUM ограничивает их число.
        $this->app->singleton(RconService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('panel.rate_limit.login_per_minute', 30))->by($request->ip());
        });

        RateLimiter::for('panel', function (Request $request) {
            return Limit::perMinute((int) config('panel.rate_limit.panel_per_minute', 120))->by($request->ip());
        });

        // Поллинг статуса RCON-задач: rconTerm.js опрашивает job каждые ~400мс до 30 сек.
        RateLimiter::for('jobs', function (Request $request) {
            return Limit::perMinute((int) config('panel.rate_limit.jobs_per_minute', 600))->by($request->ip());
        });

        Http::globalOptions(SslOptions::get());
        Http::globalRequestMiddleware(fn ($request) => $request->hasHeader('User-Agent')
            ? $request
            : $request->withHeader('User-Agent', 'SCUM Server Manager/1.0'));

        DB::whenQueryingForLongerThan(500, function (Connection $connection, QueryExecuted $event) {
            Log::channel('scum')->warning("Slow query ({$event->time}ms): {$event->toRawSql()}");
        });

        // Оптимизация SQLite: WAL-журнал, кэш в памяти и быстрый synchronous
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('PRAGMA journal_mode = WAL;');
                DB::statement('PRAGMA synchronous = NORMAL;');
                DB::statement('PRAGMA cache_size = -64000;');
                DB::statement('PRAGMA temp_store = MEMORY;');
            } catch (\Throwable) {
            }
        }

        View::composer('layouts.app', LayoutComposer::class);

        // Гарантируем наличие $errors во всех шаблонах, включая subviews и модалки
        View::composer('*', function ($view) {
            if (! isset($view->getData()['errors'])) {
                $view->with('errors', session()->get('errors') ?? new ViewErrorBag);
            }
        });
    }
}
