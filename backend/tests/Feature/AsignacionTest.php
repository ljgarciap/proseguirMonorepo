<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notificacion;
use App\Models\Destinatario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AsignacionTest extends TestCase
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
     * Test admin can fetch assignments with counts.
     */
    public function test_admin_can_list_assignments_with_counts(): void
    {
        $notif = Notificacion::create(['nombre' => 'Test Alerta', 'mensaje' => 'msg']);
        $dest = Destinatario::create(['nombre' => 'Juan', 'email' => 'juan@test.com']);
        $notif->destinatarios()->attach($dest->id);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/asignaciones');

        $response->assertStatus(200);
        
        $data = $response->json();
        // Buscar nuestra notificacion en el resultado
        $found = collect($data)->firstWhere('id', $notif->id);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['destinatarios_count']);
    }

    /**
     * Test admin can fetch detailed lists for selector.
     */
    public function test_admin_can_view_assignment_details(): void
    {
        $notif = Notificacion::create(['nombre' => 'Test Alerta', 'mensaje' => 'msg']);
        $dest = Destinatario::create(['nombre' => 'Juan', 'email' => 'juan@test.com', 'activo' => true]);
        $dest2 = Destinatario::create(['nombre' => 'Pedro', 'email' => 'pedro@test.com', 'activo' => true]);
        $dest3 = Destinatario::create(['nombre' => 'Inactivo', 'email' => 'inactivo@test.com', 'activo' => false]);
        
        $notif->destinatarios()->attach($dest->id);

        Passport::actingAs($this->admin);

        $response = $this->getJson("/api/asignaciones/{$notif->id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals($notif->id, $data['notificacion']['id']);
        
        // Asignados
        $this->assertCount(1, $data['asignados']);
        $this->assertEquals('Juan', $data['asignados'][0]['nombre']);
        
        // Activos totales (para disponibles) - pedro y juan deben salir, inactivo no!
        $activosIds = collect($data['activos'])->pluck('id');
        $this->assertTrue($activosIds->contains($dest->id));
        $this->assertTrue($activosIds->contains($dest2->id));
        $this->assertFalse($activosIds->contains($dest3->id)); // Regla de negocio: solo activos cargan
    }

    /**
     * Regla de Negocio: Cannot assign recipients to an inactive notification.
     */
    public function test_cannot_assign_recipients_to_inactive_notification(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Inactiva', 'mensaje' => 'msg', 'activo' => false]);
        $dest = Destinatario::create(['nombre' => 'Juan', 'email' => 'juan@test.com', 'activo' => true]);

        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/asignaciones', [
            'notificacion_id' => $notif->id,
            'destinatario_ids' => [$dest->id]
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se pueden asociar destinatarios a una notificación inactiva.'
        ]);
    }

    /**
     * Regla de Negocio: Cannot assign inactive recipients to notification.
     */
    public function test_cannot_assign_inactive_recipients_to_notification(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Activa', 'mensaje' => 'msg', 'activo' => true]);
        $dest = Destinatario::create(['nombre' => 'Inactivo', 'email' => 'inactivo@test.com', 'activo' => false]);

        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/asignaciones', [
            'notificacion_id' => $notif->id,
            'destinatario_ids' => [$dest->id]
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Solo destinatarios activos pueden asociarse a notificaciones.'
        ]);
    }

    /**
     * Test store syncs assignments atomically.
     */
    public function test_admin_can_save_assignments_massively(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Activa', 'mensaje' => 'msg', 'activo' => true]);
        $dest1 = Destinatario::create(['nombre' => 'Pedro', 'email' => 'pedro@test.com', 'activo' => true]);
        $dest2 = Destinatario::create(['nombre' => 'Maria', 'email' => 'maria@test.com', 'activo' => true]);

        Passport::actingAs($this->admin);

        $response = $this->postJson('/api/asignaciones', [
            'notificacion_id' => $notif->id,
            'destinatario_ids' => [$dest1->id, $dest2->id]
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Asignaciones guardadas correctamente.',
            'cantidad' => 2
        ]);
        
        // Verificar que están en la base de datos
        $this->assertDatabaseHas('re_notificacion_destinatario', [
            'notificacion_id' => $notif->id,
            'destinatario_id' => $dest1->id
        ]);
        $this->assertDatabaseHas('re_notificacion_destinatario', [
            'notificacion_id' => $notif->id,
            'destinatario_id' => $dest2->id
        ]);
    }

    /**
     * Test admin can remove all assignments from a specific notification.
     */
    public function test_admin_can_remove_all_assignments_for_notification(): void
    {
        $notif = Notificacion::create(['nombre' => 'Notif Activa', 'mensaje' => 'msg', 'activo' => true]);
        $dest = Destinatario::create(['nombre' => 'Pedro', 'email' => 'pedro@test.com', 'activo' => true]);
        $notif->destinatarios()->attach($dest->id);

        Passport::actingAs($this->admin);

        $response = $this->deleteJson("/api/asignaciones/{$notif->id}");

        $response->assertStatus(204);
        
        // Verificar que ya no existe la relación
        $this->assertDatabaseMissing('re_notificacion_destinatario', [
            'notificacion_id' => $notif->id
        ]);
    }
}
