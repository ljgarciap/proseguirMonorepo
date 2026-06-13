<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Mandato;
use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MandatoTest extends TestCase
{
    use RefreshDatabase;

    private $cliente;
    private $operativo;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111111',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo']
        ]);
    }

    public function test_client_can_create_mandate(): void
    {
        Passport::actingAs($this->cliente);

        $payload = [
            'mandante_razon_social' => 'Empresa Mandante',
            'mandante_tipo_documento' => 'CC',
            'mandante_numero_documento' => '123',
            'mandante_domicilio' => 'Domicilio Mandante',
            'mandante_direccion' => 'Calle 123',
            'mandante_telefono' => '555-1234',
            'mandante_rep_legal_nombre' => 'Rep Mandante',
            'mandante_rep_legal_tipo_doc' => 'CC',
            'mandante_rep_legal_num_doc' => '456',
            'mandante_rep_legal_email' => 'rep@mandante.com',
            'factor_razon_social' => 'Empresa Factor',
            'factor_tipo_documento' => 'NIT',
            'factor_numero_documento' => '999',
            'factor_rep_legal_nombre' => 'Rep Factor',
            'factor_rep_legal_tipo_doc' => 'CC',
            'factor_rep_legal_num_doc' => '789',
            'factor_rep_legal_email' => 'rep@factor.com',
        ];

        $response = $this->postJson('/api/mandatos', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mandatos', [
            'mandante_razon_social' => 'Empresa Mandante',
            'status' => 'pendiente'
        ]);
    }

    public function test_admin_can_update_mandate(): void
    {
        $mandato = Mandato::create([
            'user_id' => $this->cliente->id,
            'mandante_razon_social' => 'Empresa Mandante',
            'mandante_tipo_documento' => 'CC',
            'mandante_numero_documento' => '123',
            'mandante_domicilio' => 'Domicilio Mandante',
            'mandante_direccion' => 'Calle 123',
            'mandante_telefono' => '555-1234',
            'mandante_rep_legal_nombre' => 'Rep Mandante',
            'mandante_rep_legal_tipo_doc' => 'CC',
            'mandante_rep_legal_num_doc' => '456',
            'mandante_rep_legal_email' => 'rep@mandante.com',
            'factor_razon_social' => 'Empresa Factor',
            'factor_tipo_documento' => 'NIT',
            'factor_numero_documento' => '999',
            'factor_rep_legal_nombre' => 'Rep Factor',
            'factor_rep_legal_tipo_doc' => 'CC',
            'factor_rep_legal_num_doc' => '789',
            'factor_rep_legal_email' => 'rep@factor.com',
            'status' => 'pendiente'
        ]);

        Passport::actingAs($this->operativo);

        $payload = array_merge($mandato->toArray(), [
            'mandante_razon_social' => 'Empresa Mandante Modificada',
            'status' => 'firmado',
            'observaciones' => 'Todo correcto.'
        ]);

        $response = $this->putJson("/api/mandatos/{$mandato->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mandatos', [
            'id' => $mandato->id,
            'mandante_razon_social' => 'Empresa Mandante Modificada',
            'status' => 'firmado',
            'observaciones' => 'Todo correcto.'
        ]);
    }

    public function test_admin_can_delete_mandate(): void
    {
        $mandato = Mandato::create([
            'user_id' => $this->cliente->id,
            'mandante_razon_social' => 'Empresa Mandante',
            'mandante_tipo_documento' => 'CC',
            'mandante_numero_documento' => '123',
            'mandante_domicilio' => 'Domicilio Mandante',
            'mandante_direccion' => 'Calle 123',
            'mandante_telefono' => '555-1234',
            'mandante_rep_legal_nombre' => 'Rep Mandante',
            'mandante_rep_legal_tipo_doc' => 'CC',
            'mandante_rep_legal_num_doc' => '456',
            'mandante_rep_legal_email' => 'rep@mandante.com',
            'factor_razon_social' => 'Empresa Factor',
            'factor_tipo_documento' => 'NIT',
            'factor_numero_documento' => '999',
            'factor_rep_legal_nombre' => 'Rep Factor',
            'factor_rep_legal_tipo_doc' => 'CC',
            'factor_rep_legal_num_doc' => '789',
            'factor_rep_legal_email' => 'rep@factor.com',
            'status' => 'pendiente'
        ]);

        Passport::actingAs($this->operativo);

        $response = $this->deleteJson("/api/mandatos/{$mandato->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('mandatos', [
            'id' => $mandato->id
        ]);
    }

    public function test_admin_can_export_mandate(): void
    {
        $mandato = Mandato::create([
            'user_id' => $this->cliente->id,
            'mandante_razon_social' => 'Empresa Mandante',
            'mandante_tipo_documento' => 'CC',
            'mandante_numero_documento' => '123',
            'mandante_domicilio' => 'Domicilio Mandante',
            'mandante_direccion' => 'Calle 123',
            'mandante_telefono' => '555-1234',
            'mandante_rep_legal_nombre' => 'Rep Mandante',
            'mandante_rep_legal_tipo_doc' => 'CC',
            'mandante_rep_legal_num_doc' => '456',
            'mandante_rep_legal_email' => 'rep@mandante.com',
            'factor_razon_social' => 'Empresa Factor',
            'factor_tipo_documento' => 'NIT',
            'factor_numero_documento' => '999',
            'factor_rep_legal_nombre' => 'Rep Factor',
            'factor_rep_legal_tipo_doc' => 'CC',
            'factor_rep_legal_num_doc' => '789',
            'factor_rep_legal_email' => 'rep@factor.com',
            'status' => 'firmado'
        ]);

        Passport::actingAs($this->operativo);

        $response = $this->get("/api/mandatos/{$mandato->id}/export");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
