<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notificacion;
use App\Models\Destinatario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

class NotificacionTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $cc = \App\Models\DocumentType::where('codigo', 'CC')->first() 
            ?? \App\Models\DocumentType::first() 
            ?? \App\Models\DocumentType::create(['nombre' => 'Cedula', 'codigo' => 'CC']);

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
     * Test admin can fetch notifications ordered by name.
     */
    public function test_admin_can_list_notifications_ordered_by_name(): void
    {
        Notificacion::create(['nombre' => 'Zeta', 'mensaje' => 'Zeta msg']);
        Notificacion::create(['nombre' => 'Alfa', 'mensaje' => 'Alfa msg']);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/notificaciones');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals('Alfa', $data[0]['nombre']);
        $this->assertEquals('Zeta', $data[count($data) - 1]['nombre']);
    }

    /**
     * Test store validates required fields.
     */
    public function test_store_validates_required_fields(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/notificaciones', [
            'nombre' => '',
            'mensaje' => ''
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombre', 'mensaje']);
    }

    /**
     * Test store creates notification active by default.
     */
    public function test_store_creates_notification_active_by_default(): void
    {
        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/notificaciones', [
            'nombre' => 'Test Alerta',
            'mensaje' => 'Contenido de la alerta'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notificaciones', [
            'nombre' => 'Test Alerta',
            'activo' => true
        ]);
    }

    /**
     * Test update notification.
     */
    public function test_admin_can_update_notification(): void
    {
        $notif = Notificacion::create(['nombre' => 'Viejo', 'mensaje' => 'Viejo msg', 'activo' => true]);

        Passport::actingAs($this->admin);

        $response = $this->putJson("/api/notificaciones/{$notif->id}", [
            'nombre' => 'Nuevo Nombre',
            'mensaje' => 'Nuevo msg',
            'activo' => false
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $notif->id,
            'nombre' => 'Nuevo Nombre',
            'mensaje' => 'Nuevo msg',
            'activo' => false
        ]);
    }

    /**
     * Regla de Negocio: Block deletion if recipients are associated.
     */
    public function test_delete_notification_fails_if_has_assigned_recipients(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Con Dest', 'mensaje' => 'msg', 'activo' => true]);
        $dest = Destinatario::create(['nombre' => 'Juan', 'email' => 'juan@test.com', 'activo' => true]);
        
        // Asociar en la tabla intermedia
        $notif->destinatarios()->attach($dest->id);

        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/notificaciones/{$notif->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se puede eliminar la notificación porque tiene destinatarios asociados.'
        ]);
        
        // Verificar que sigue en base de datos
        $this->assertDatabaseHas('notificaciones', ['id' => $notif->id]);
    }

    /**
     * Regla de Negocio: Allow deletion if no recipients are associated.
     */
    public function test_delete_notification_succeeds_if_no_assigned_recipients(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Libre', 'mensaje' => 'msg', 'activo' => true]);

        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/notificaciones/{$notif->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('notificaciones', ['id' => $notif->id]);
    }
}
