<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\ApiTokenMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for External Applications
|--------------------------------------------------------------------------
| Все эндпоинты защищены через ApiTokenMiddleware (X-API-Key или Bearer токен).
*/

// Enterprise Health Check (Observability)
Route::get('/health', [HealthController::class, 'check'])->name('api.health');

Route::prefix('v1')->middleware([ApiTokenMiddleware::class])->group(function () {
    // Статус и возможности API
    Route::get('/', [ApiController::class, 'status'])->name('api.v1.status');
    Route::get('/status', [ApiController::class, 'status'])->name('api.v1.status.alias');

    // 1. Определение количества игроков на сервере
    Route::get('/server/online', [ApiController::class, 'onlineCount'])->name('api.v1.server.online');
    Route::get('/server/count', [ApiController::class, 'onlineCount'])->name('api.v1.server.count');

    // 2. Получение списка онлайн игроков (с деньгами, золотом, славой, отрядом)
    Route::get('/players/online', [ApiController::class, 'onlinePlayers'])->name('api.v1.players.online');

    // 3. Получение информации конкретного игрока (по SteamID, имени или profileId)
    Route::get('/players/{identifier}', [ApiController::class, 'player'])->name('api.v1.players.show');

    // Поиск игроков в базе данных
    Route::get('/players', [ApiController::class, 'players'])->name('api.v1.players.index');
});
