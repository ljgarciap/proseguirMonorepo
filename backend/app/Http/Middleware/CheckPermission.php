<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md). Reemplaza
 * CheckUserRole ruta por ruta: en vez de una lista de roles hardcodeada
 * en el propio middleware(), resuelve contra el catálogo paramétrico de
 * Fase 1 (roles → permissions vía role_permission).
 *
 * El bypass de 'superadmin' se mantiene idéntico al de CheckUserRole —
 * superadmin pasa SIEMPRE, incluso si el permiso pedido no lo tiene
 * asignado explícitamente en el catálogo (varios permisos de Fase 1 se
 * sembraron fieles al hardcode viejo, que en algunas pantallas no listaba
 * a superadmin explícitamente — sin este bypass, superadmin perdería
 * acceso por API a esas acciones, aunque nunca las haya usado vía UI).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $clave): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'debug_info' => 'No se encontró un usuario autenticado por Passport.',
            ], 401);
        }

        $userRoles = is_array($user->roles) ? $user->roles : [];

        if (in_array('superadmin', $userRoles, true)) {
            return $next($request);
        }

        $tienePermiso = Role::whereIn('slug', $userRoles)
            ->whereHas('permissions', fn ($q) => $q->where('clave', $clave))
            ->exists();

        if ($tienePermiso) {
            return $next($request);
        }

        return response()->json([
            'message' => 'No tienes autorización para realizar esta acción.',
            'permiso_requerido' => $clave,
        ], 403);
    }
}
