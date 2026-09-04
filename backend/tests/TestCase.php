<?php

namespace Tests;

use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md): el middleware
     * CheckPermission necesita el catálogo de roles/permisos poblado para
     * funcionar — a diferencia del CheckUserRole viejo (lista de roles
     * embebida en el propio middleware, sin dependencia de BD). Sin esto,
     * cualquier test con `use RefreshDatabase` que ejercite una ruta ya
     * migrada a `checkpermission:` con un usuario NO superadmin (superadmin
     * siempre bypasea) recibe 403 en vez del comportamiento real, porque
     * su BD de test (fresca por RefreshDatabase) no tiene ningún rol ni
     * permiso cargado. Se usa solo `RolesPermissionsSeeder` (no el
     * DatabaseSeeder completo) para no interferir con los fixtures propios
     * de cada test (DocumentType, User, etc. que la mayoría ya crea en su
     * propio setUp()).
     */
    protected $seeder = RolesPermissionsSeeder::class;
}
