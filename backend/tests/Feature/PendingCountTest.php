<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\InternalDocument;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PendingCountTest extends TestCase
{
    use RefreshDatabase;

    private $viewer;
    private $sender;
    private $categoria;
    private $prioridad;
    private $docCC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docCC = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);
        $this->viewer = $this->makeUser('superadmin', 'viewer');
        $this->sender = $this->makeUser('operativo', 'sender');
        $this->categoria = AccountingCategory::create(['nombre' => 'Extractos Bancarios']);
        $this->prioridad = AccountingPriority::create(['nombre' => 'Baja', 'horas_vencimiento' => 24]);
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

    private function makeEnvioWithStep(string $areaCodigo, int $orden, int $currentStepOrder, string $stepEstado = 'pendiente', ?int $horasVencimiento = 24): DocumentEnvio
    {
        $area = DocumentArea::create(['nombre' => ucfirst($areaCodigo), 'codigo' => $areaCodigo . '_' . uniqid()]);
        // Usamos siempre el codigo real esperado por el badge (contable/gerente/operativo)
        $area->update(['codigo' => $areaCodigo]);

        $prioridad = $horasVencimiento
            ? AccountingPriority::create(['nombre' => 'Prio ' . uniqid(), 'horas_vencimiento' => $horasVencimiento])
            : $this->prioridad;

        $envio = DocumentEnvio::create([
            'sender_id' => $this->sender->id,
            'titulo' => 'Envio Test',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $prioridad->id,
            'estado_general' => 'pendiente',
            'current_step_order' => $currentStepOrder,
        ]);

        DocumentEnvioStep::create([
            'envio_id' => $envio->id, 'orden' => $orden, 'area_id' => $area->id, 'estado' => $stepEstado,
        ]);

        return $envio;
    }

    public function test_pending_step_at_current_turn_is_counted_for_its_area(): void
    {
        $this->makeEnvioWithStep('gerente', 1, 1);

        Passport::actingAs($this->viewer);
        $response = $this->getJson('/api/uploads/pending-count')->assertStatus(200);

        $this->assertEquals(1, $response->json('internal_gerente'));
    }

    public function test_future_step_not_at_current_turn_is_not_counted(): void
    {
        // orden=2 pero current_step_order=1 => todavía no es su turno
        $this->makeEnvioWithStep('gerente', 2, 1);

        Passport::actingAs($this->viewer);
        $response = $this->getJson('/api/uploads/pending-count')->assertStatus(200);

        $this->assertEquals(0, $response->json('internal_gerente'));
    }

    public function test_counts_from_old_and_new_model_are_summed(): void
    {
        InternalDocument::create([
            'sender_id' => $this->sender->id,
            'target_role' => 'contable',
            'titulo' => 'Viejo',
            'archivo_path' => 'internal_docs/viejo.pdf',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado' => 'pendiente',
        ]);
        $this->makeEnvioWithStep('contable', 1, 1);

        Passport::actingAs($this->viewer);
        $response = $this->getJson('/api/uploads/pending-count')->assertStatus(200);

        $this->assertEquals(2, $response->json('contable'));
    }

    public function test_migrated_legacy_document_is_not_double_counted(): void
    {
        $legacy = InternalDocument::create([
            'sender_id' => $this->sender->id,
            'target_role' => 'contable',
            'titulo' => 'Ya migrado',
            'archivo_path' => 'internal_docs/migrado.pdf',
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'estado' => 'pendiente',
        ]);

        // Simula que este documento ya fue migrado a la Bandeja Interna nueva
        // (SCRUM-94, ver MigrateLegacyInternalDocuments): existe un DocumentEnvio
        // con el legacy_batch_key correspondiente. Sin batch_id, la clave es
        // 'single_{id}', igual que en el comando de migración.
        $envio = $this->makeEnvioWithStep('contable', 1, 1);
        $envio->update(['legacy_batch_key' => 'single_' . $legacy->id]);

        Passport::actingAs($this->viewer);
        $response = $this->getJson('/api/uploads/pending-count')->assertStatus(200);

        // Debe contarse una sola vez (vía el DocumentEnvio migrado), no dos.
        $this->assertEquals(1, $response->json('contable'));
    }

    public function test_expiring_soon_envio_is_flagged(): void
    {
        // horas_vencimiento = 1 y creado ahora => vence en <2h
        $this->makeEnvioWithStep('operativo', 1, 1, 'pendiente', 1);

        Passport::actingAs($this->viewer);
        $response = $this->getJson('/api/uploads/pending-count')->assertStatus(200);

        $this->assertEquals(1, $response->json('expiring_operativo'));
    }

    /**
     * El endpoint ya calcula y devuelve el contador de 'contable' (bandeja
     * interna) — ese rol también necesita poder consultarlo para su badge.
     */
    public function test_contable_role_can_access_pending_count(): void
    {
        Passport::actingAs($this->makeUser('contable', 'viewer2'));
        $this->getJson('/api/uploads/pending-count')->assertStatus(200);
    }
}
