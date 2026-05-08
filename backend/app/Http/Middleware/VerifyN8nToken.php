<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $expectedToken = config('services.n8n.token');

        if (!$token || $token !== $expectedToken) {
            return response()->json(['message' => 'Unauthorized or invalid token'], 401);
        }

        return $next($request);
    }
}
