<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Destinatario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DestinatarioTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $cc = \App\Models\DocumentType::where('codigo', 'CC')->first() 
            ?? \App\Models\DocumentType::first() 
            ?? \App\Models\DocumentType::create(['nombre' => 'Cedula', 'codigo' => 'CC']);

        // Crear un usuario superadmin para autenticarse
        $this->admin = User::create([
            'name' => 'Super Administrador Test',
            'email' => 'admin.test.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'test_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['superadmin']
        ]);
    }

    /**
     * Test admin can fetch recipients ordered by name.
     */
    public function test_admin_can_list_recipients_ordered_by_name(): void
    {
        Destinatario::create(['nombre' => 'Zacarias', 'email' => 'zacarias@test.com']);
        Destinatario::create(['nombre' => 'Ana', 'email' => 'ana@test.com']);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/destinatarios');

        $response->assertStatus(200);
        
        $data = $response->json();
        // Verificar orden alfabético
        $this->assertEquals('Ana', $data[0]['nombre']);
        $this->assertEquals('Zacarias', $data[count($data) - 1]['nombre']);
    }

    /**
     * Test store validates invalid email format.
     */
    public function test_store_recipients_validates_email_format(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/destinatarios', [
            'nombre' => 'Juan Pérez',
            'email' => 'correo-invalido'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test store prevents duplicates.
     */
    public function test_store_recipients_prevents_duplicate_email(): void
    {
        Destinatario::create(['nombre' => 'Juan', 'email' => 'duplicate@test.com']);

        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/destinatarios', [
            'nombre' => 'Otro Juan',
            'email' => 'duplicate@test.com'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test store creates recipient with default active state.
     */
    public function test_store_creates_recipient_active_by_default(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/destinatarios', [
            'nombre' => 'Pedro',
            'email' => 'pedro@test.com'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('destinatarios', [
            'email' => 'pedro@test.com',
            'activo' => true
        ]);
    }

    /**
     * Test update recipient.
     */
    public function test_admin_can_update_recipient(): void
    {
        $recipient = Destinatario::create(['nombre' => 'Carlos', 'email' => 'carlos@test.com', 'activo' => true]);

        Passport::actingAs($this->admin);

        $response = $this->putJson("/api/destinatarios/{$recipient->id}", [
            'nombre' => 'Carlos Modificado',
            'email' => 'carlos.m@test.com',
            'activo' => false
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('destinatarios', [
            'id' => $recipient->id,
            'nombre' => 'Carlos Modificado',
            'email' => 'carlos.m@test.com',
            'activo' => false
        ]);
    }

    /**
     * Test delete recipient.
     */
    public function test_admin_can_delete_recipient(): void
    {
        $recipient = Destinatario::create(['nombre' => 'Borrar', 'email' => 'borrar@test.com']);

        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/destinatarios/{$recipient->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('destinatarios', [
            'id' => $recipient->id
        ]);
    }
}
