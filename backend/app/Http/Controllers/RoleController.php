<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Catálogo puro: nada acá
 * controla acceso real todavía — protegido con checkrole:superadmin (el
 * mecanismo legacy, no este motor) igual que el resto de pantallas
 * "solo superadmin" (ver routes/api.php).
 */
class RoleController extends Controller
{
    /**
     * Clave del permiso que representa esta misma pantalla — protegido
     * para que 'superadmin' nunca pueda quitárselo a sí mismo (evita
     * bloquearse el acceso a la única pantalla que permite revertirlo).
     */
    private const CLAVE_PERMISO_GESTION_ROLES = 'roles';

    public function __construct(private ActivityLogService $activityLog)
    {
    }

    public function index()
    {
        $roles = Role::with('permissions:id,clave')->orderBy('nombre')->get();

        return response()->json($roles->map(function (Role $rol) {
            return [
                'id' => $rol->id,
                'nombre' => $rol->nombre,
                'slug' => $rol->slug,
                'descripcion' => $rol->descripcion,
                'es_sistema' => $rol->es_sistema,
                'permission_ids' => $rol->permissions->pluck('id'),
                'usuarios_asignados' => $rol->usuariosAsignadosCount(),
            ];
        }));
    }

    public function store(Request $request)
    {
        $validado = $request->validate([
            'nombre' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'slug')],
            'descripcion' => 'nullable|string|max:1000',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $rol = Role::create([
            'nombre' => $validado['nombre'],
            'slug' => $validado['slug'],
            'descripcion' => $validado['descripcion'] ?? null,
            'es_sistema' => false,
        ]);

        $rol->permissions()->sync($validado['permission_ids'] ?? []);

        $this->activityLog->registrar(
            'rol_creado',
            "Se creó el rol \"{$rol->nombre}\" ({$rol->slug}).",
            Auth::user(),
            $rol,
            ['permission_ids' => $validado['permission_ids'] ?? []],
            $request,
        );

        return response()->json($rol->fresh('permissions'), 201);
    }

    public function update(Request $request, Role $role)
    {
        $reglasSlug = $role->es_sistema
            ? ['prohibited']
            : ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'slug')->ignore($role->id)];

        $validado = $request->validate([
            'nombre' => 'required|string|max:255',
            'slug' => $reglasSlug,
            'descripcion' => 'nullable|string|max:1000',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ], [
            'slug.prohibited' => 'El slug de un rol del sistema no se puede editar — el código legacy lo compara literal.',
        ]);

        $permisosNuevos = $validado['permission_ids'] ?? [];

        if ($role->slug === 'superadmin') {
            $permisoGestion = Permission::where('clave', self::CLAVE_PERMISO_GESTION_ROLES)->first();
            if ($permisoGestion && !in_array($permisoGestion->id, $permisosNuevos, true)) {
                return response()->json([
                    'message' => 'No podés quitarle a superadmin el permiso de gestión de roles — te dejaría sin acceso a esta pantalla.',
                ], 422);
            }
        }

        $role->update([
            'nombre' => $validado['nombre'],
            'descripcion' => $validado['descripcion'] ?? null,
        ]);

        $role->permissions()->sync($permisosNuevos);

        $this->activityLog->registrar(
            'rol_actualizado',
            "Se actualizó el rol \"{$role->nombre}\" ({$role->slug}).",
            Auth::user(),
            $role,
            ['permission_ids' => $permisosNuevos],
            $request,
        );

        return response()->json($role->fresh('permissions'));
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->es_sistema) {
            return response()->json([
                'message' => 'Los roles del sistema no se pueden eliminar.',
            ], 422);
        }

        $usuariosAsignados = $role->usuariosAsignadosCount();
        if ($usuariosAsignados > 0) {
            return response()->json([
                'message' => "No se puede eliminar: {$usuariosAsignados} usuario(s) tienen este rol asignado. Reasigná esos usuarios primero.",
                'usuarios_asignados' => $usuariosAsignados,
            ], 422);
        }

        $nombre = $role->nombre;
        $slug = $role->slug;
        $role->delete();

        $this->activityLog->registrar(
            'rol_eliminado',
            "Se eliminó el rol \"{$nombre}\" ({$slug}).",
            Auth::user(),
            null,
            ['slug' => $slug],
            $request,
        );

        return response()->json(null, 204);
    }
}
