<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\CreditoOrdinario;
use App\Models\Cliente;
use App\Models\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->cliente = User::create([
            'name' => 'Cliente Test',
            'email' => 'cliente.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '111222',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['cliente']
        ]);

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

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function subirArchivo(int $creditoId, string $campo, string $rol, string $nombre = 'doc.pdf'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'          => 'subir_archivo',
            'campo_documento' => $campo,
            'archivo'         => $this->pdf($nombre),
        ], ['X-Active-Role' => $rol]);
    }

    public function test_full_bpmn_transitions_and_devoluciones(): void
    {
        Passport::actingAs($this->admin);

        // 1. Create Credit Request
        $response = $this->postJson('/api/creditos', [
            'monto'       => 50000000.00,
            'plazo_meses' => 24,
            'cliente_id'  => $this->cliente->id
        ], ['X-Active-Role' => 'superadmin']);

        $response->assertStatus(201);
        $creditoId = $response->json('id');
        $this->assertDatabaseHas('credito_ordinarios', ['id' => $creditoId, 'estado' => 'revision_documental']);

        // 2. Coordinador approves documental revision
        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Documentación inicial correcta.'
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'analisis_sarlaft_financiero');

        // 3. Parallel analysis: Cumplimiento uploads SARLAFT docs
        Passport::actingAs($this->cumplimiento);
        $this->subirArchivo($creditoId, 'sarlft_sintesis', 'oficial_cumplimiento', 'sintesis.pdf')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'analisis_sarlaft_financiero');

        $this->subirArchivo($creditoId, 'sarlft_datacredito', 'oficial_cumplimiento', 'datacredito.pdf')
            ->assertStatus(200);

        // Coordinador uploads financial docs
        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'analisis_financiero', 'coordinador_comercial', 'analisis.pdf')
            ->assertStatus(200);

        // Last doc triggers auto-transition to aprobacion_presentacion
        $this->subirArchivo($creditoId, 'presentacion_comite', 'coordinador_comercial', 'presentacion.pdf')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_presentacion');

        // 4. Gerente rejects (returns to analisis) then re-approves
        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'rechazar',
            'comentario' => 'Revisar datos financieros'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'analisis_sarlaft_financiero');

        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'presentacion_comite', 'coordinador_comercial', 'presentacion_v2.pdf')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_presentacion');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Presentación lista.'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'comite_evaluacion');

        // 5. Comité devuelve a Gerente, luego re-aprueba
        Passport::actingAs($this->comite);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'devolver',
            'comentario' => 'El monto es muy alto, ajustar propuesta'
        ], ['X-Active-Role' => 'comite_credito'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_presentacion');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'comite_evaluacion');

        // Comité sube acta y aprueba
        Passport::actingAs($this->comite);
        $this->subirArchivo($creditoId, 'acta_comite_firmada', 'comite_credito', 'acta.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'aprobar',
            'comentario' => 'Aprobado por unanimidad.'
        ], ['X-Active-Role' => 'comite_credito'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'formalizacion_garantias');

        // 6. Garantías: cliente sube, operativo rechaza (limpia archivo), cliente re-sube, operativo aprueba
        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'garantias_firmadas', 'cliente', 'firmadas.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'rechazar',
            'comentario' => 'Falta firma en página 3'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'formalizacion_garantias')
            ->assertJsonPath('documentos.garantias_firmadas', null);

        Passport::actingAs($this->cliente);
        $this->subirArchivo($creditoId, 'garantias_firmadas', 'cliente', 'firmadas_corregidas.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->operativo);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_registro_cyf');

        // 7. CYF: comercial sube, gerente rechaza (limpia), comercial re-sube, gerente aprueba
        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'registro_cyf', 'coordinador_comercial', 'cyf_soporte.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'rechazar',
            'comentario' => 'Soporte borroso'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'aprobacion_registro_cyf')
            ->assertJsonPath('documentos.registro_cyf', null);

        Passport::actingAs($this->coordinador);
        $this->subirArchivo($creditoId, 'registro_cyf', 'coordinador_comercial', 'cyf_soporte_clear.pdf')
            ->assertStatus(200);

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_ingreso');

        // 8. Desembolso ingreso: operativo sube egreso y aprueba
        Passport::actingAs($this->operativo);
        $this->subirArchivo($creditoId, 'desembolso_egreso', 'operativo', 'egreso.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_aprobacion');

        // 9. Gerente devuelve desembolso (limpia egreso), operativo re-sube y aprueba
        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion'     => 'devolver',
            'comentario' => 'Monto de transferencia errado'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_ingreso')
            ->assertJsonPath('documentos.desembolso_egreso', null);

        Passport::actingAs($this->operativo);
        $this->subirArchivo($creditoId, 'desembolso_egreso', 'operativo', 'egreso_v2.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'operativo'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'desembolso_aprobacion');

        Passport::actingAs($this->gerente);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'gerente'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'ejecucion_transferencia');

        // 10. Tesorería sube comprobante y completa el proceso BPMN
        Passport::actingAs($this->tesoreria);
        $this->subirArchivo($creditoId, 'comprobante_transferencia', 'tesoreria', 'transfer.pdf')
            ->assertStatus(200);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar'
        ], ['X-Active-Role' => 'tesoreria'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'completado');
    }
}
