<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'debug_info' => 'No se encontró un usuario autenticado por Passport.'
            ], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userRoles = $user->roles ?? [];
        
        if (array_intersect($userRoles, $roles) || in_array('superadmin', $userRoles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized role.',
            'required_roles' => $roles,
            'user_roles' => $userRoles
        ], 403);
    }
}
