<?php

namespace App\Http\Controllers;

use App\Models\Permission;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Solo lectura: el
 * catálogo de permisos se siembra vía RolesPermissionsSeeder, no se
 * crea/edita desde la UI en Fase 1.
 */
class PermissionController extends Controller
{
    public function index()
    {
        return response()->json(
            Permission::orderBy('modulo')->orderBy('nombre')->get()->groupBy('modulo')
        );
    }
}
