<?php

namespace Tests\Feature;

use App\Mail\BandejaDocumentoAprobadoFinalMail;
use App\Mail\BandejaDocumentoAprobadoIntermedioMail;
use App\Mail\BandejaDocumentoDevueltoMail;
use App\Mail\BandejaDocumentoNuevoMail;
use App\Mail\BandejaDocumentoReenviadoMail;
use App\Models\AccountingCategory;
use App\Models\AccountingPriority;
use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-311: notificaciones de la Bandeja Interna de Documentos —
 * documento nuevo, devuelto, aprobación final e intermedia.
 */
class DocumentEnvioNotificationsTest extends TestCase
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
        Storage::fake('public');
        Mail::fake();

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
            'observaciones' => 'Favor revisar antes del cierre de mes.',
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

    public function test_store_notifica_al_area_del_primer_paso(): void
    {
        $contable = $this->makeUser('contable', 'dest1');

        Passport::actingAs($this->sender);
        $response = $this->postJson('/api/document-envios', [
            'titulo' => 'Reporte Mensual',
            'ruta' => [$this->contabilidad->id, $this->gerencia->id],
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'observaciones' => 'Favor revisar.',
            'archivos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);

        $response->assertStatus(201);

        Mail::assertSent(BandejaDocumentoNuevoMail::class, function ($mail) use ($contable) {
            return $mail->hasTo($contable->email)
                && $mail->envio->titulo === 'Reporte Mensual'
                // sender es 'operativo'; no hay fila document_areas para ese
                // código en este fixture, así que cae al fallback capitalizado.
                && $mail->rolOrigen === 'Operativo';
        });
    }

    public function test_store_sin_usuarios_activos_en_el_area_no_falla_la_creacion(): void
    {
        Passport::actingAs($this->sender);
        // Ninguna área tiene usuarios seed además del sender ('operativo').
        $response = $this->postJson('/api/document-envios', [
            'titulo' => 'Reporte Sin Destinatarios',
            'ruta' => [$this->contabilidad->id],
            'categoria_id' => $this->categoria->id,
            'prioridad_id' => $this->prioridad->id,
            'archivos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);

        $response->assertStatus(201);
        Mail::assertNothingSent();
    }

    public function test_devolver_notifica_al_remitente(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $contable = $this->makeUser('contable', 'dev1');

        Passport::actingAs($contable);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'devolver',
            'observacion' => 'Factura ilegible, subir de nuevo.',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(BandejaDocumentoDevueltoMail::class, function ($mail) use ($envio) {
            return $mail->hasTo($this->sender->email)
                && $mail->envio->id === $envio->id
                && $mail->step->observacion === 'Factura ilegible, subir de nuevo.'
                && $mail->step->area->codigo === 'contable';
        });
    }

    public function test_procesar_ultimo_paso_notifica_aprobacion_final_al_remitente(): void
    {
        $envio = $this->makeEnvio();
        $envio->steps()->where('orden', 1)->first()->update(['estado' => 'procesado']);
        $envio->update(['current_step_order' => 2]);
        $step2 = $envio->steps()->where('orden', 2)->first();
        $gerente = $this->makeUser('gerente', 'fin1');

        Passport::actingAs($gerente);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step2->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(BandejaDocumentoAprobadoFinalMail::class, function ($mail) use ($envio, $gerente) {
            return $mail->hasTo($this->sender->email)
                && $mail->envio->id === $envio->id
                && $mail->step->usuario_id === $gerente->id
                && $mail->step->area->codigo === 'gerente';
        });
        Mail::assertSentTimes(BandejaDocumentoAprobadoFinalMail::class, 1);
    }

    public function test_procesar_paso_intermedio_notifica_remitente_y_siguiente_area(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $contable = $this->makeUser('contable', 'int1');
        $gerenteDestino = $this->makeUser('gerente', 'int2');

        Passport::actingAs($contable);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'procesar',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(BandejaDocumentoAprobadoIntermedioMail::class, function ($mail) {
            return $mail->hasTo($this->sender->email);
        });
        Mail::assertSent(BandejaDocumentoAprobadoIntermedioMail::class, function ($mail) use ($gerenteDestino) {
            return $mail->hasTo($gerenteDestino->email)
                && $mail->siguientePaso->area->codigo === 'gerente'
                && $mail->stepAprobado->area->codigo === 'contable';
        });
        Mail::assertSentTimes(BandejaDocumentoAprobadoIntermedioMail::class, 2);
    }

    public function test_reenviar_notifica_al_area_del_paso_con_el_motivo_de_devolucion(): void
    {
        $envio = $this->makeEnvio();
        $step1 = $envio->steps()->where('orden', 1)->first();
        $step1->update([
            'estado' => 'devuelto',
            'observacion' => 'Factura ilegible.',
            'fecha_procesamiento' => now(),
        ]);
        $envio->update(['estado_general' => 'devuelto']);
        $contableDestino = $this->makeUser('contable', 'reenv1');

        Passport::actingAs($this->sender);
        $response = $this->patchJson("/api/document-envios/{$envio->id}/steps/{$step1->id}", [
            'accion' => 'reenviar',
            'observacion' => 'Ya se corrigió.',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(BandejaDocumentoReenviadoMail::class, function ($mail) use ($envio, $contableDestino) {
            return $mail->hasTo($contableDestino->email)
                && $mail->envio->id === $envio->id
                && $mail->step->area->codigo === 'contable'
                && $mail->motivoDevolucion === 'Factura ilegible.'
                && str_contains($mail->notaReenvio, 'Ya se corrigió.');
        });
        Mail::assertSentTimes(BandejaDocumentoReenviadoMail::class, 1);
    }
}
