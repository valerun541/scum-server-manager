<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('scum_auth')) {
            return $next($request);
        }
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return redirect()->route('login');
    }
}
