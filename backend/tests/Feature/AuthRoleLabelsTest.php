<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-346 (seguimiento, a pedido explícito de Luis): el nombre de un rol
 * debe salir SIEMPRE de la base de datos, nunca de un mapa hardcodeado en
 * el frontend — ya pasó 2 veces (SCRUM-331, SCRUM-346) que un rename desde
 * Roles y Permisos persistía bien en BD pero pantallas con su propio mapa
 * quedaban desactualizadas. login()/me() ahora mandan 'role_labels' (TODO
 * el catálogo, no solo los roles del usuario) para que el frontend nunca
 * más necesite su propio mapa.
 */
class AuthRoleLabelsTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->usuario = User::create([
            'name' => 'Usuario Role Labels Test',
            'email' => 'role.labels@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'role_labels_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['operativo'],
        ]);

        Role::updateOrCreate(['slug' => 'operativo'], ['nombre' => 'Operativo', 'descripcion' => 'x', 'es_sistema' => true]);
        Role::updateOrCreate(['slug' => 'gerente'], ['nombre' => 'Gerencia', 'descripcion' => 'x', 'es_sistema' => true]);
        // Solo estos 2 roles para simplificar la aserción exacta de abajo —
        // cualquier otro rol que el TestCase base haya sembrado se ignora.
        Role::whereNotIn('slug', ['operativo', 'gerente'])->delete();
    }

    public function test_login_incluye_role_labels_de_todo_el_catalogo(): void
    {
        Client::factory()->asPersonalAccessTokenClient()->create();

        $response = $this->postJson('/api/login', [
            'numero_documento' => 'role_labels_1',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $roleLabels = $response->json('role_labels');
        ksort($roleLabels);
        $this->assertSame([
            'gerente' => 'Gerencia',
            'operativo' => 'Operativo',
        ], $roleLabels);
    }

    public function test_role_labels_refleja_un_rename_hecho_desde_roles_y_permisos_sin_redeploy(): void
    {
        // Simula exactamente el escenario de SCRUM-346: un superadmin
        // renombra el rol desde la pantalla de Roles y Permisos (persiste
        // en BD), sin ningún cambio de código ni redeploy de por medio.
        Role::where('slug', 'operativo')->update(['nombre' => 'Director Administrativo']);

        Passport::actingAs($this->usuario);
        $response = $this->getJson('/api/me');

        $response->assertStatus(200);
        $this->assertSame('Director Administrativo', $response->json('role_labels.operativo'));
    }
}
