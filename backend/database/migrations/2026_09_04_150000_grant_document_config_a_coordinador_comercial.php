<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * SCRUM-327: habilita el acceso a "Config Requisitos" (/document-config)
 * para el rol Coordinador Comercial. RolesPermissionsSeeder ya trae este
 * rol en el catálogo de ambos permisos para instalaciones nuevas, pero su
 * `syncWithoutDetaching` de grants solo corre cuando el PERMISO se crea
 * por primera vez (ver docblock del seeder) — en cualquier ambiente donde
 * el catálogo Fase 2 ya estaba sembrado (test/prod), esta migración es la
 * que efectivamente hace el grant.
 */
return new class extends Migration
{
    private const CLAVES = ['document-config', 'document-requirements:gestionar'];

    public function up(): void
    {
        $rol = Role::where('slug', 'coordinador_comercial')->first();
        if (!$rol) {
            return;
        }

        $permisoIds = Permission::whereIn('clave', self::CLAVES)->pluck('id');
        $rol->permissions()->syncWithoutDetaching($permisoIds);
    }

    public function down(): void
    {
        $rol = Role::where('slug', 'coordinador_comercial')->first();
        if (!$rol) {
            return;
        }

        $permisoIds = Permission::whereIn('clave', self::CLAVES)->pluck('id');
        $rol->permissions()->detach($permisoIds);
    }
};
