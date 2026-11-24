<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Get token from header (X-API-Key, Bearer) or query string
        $token = $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_token')
            ?? $request->query('token');

        // 2. Get valid token from settings or env
        $validToken = config('services.api.token');
        if (! $validToken) {
            $setting = DB::table('app_settings')->where('key', 'api_token')->first();
            $validToken = $setting?->value;
        }

        // If no token exists at all in system, generate one and save
        if (! $validToken) {
            $validToken = 'scum_'.bin2hex(random_bytes(16));
            DB::table('app_settings')->updateOrInsert(
                ['key' => 'api_token'],
                ['value' => $validToken, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if (! $token || ! hash_equals((string) $validToken, (string) $token)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized. Invalid or missing API token.',
                'hint' => 'Provide token via X-API-Key header, Authorization: Bearer <token>, or ?api_token=<token>',
            ], 401);
        }

        return $next($request);
    }
}
