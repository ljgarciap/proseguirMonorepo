<?php

namespace Tests\Feature;

use App\Mail\AjustesDocumentalesClienteMail;
use App\Mail\DocumentacionValidadaClienteMail;
use App\Mail\InformeTecnicoPendienteIngenieroMail;
use App\Mail\ListasSarlaftPendienteControlInternoMail;
use App\Mail\SolicitudNegadaClienteMail;
use App\Models\CreditoOrdinario;
use App\Models\DocumentType;
use App\Models\TipoPersona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-258: notificaciones por correo tras la decisión del Coordinador
 * Comercial sobre la validación documental de Etapa 1.
 */
class ValidacionDocumentalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $docCC;
    private $cliente;
    private $coordinador;
    private $cumplimiento;
    private $cumplimiento2;
    private $ingeniero;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');

        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);

        $this->cliente = User::create([
            'name' => 'Cliente 258', 'email' => 'cliente258@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258001', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['cliente'],
        ]);
        $this->coordinador = User::create([
            'name' => 'Coordinador 258', 'email' => 'coordinador258@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258002', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['coordinador_comercial'],
        ]);
        $this->cumplimiento = User::create([
            'name' => 'Cumplimiento 258', 'email' => 'cumplimiento258@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258003', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['oficial_cumplimiento'],
        ]);
        $this->cumplimiento2 = User::create([
            'name' => 'Cumplimiento 258 B', 'email' => 'cumplimiento258b@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258004', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['oficial_cumplimiento'],
        ]);
        $this->ingeniero = User::create([
            'name' => 'Ingeniero 258', 'email' => 'ingeniero258@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258005', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['ingeniero'],
        ]);
        $this->admin = User::create([
            'name' => 'Admin 258', 'email' => 'admin258@test.com', 'password' => bcrypt('password'),
            'numero_documento' => '258006', 'tipo_documento_id' => $this->docCC->id, 'roles' => ['superadmin'],
        ]);
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function subirArchivo(int $creditoId, string $campo, string $rol): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'subir_archivo', 'campo_documento' => $campo, 'archivo' => $this->pdf($campo . '.pdf'),
        ], ['X-Active-Role' => $rol]);
    }

    private function completarEtapa1Legacy(int $creditoId): void
    {
        foreach (['formulario_solicitud', 'documentos_identidad', 'estados_financieros', 'certificados_laborales'] as $campo) {
            $this->subirArchivo($creditoId, $campo, 'coordinador_comercial')->assertStatus(200);
        }
    }

    // RF-04/RF-05/RF-07: Ordinario aprobado -> cliente + TODOS los
    // oficial_cumplimiento activos (decisión de Luis 2026-08-26: no solo el
    // primero).
    public function test_aprobar_ordinario_notifica_cliente_y_todos_los_control_interno(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 10000000, 'plazo_meses' => 12, 'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->coordinador);
        $this->completarEtapa1Legacy($creditoId);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar', 'comentario' => 'Todo en regla.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'sarlaft_control_interno');

        Mail::assertSent(DocumentacionValidadaClienteMail::class, function ($mail) use ($creditoId) {
            return $mail->hasTo('cliente258@test.com') && $mail->credito->id === $creditoId && $mail->comentario === 'Todo en regla.';
        });
        Mail::assertSent(ListasSarlaftPendienteControlInternoMail::class, function ($mail) {
            return $mail->hasTo('cumplimiento258@test.com');
        });
        Mail::assertSent(ListasSarlaftPendienteControlInternoMail::class, function ($mail) {
            return $mail->hasTo('cumplimiento258b@test.com');
        });
        Mail::assertNotSent(InformeTecnicoPendienteIngenieroMail::class);
    }

    // RF-04/RF-05/RF-06: Constructor aprobado -> cliente + Ingeniero(s).
    public function test_aprobar_constructor_notifica_cliente_e_ingeniero(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = CreditoOrdinario::iniciar(
            clienteId: $this->cliente->id, monto: 10000000, plazoMeses: 12,
            usuario: $this->admin->name, rol: 'superadmin', comentario: 'Solicitud Constructor registrada.',
            estadoInicial: 'validacion_documental_constructor',
        )->id;

        Passport::actingAs($this->coordinador);
        $this->completarEtapa1Legacy($creditoId);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar', 'comentario' => 'Expediente inicial correcto.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'informe_tecnico_ingeniero');

        Mail::assertSent(DocumentacionValidadaClienteMail::class, fn ($mail) => $mail->hasTo('cliente258@test.com'));
        Mail::assertSent(InformeTecnicoPendienteIngenieroMail::class, fn ($mail) => $mail->hasTo('ingeniero258@test.com'));
        Mail::assertNotSent(ListasSarlaftPendienteControlInternoMail::class);
    }

    // RF-08: Solicitar Completar Soportes -> cliente, con el comentario del
    // Coordinador incluido.
    public function test_completar_soportes_notifica_cliente_con_comentario(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 10000000, 'plazo_meses' => 12, 'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'completar', 'comentario' => 'Falta la cédula legible.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'completar_solicitud');

        Mail::assertSent(AjustesDocumentalesClienteMail::class, function ($mail) {
            return $mail->hasTo('cliente258@test.com') && $mail->comentario === 'Falta la cédula legible.';
        });
    }

    // RF-09: Rechazar Solicitud en Etapa 1 -> cliente, wording "negada"
    // (SCRUM-257).
    public function test_rechazar_etapa1_notifica_cliente_negacion(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 10000000, 'plazo_meses' => 12, 'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->coordinador);
        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'rechazar', 'comentario' => 'No cumple con la política de riesgo.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'rechazado');

        Mail::assertSent(SolicitudNegadaClienteMail::class, function ($mail) {
            return $mail->hasTo('cliente258@test.com') && $mail->comentario === 'No cumple con la política de riesgo.';
        });
    }

    // Alcance de 258: un rechazo en OTRA etapa (Comité, atajo superadmin) no
    // dispara este correo — sección 5.3 de la spec es específica a Etapa 1.
    public function test_rechazar_fuera_de_etapa1_no_dispara_notificacion_258(): void
    {
        Passport::actingAs($this->admin);
        $creditoId = CreditoOrdinario::iniciar(
            clienteId: $this->cliente->id, monto: 10000000, plazoMeses: 12,
            usuario: $this->admin->name, rol: 'superadmin', comentario: 'Directo a comité (fixture de test).',
            estadoInicial: 'comite_evaluacion',
        )->id;

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'rechazar', 'comentario' => 'Rechazado por el comité.',
        ], ['X-Active-Role' => 'superadmin'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'rechazado');

        Mail::assertNotSent(SolicitudNegadaClienteMail::class);
    }

    // Sin usuarios activos con el rol interno requerido: no debe romper la
    // transición ya persistida (RF-14, independencia).
    public function test_aprobar_ordinario_sin_control_interno_activo_no_rompe_transicion(): void
    {
        User::where('id', $this->cumplimiento->id)->orWhere('id', $this->cumplimiento2->id)->get()->each->delete();

        Passport::actingAs($this->admin);
        $creditoId = $this->postJson('/api/creditos', [
            'monto' => 10000000, 'plazo_meses' => 12, 'cliente_id' => $this->cliente->id,
        ], ['X-Active-Role' => 'superadmin'])->json('id');

        Passport::actingAs($this->coordinador);
        $this->completarEtapa1Legacy($creditoId);

        $this->postJson("/api/creditos/{$creditoId}/transition", [
            'accion' => 'aprobar', 'comentario' => 'Todo en regla.',
        ], ['X-Active-Role' => 'coordinador_comercial'])
            ->assertStatus(200)
            ->assertJsonPath('estado', 'sarlaft_control_interno');

        Mail::assertSent(DocumentacionValidadaClienteMail::class);
        Mail::assertNotSent(ListasSarlaftPendienteControlInternoMail::class);
    }
}
