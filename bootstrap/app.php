<?php

use App\Http\Middleware\IpWhitelistMiddleware;
use App\Http\Middleware\ScumAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'scum.auth' => ScumAuth::class,
            'ip.whitelist' => IpWhitelistMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('login')) {
                return redirect()->route('login')->withErrors(['password' => 'Срок действия страницы входа истёк. Пожалуйста, войдите снова.']);
            }

            return redirect()->back()->withInput()->with('error', 'Срок действия страницы истёк. Пожалуйста, повторите действие.');
        });
    })->create();
