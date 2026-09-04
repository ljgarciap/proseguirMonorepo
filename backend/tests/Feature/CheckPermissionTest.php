<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md). Cubre el mecanismo
 * de enforcement real (CheckPermission + /api/me) contra el primer grupo
 * migrado (`destinatarios`, superadmin-only) — sin tocar ningún otro
 * módulo todavía.
 */
class CheckPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $operativo;
    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->superadmin = User::create([
            'name' => 'Admin Test', 'email' => 'admin.perm@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '910111', 'tipo_documento_id' => $docCC->id, 'roles' => ['superadmin'],
        ]);
        $this->operativo = User::create([
            'name' => 'Operativo Test', 'email' => 'operativo.perm@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '910222', 'tipo_documento_id' => $docCC->id, 'roles' => ['operativo'],
        ]);
        $this->gerente = User::create([
            'name' => 'Gerente Test', 'email' => 'gerente.perm@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '910333', 'tipo_documento_id' => $docCC->id, 'roles' => ['gerente'],
        ]);
    }

    public function test_sin_autenticar_da_401(): void
    {
        $this->getJson('/api/destinatarios')->assertStatus(401);
    }

    public function test_superadmin_pasa_el_gate_de_permiso(): void
    {
        Passport::actingAs($this->superadmin);
        $this->getJson('/api/destinatarios', ['X-Active-Role' => 'superadmin'])->assertStatus(200);
    }

    public function test_rol_sin_el_permiso_recibe_403_con_clave_requerida(): void
    {
        Passport::actingAs($this->operativo);

        $response = $this->getJson('/api/destinatarios', ['X-Active-Role' => 'operativo']);
        $response->assertStatus(403)->assertJsonPath('permiso_requerido', 'destinatarios');
    }

    public function test_rol_con_el_permiso_asignado_pasa(): void
    {
        // No hay ningún rol semilla más que superadmin con 'destinatarios'
        // asignado — se lo damos a gerente para probar el camino "no
        // superadmin, pero SÍ tiene el permiso".
        $rolGerente = \App\Models\Role::where('slug', 'gerente')->first();
        $permiso = \App\Models\Permission::where('clave', 'destinatarios')->first();
        $rolGerente->permissions()->syncWithoutDetaching([$permiso->id]);

        Passport::actingAs($this->gerente);
        $this->getJson('/api/destinatarios', ['X-Active-Role' => 'gerente'])->assertStatus(200);
    }

    public function test_me_incluye_permissions_y_superadmin_recibe_todo_el_catalogo(): void
    {
        Passport::actingAs($this->superadmin);
        $response = $this->getJson('/api/me')->assertStatus(200);

        $permissions = $response->json('permissions');
        $this->assertContains('destinatarios', $permissions);
        $this->assertSame(\App\Models\Permission::count(), count($permissions));
    }

    public function test_me_incluye_permissions_acotados_a_los_roles_del_usuario(): void
    {
        Passport::actingAs($this->operativo);
        $response = $this->getJson('/api/me')->assertStatus(200);

        $permissions = $response->json('permissions');
        $this->assertNotContains('destinatarios', $permissions);
        $this->assertContains('uploads:validar', $permissions); // acción exclusiva de operativo
    }
}
