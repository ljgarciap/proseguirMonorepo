<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Limpiamos espacios en blanco de ambos lados
        $token = trim($request->bearerToken() ?? '');
        $expectedToken = trim(env('N8N_API_TOKEN') ?: 'MiTokenSuperSecreto123');

        if (empty($token) || $token !== $expectedToken) {
            return response()->json([
                'message' => 'Unauthorized or invalid token',
                'hint' => 'Asegurate de que N8N_API_TOKEN coincida'
            ], 401);
        }

        return $next($request);
    }
}
