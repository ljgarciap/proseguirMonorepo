<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        // Leemos directo del env por si la caché de config en prod está molestando
        $expectedToken = env('N8N_API_TOKEN', 'MiTokenSuperSecreto123');

        if (!$token || $token !== $expectedToken) {
            return response()->json([
                'message' => 'Unauthorized or invalid token',
                'received' => $token ? 'Token recibido (oculto)' : 'No se recibió token'
            ], 401);
        }

        return $next($request);
    }
}
