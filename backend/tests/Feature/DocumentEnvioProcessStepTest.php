<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DocumentEnvioProcessStepTest extends TestCase
{
    use RefreshDatabase;

    private $sender;
    private $contabilidad;
    private $gerencia;
    private $categoria;
    private $prioridad;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->sender = $this->makeUser('operativo', 'sender');
        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja']);
        $this->contabilidad = DocumentArea::create(['nombre' => 'Contabilidad', 'codigo' => 'contable']);
        $this->gerencia = DocumentArea::create(['nombre' => 'Gerencia', 'codigo' => 'gerente']);
    }

    private function makeUser(string $role, string $suffix): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . $suffix,
            'email' => "{$role}.{$suffix}@test.com",
            'password' => bcrypt('password'),
            'numero_documento' => crc32($role . $suffix),
            'tipo_documento_id' => $this->docCC->id,
            'roles' => [$role],
        ]);
    }

    private function makeEnvio(): DocumentEnvio
    {
        $envio = DocumentEnvio::create([
            'sender_id' => $this->sender->id,
            'titulo' => 'Reporte Mensual',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado_general' => 'pendiente',
            'current_step_order' => 1,
        ]);

        DocumentEnvioStep::create([
            'envio_id' => $envio->id, 'orden' => 1, 'area_id' => $this->contabilidad->id, 'estado' => 'pendiente',
        ]);
        DocumentEnvioStep::create([
            'envio_id' => $envio->id, 'orden' => 2, 'area_id' => $this->gerencia->id, 'estado' => 'pendiente',
        ]);

        return $envio;
    }

    public function test_processing_first_step_advances_to_next_step(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $contable = $this->makeUser('contable', 'c1');

        Passport::actingAs($contable);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('estado_general', 'en_proceso');
        $response->assertJsonPath('current_step_order', 2);
        $response->assertJsonPath('steps.0.estado', 'procesado');
        $response->assertJsonPath('steps.1.estado', 'pendiente');
    }

    public function test_processing_last_step_finalizes_envio(): void
    {
        $envio = $this->makeEnvio();
        $envio->steps()->where('orden', 1)->first()->update(['estado' => 'procesado']);
        $envio->update(['current_step_order' => 2]);
        $step2 = $envio->steps()->where('orden', 2)->first();

        $gerente = $this->makeUser('gerente', 'g1');

        Passport::actingAs($gerente);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step2->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('estado_general', 'procesado');
        $response->assertJsonPath('steps.1.estado', 'procesado');
    }

    public function test_devolver_requires_observacion(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $contable = $this->makeUser('contable', 'c2');

        Passport::actingAs($contable);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'devolver',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['observacion']);
    }

    public function test_devolver_is_terminal_and_does_not_advance_route(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $contable = $this->makeUser('contable', 'c3');

        Passport::actingAs($contable);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'devolver',
            'observacion' => 'Factura ilegible, subir de nuevo.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('estado_general', 'devuelto');
        $response->assertJsonPath('current_step_order', 1);
        $response->assertJsonPath('steps.0.estado', 'devuelto');
        $response->assertJsonPath('steps.0.observacion', 'Factura ilegible, subir de nuevo.');
    }

    public function test_wrong_area_cannot_process_step(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $gerente = $this->makeUser('gerente', 'g2');

        Passport::actingAs($gerente);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_process_future_step_out_of_turn(): void
    {
        $envio = $this->makeEnvio();
        $step2 = $envio->steps()->where('orden', 2)->first();
        $gerente = $this->makeUser('gerente', 'g3');

        Passport::actingAs($gerente);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step2->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(422);
    }

    public function test_superadmin_can_process_any_step(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $admin = $this->makeUser('superadmin', 'sa1');

        Passport::actingAs($admin);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(200);
    }
}
