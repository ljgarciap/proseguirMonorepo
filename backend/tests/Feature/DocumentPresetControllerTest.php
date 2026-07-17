<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DocumentPresetControllerTest extends TestCase
{
    use RefreshDatabase;

    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => "{$role}.preset@test.com",
            'password' => bcrypt('password'),
            'numero_documento' => 'preset_' . uniqid(),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => [$role],
        ]);
    }

    /**
     * SCRUM-134/141: Coordinador Comercial necesita leer los presets para el
     * dropdown de Registro Solicitud de Crédito, aunque no gestione el CRUD.
     */
    public function test_coordinador_comercial_can_list_but_not_manage_presets(): void
    {
        Passport::actingAs($this->makeUser('coordinador_comercial'));

        $this->getJson('/api/document-presets')->assertStatus(200);
        $this->postJson('/api/document-presets', ['nombre' => 'X', 'requirements' => []])->assertStatus(403);
    }

    public function test_operativo_can_manage_presets(): void
    {
        Passport::actingAs($this->makeUser('operativo'));
        $this->getJson('/api/document-presets')->assertStatus(200);
    }
}
