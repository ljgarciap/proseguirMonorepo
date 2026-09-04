<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Hallazgo de seguridad 2026-09-04 (encontrado durante RBAC Fase 2, tratado
 * a pedido explícito de Luis): GET /api/history/{categoria}/export no
 * tenía ningún middleware de auth/rol — cualquiera sin sesión podía
 * descargar el Excel completo de cartera/operaciones/pagos/confirming/
 * compraventa. Ver docs/specs/rbac-fase2-enforcement.md.
 */
class HistoryExportAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_autenticar_no_puede_descargar_el_export(): void
    {
        $this->getJson('/api/history/cartera/export')->assertStatus(401);
    }

    public function test_rol_sin_el_permiso_no_puede_descargar_el_export(): void
    {
        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $cliente = User::create([
            'name' => 'Cliente Test', 'email' => 'cliente.export@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '920111', 'tipo_documento_id' => $docCC->id, 'roles' => ['cliente'],
        ]);

        Passport::actingAs($cliente);
        $this->getJson('/api/history/cartera/export')->assertStatus(403);
    }

    public function test_rol_autorizado_puede_descargar_el_export(): void
    {
        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $operativo = User::create([
            'name' => 'Operativo Test', 'email' => 'operativo.export@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '920222', 'tipo_documento_id' => $docCC->id, 'roles' => ['operativo'],
        ]);

        Passport::actingAs($operativo);
        $this->getJson('/api/history/cartera/export')->assertStatus(200);
    }
}
