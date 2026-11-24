<?php

namespace App\Http\Middleware;

use App\Services\Security\IpWhitelistService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelistMiddleware
{
    public function __construct(private IpWhitelistService $whitelistService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();

        if ($this->whitelistService->isAllowed($clientIp)) {
            return $next($request);
        }

        // Логируем попытку несанкционированного доступа
        Log::warning("SSM Security: IP {$clientIp} blocked by IP Whitelist policy for URI: {$request->path()} [Agent: ".substr($request->userAgent() ?? '', 0, 100).']');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Forbidden: Your IP address is not authorized to access this panel.',
                'ip' => $clientIp,
            ], 403);
        }

        return response()->view('errors.403', [
            'clientIp' => $clientIp,
            'message' => 'Доступ к панели управления ограничен политикой безопасности по белым спискам IP.',
        ], 403);
    }
}
