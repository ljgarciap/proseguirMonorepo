<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Cubre el catálogo puro:
 * ningún test acá verifica enforcement real (Fase 2 no existe todavía).
 */
class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $operativo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->superadmin = User::create([
            'name' => 'Admin Test', 'email' => 'admin.roles@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900111', 'tipo_documento_id' => $docCC->id, 'roles' => ['superadmin'],
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test', 'email' => 'operativo.roles@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900222', 'tipo_documento_id' => $docCC->id, 'roles' => ['operativo'],
        ]);
    }

    public function test_seeder_replica_los_10_roles_y_sus_permisos(): void
    {
        $this->assertSame(10, Role::count());
        $this->assertGreaterThan(0, Permission::count());

        $ingeniero = Role::where('slug', 'ingeniero')->first();
        $this->assertEqualsCanonicalizing(
            ['informes-tecnicos'],
            $ingeniero->permissions->pluck('clave')->all()
        );

        $superadmin = Role::where('slug', 'superadmin')->first();
        $this->assertTrue($superadmin->permissions->pluck('clave')->contains('roles'));
    }

    public function test_solo_superadmin_accede_a_roles_y_permissions(): void
    {
        Passport::actingAs($this->operativo);

        $this->getJson('/api/roles', ['X-Active-Role' => 'operativo'])->assertStatus(403);
        $this->getJson('/api/permissions', ['X-Active-Role' => 'operativo'])->assertStatus(403);
    }

    public function test_superadmin_puede_listar_roles_con_permisos_y_usuarios_asignados(): void
    {
        Passport::actingAs($this->superadmin);

        $response = $this->getJson('/api/roles', ['X-Active-Role' => 'superadmin'])
            ->assertStatus(200);

        $operativo = collect($response->json())->firstWhere('slug', 'operativo');
        $this->assertNotNull($operativo);
        $this->assertSame(1, $operativo['usuarios_asignados']); // $this->operativo
    }

    public function test_superadmin_puede_crear_rol_nuevo_y_asignarle_permisos(): void
    {
        Passport::actingAs($this->superadmin);

        $permisoDashboard = Permission::where('clave', 'dashboard')->first();

        $response = $this->postJson('/api/roles', [
            'nombre' => 'Auditor Externo',
            'slug' => 'auditor_externo',
            'descripcion' => 'Rol de prueba',
            'permission_ids' => [$permisoDashboard->id],
        ], ['X-Active-Role' => 'superadmin']);

        $response->assertStatus(201)->assertJsonPath('slug', 'auditor_externo');
        $this->assertDatabaseHas('roles', ['slug' => 'auditor_externo', 'es_sistema' => false]);
        $this->assertTrue(
            ActivityLog::where('accion', 'rol_creado')->where('descripcion', 'like', '%Auditor Externo%')->exists()
        );
    }

    public function test_no_permite_slug_duplicado(): void
    {
        Passport::actingAs($this->superadmin);

        $this->postJson('/api/roles', [
            'nombre' => 'Duplicado',
            'slug' => 'operativo', // ya existe (rol semilla)
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(422);
    }

    public function test_rol_creado_se_puede_asignar_a_un_usuario_de_inmediato(): void
    {
        Passport::actingAs($this->superadmin);

        $this->postJson('/api/roles', [
            'nombre' => 'Auditor Externo',
            'slug' => 'auditor_externo',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(201);

        $docCC = DocumentType::first();
        $response = $this->postJson('/api/users', [
            'name' => 'Nuevo Auditor',
            'numero_documento' => '900333',
            'tipo_documento_id' => $docCC->id,
            'password' => 'password123',
            'roles' => ['auditor_externo'],
        ], ['X-Active-Role' => 'superadmin']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['numero_documento' => '900333']);
    }

    public function test_no_se_puede_editar_slug_de_rol_del_sistema(): void
    {
        Passport::actingAs($this->superadmin);

        $operativo = Role::where('slug', 'operativo')->first();

        $this->putJson("/api/roles/{$operativo->id}", [
            'nombre' => 'Operativo',
            'slug' => 'operativo_renombrado',
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(422);
    }

    public function test_no_se_puede_quitar_a_superadmin_su_propio_permiso_de_gestion(): void
    {
        Passport::actingAs($this->superadmin);

        $superadmin = Role::where('slug', 'superadmin')->first();
        $otrosPermisos = $superadmin->permissions->pluck('id')
            ->reject(fn ($id) => $id === Permission::where('clave', 'roles')->value('id'))
            ->all();

        $this->putJson("/api/roles/{$superadmin->id}", [
            'nombre' => 'Super Administrador',
            'permission_ids' => $otrosPermisos, // sin 'roles'
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(422);

        // El permiso sigue intacto tras el intento rechazado.
        $this->assertTrue($superadmin->fresh()->permissions->pluck('clave')->contains('roles'));
    }

    public function test_no_se_puede_eliminar_rol_del_sistema(): void
    {
        Passport::actingAs($this->superadmin);

        $operativo = Role::where('slug', 'operativo')->first();

        $this->deleteJson("/api/roles/{$operativo->id}", [], ['X-Active-Role' => 'superadmin'])
            ->assertStatus(422);
        $this->assertDatabaseHas('roles', ['slug' => 'operativo']);
    }

    public function test_no_se_puede_eliminar_rol_con_usuarios_asignados(): void
    {
        Passport::actingAs($this->superadmin);

        $this->postJson('/api/roles', ['nombre' => 'Auditor Externo', 'slug' => 'auditor_externo'], ['X-Active-Role' => 'superadmin']);
        $rol = Role::where('slug', 'auditor_externo')->first();

        $docCC = DocumentType::first();
        User::create([
            'name' => 'Con Rol Custom', 'email' => 'custom.roles@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '900444', 'tipo_documento_id' => $docCC->id, 'roles' => ['auditor_externo'],
        ]);

        $response = $this->deleteJson("/api/roles/{$rol->id}", [], ['X-Active-Role' => 'superadmin']);
        $response->assertStatus(422)->assertJsonPath('usuarios_asignados', 1);
        $this->assertDatabaseHas('roles', ['slug' => 'auditor_externo']);
    }

    public function test_elimina_rol_custom_sin_usuarios_asignados(): void
    {
        Passport::actingAs($this->superadmin);

        $this->postJson('/api/roles', ['nombre' => 'Auditor Externo', 'slug' => 'auditor_externo'], ['X-Active-Role' => 'superadmin']);
        $rol = Role::where('slug', 'auditor_externo')->first();

        $this->deleteJson("/api/roles/{$rol->id}", [], ['X-Active-Role' => 'superadmin'])->assertStatus(204);
        $this->assertDatabaseMissing('roles', ['slug' => 'auditor_externo']);
        $this->assertTrue(ActivityLog::where('accion', 'rol_eliminado')->exists());
    }

    public function test_asignar_permiso_inexistente_es_rechazado(): void
    {
        Passport::actingAs($this->superadmin);

        $this->postJson('/api/roles', [
            'nombre' => 'Auditor Externo',
            'slug' => 'auditor_externo',
            'permission_ids' => [999999],
        ], ['X-Active-Role' => 'superadmin'])->assertStatus(422);
    }
}
