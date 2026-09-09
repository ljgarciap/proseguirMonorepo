<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Hallazgo 2026-09-09: UserController (crear/editar/desactivar/restaurar
 * usuarios) nunca llamaba a ActivityLogService — a diferencia de
 * AuthController y RoleController, que sí auditan cada acción relevante.
 * Un cambio de roles hecho por la UI en Gestión de Usuarios no dejaba
 * ningún rastro en activity_logs. Este test cubre los 4 métodos.
 */
class UserControllerActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private DocumentType $docCC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->superadmin = User::create([
            'name' => 'Admin Test', 'email' => 'admin.users@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900333', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin'],
        ]);

        Passport::actingAs($this->superadmin);
    }

    private function headers(): array
    {
        return ['X-Active-Role' => 'superadmin'];
    }

    public function test_crear_usuario_queda_auditado(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Nuevo Operativo',
            'numero_documento' => '900444',
            'tipo_documento_id' => $this->docCC->id,
            'email' => 'nuevo.operativo@test.com',
            'password' => 'password123',
            'roles' => ['operativo'],
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertTrue(
            ActivityLog::where('accion', 'usuario_creado')
                ->where('descripcion', 'like', '%Nuevo Operativo%')
                ->exists()
        );
    }

    public function test_actualizar_roles_de_usuario_queda_auditado_con_antes_y_despues(): void
    {
        $user = User::create([
            'name' => 'Usuario Editable', 'email' => 'editable@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900555', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['operativo'],
        ]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'numero_documento' => $user->numero_documento,
            'tipo_documento_id' => $this->docCC->id,
            'email' => $user->email,
            'roles' => ['operativo', 'superadmin'],
        ], $this->headers());

        $response->assertStatus(200);

        $log = ActivityLog::where('accion', 'usuario_actualizado')
            ->where('entidad_id', $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(['operativo'], $log->metadata['roles_anteriores']);
        $this->assertSame(['operativo', 'superadmin'], $log->metadata['roles_nuevos']);
        $this->assertFalse($log->metadata['password_cambiada']);
    }

    public function test_desactivar_usuario_queda_auditado(): void
    {
        $user = User::create([
            'name' => 'Usuario A Desactivar', 'email' => 'desactivar@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900666', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['operativo'],
        ]);

        $this->deleteJson("/api/users/{$user->id}", [], $this->headers())->assertStatus(204);

        $this->assertTrue(
            ActivityLog::where('accion', 'usuario_desactivado')->where('entidad_id', $user->id)->exists()
        );
    }

    public function test_restaurar_usuario_queda_auditado(): void
    {
        $user = User::create([
            'name' => 'Usuario A Restaurar', 'email' => 'restaurar@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900777', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['operativo'],
        ]);
        $user->delete();

        $this->postJson("/api/users/{$user->id}/restore", [], $this->headers())->assertStatus(200);

        $this->assertTrue(
            ActivityLog::where('accion', 'usuario_restaurado')->where('entidad_id', $user->id)->exists()
        );
    }
}
