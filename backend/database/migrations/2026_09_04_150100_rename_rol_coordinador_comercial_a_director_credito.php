<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * SCRUM-331: Proseguir renombró el cargo "Coordinador Comercial" a
 * "Director de Crédito". El slug interno (coordinador_comercial) NO
 * cambia — sigue siendo el string legacy usado en checkpermission/roles[]
 * en todo el backend y frontend, nunca editable desde /roles (ver
 * docblock de RolesPermissionsSeeder). Esta migración solo actualiza el
 * nombre visible en instalaciones donde el rol ya estaba sembrado —
 * RolesPermissionsSeeder::ROLES trae el nombre nuevo para instalaciones
 * frescas, pero su firstOrCreate nunca sobreescribe el nombre de un rol
 * ya existente (para no revertir una edición manual de un superadmin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::where('slug', 'coordinador_comercial')
            ->where('nombre', 'Coordinador Comercial')
            ->update(['nombre' => 'Director de Crédito']);
    }

    public function down(): void
    {
        Role::where('slug', 'coordinador_comercial')
            ->where('nombre', 'Director de Crédito')
            ->update(['nombre' => 'Coordinador Comercial']);
    }
};
