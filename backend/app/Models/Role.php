<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'es_sistema',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * users.roles (json) sigue siendo la fuente de verdad real de qué
     * usuario tiene qué rol — no hay tabla user_role (ver nota de
     * corrección en docs/specs/rbac-roles-permisos-parametrico.md). Mismo
     * patrón whereJsonContains que ya usa el resto del código (12 sitios
     * en 9 archivos, ej. GestionCreditoController, GarantiasFormalizacionService).
     */
    public function usuariosAsignadosCount(): int
    {
        return User::whereJsonContains('roles', $this->slug)->count();
    }
}
