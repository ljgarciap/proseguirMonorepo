<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\CreditoOrdinario;
use App\Models\Cliente;
use App\Models\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditoOrdinarioTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $cliente;
    private $coordinador;
    private $cumplimiento;
    private $operativo;
    private $gerente;
    private $comite;
    private $tesoreria;
    private $docCC;
    private $tipoNatural;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        // Create Cliente User
        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);

        // Create Staff Users
        $this->coordinador = User::create([
            'name' => 'Coordinador Test',
            'email' => 'coordinador.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222333',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['coordinador_comercial']
        ]);

        $this->cumplimiento = User::create([
            'name' => 'Cumplimiento Test',
            'email' => 'cumplimiento.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '333444',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['oficial_cumplimiento']
        ]);

        $this->operativo = User::create([
            'name' => 'Operativo Test',
            'email' => 'operativo.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '444555',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['operativo']
        ]);

        $this->gerente = User::create([
            'name' => 'Gerente Test',
            'email' => 'gerente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '555666',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['gerente']
        ]);

        $this->comite = User::create([
            'name' => 'Comite Test',
            'email' => 'comite.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '666777',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['comite_credito']
        ]);

        $this->tesoreria = User::create([
            'name' => 'Tesoreria Test',
            'email' => 'tesoreria.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '777888',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['tesoreria']
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '888999',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['superadmin']
        ]);
    }

    public function test_full_bpmn_transitions_and_devoluciones(): void
    {
        Passport::actingAs($this->admin);

        // 1. Store/Create Credit Request
        $response = $this->postJson('/api/creditos', [
            'monto' => 50000000.00,
            'plazo_meses' => 24,
            'cliente_id' => $this->cliente->id
        ], ['X-Active-Role' => 'superadmin']);

        $response->assertStatus(201);
        $creditoId = $response->json('id');

        $this->assertDatabaseHas('credito_ordinarios', [
            'id' => $creditoId,
            'estado' => 'revision_documental'
        ]);

        // 2. Coordinador Comercial approves initial documental revision
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar',
            'comentario' => 'Documentación inicial correcta.'
        ], ['X-Active-Role' => 'coordinador_comercial']);

        $response->assertStatus(200);
        $this->assertEquals('analisis_sarlaft_financiero', $response->json('estado'));

        // 3. Parallel Analysis (Oficial de Cumplimiento and Coordinador Comercial upload documents)
        // Cumplimiento uploads sarlft_sintesis
        Passport::actingAs($this->cumplimiento);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'sarlft_sintesis',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'sintesis.pdf'
        ], ['X-Active-Role' => 'oficial_cumplimiento']);
        $response->assertStatus(200);
        $this->assertEquals('analisis_sarlaft_financiero', $response->json('estado'));

        // Cumplimiento uploads sarlft_datacredito
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'sarlft_datacredito',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'datacredito.pdf'
        ], ['X-Active-Role' => 'oficial_cumplimiento']);
        $response->assertStatus(200);

        // Comercial uploads analisis_financiero
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'analisis_financiero',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'analisis.pdf'
        ], ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);

        // Comercial uploads presentacion_comite -> triggers automatic transition to aprobacion_presentacion
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'presentacion_comite',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'presentacion.pdf'
        ], ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $this->assertEquals('aprobacion_presentacion', $response->json('estado'));

        // 4. Gerente rejects presentation first (returns to analysis)
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'rechazar',
            'comentario' => 'Revisar datos financieros'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('analisis_sarlaft_financiero', $response->json('estado'));

        // Re-upload presentacion_comite to trigger back to Gerente
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'presentacion_comite',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'presentacion_v2.pdf'
        ], ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);
        $this->assertEquals('aprobacion_presentacion', $response->json('estado'));

        // Gerente approves presentation
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar',
            'comentario' => 'Presentación lista.'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('comite_evaluacion', $response->json('estado'));

        // 5. Comite de Credito returns (devolver) to Gerente first
        Passport::actingAs($this->comite);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'devolver',
            'comentario' => 'El monto es muy alto, ajustar propuesta'
        ], ['X-Active-Role' => 'comite_credito']);
        $response->assertStatus(200);
        $this->assertEquals('aprobacion_presentacion', $response->json('estado'));

        // Re-approve by Gerente to go back to Comite
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('comite_evaluacion', $response->json('estado'));

        // Comite uploads Acta and approves
        Passport::actingAs($this->comite);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'acta_comite_firmada',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'acta.pdf'
        ], ['X-Active-Role' => 'comite_credito']);
        $response->assertStatus(200);

        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar',
            'comentario' => 'Aprobado por unanimidad.'
        ], ['X-Active-Role' => 'comite_credito']);
        $response->assertStatus(200);
        $this->assertEquals('formalizacion_garantias', $response->json('estado'));

        // 6. Guarantees stage - Cliente uploads signed guarantees
        Passport::actingAs($this->cliente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'garantias_firmadas',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'firmadas.pdf'
        ], ['X-Active-Role' => 'cliente']);
        $response->assertStatus(200);

        // Operativo rejects guarantees first (clears signed file, remains in guarantees)
        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'rechazar',
            'comentario' => 'Falta firma en página 3'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);
        $this->assertEquals('formalizacion_garantias', $response->json('estado'));
        $this->assertNull($response->json('documentos.garantias_firmadas'));

        // Re-upload and approve garantías
        Passport::actingAs($this->cliente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'garantias_firmadas',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'firmadas_corregidas.pdf'
        ], ['X-Active-Role' => 'cliente']);
        $response->assertStatus(200);

        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);
        $this->assertEquals('aprobacion_registro_cyf', $response->json('estado'));

        // 7. CYF Registration - Comercial uploads support
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'registro_cyf',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'cyf_soporte.pdf'
        ], ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);

        // Gerente rejects CYF support (clears file)
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'rechazar',
            'comentario' => 'Soporte borroso'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('aprobacion_registro_cyf', $response->json('estado'));
        $this->assertNull($response->json('documentos.registro_cyf'));

        // Re-upload and approve CYF
        Passport::actingAs($this->coordinador);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'registro_cyf',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'cyf_soporte_clear.pdf'
        ], ['X-Active-Role' => 'coordinador_comercial']);
        $response->assertStatus(200);

        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('desembolso_ingreso', $response->json('estado'));

        // 8. Desembolso Ingreso - Operativo uploads egreso support
        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'desembolso_egreso',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'egreso.pdf'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);

        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);
        $this->assertEquals('desembolso_aprobacion', $response->json('estado'));

        // 9. Desembolso Aprobacion - Gerente devuelves to egreso (clears egreso document)
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'devolver',
            'comentario' => 'Monto de transferencia errado'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('desembolso_ingreso', $response->json('estado'));
        $this->assertNull($response->json('documentos.desembolso_egreso'));

        // Re-upload and approve egreso
        Passport::actingAs($this->operativo);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'desembolso_egreso',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'egreso_v2.pdf'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);

        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo']);
        $response->assertStatus(200);
        $this->assertEquals('desembolso_aprobacion', $response->json('estado'));

        // Approve desembolso by Gerente
        Passport::actingAs($this->gerente);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente']);
        $response->assertStatus(200);
        $this->assertEquals('ejecucion_transferencia', $response->json('estado'));

        // 10. Transferencia - Tesorería uploads transfer support and completes process
        Passport::actingAs($this->tesoreria);
        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo',
            'campo_documento' => 'comprobante_transferencia',
            'archivo' => 'data:application/pdf;base64,dGVzdA==',
            'archivo_nombre' => 'transfer.pdf'
        ], ['X-Active-Role' => 'tesoreria']);
        $response->assertStatus(200);

        $response = $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'tesoreria']);
        $response->assertStatus(200);
        $this->assertEquals('completado', $response->json('estado'));
    }
}
