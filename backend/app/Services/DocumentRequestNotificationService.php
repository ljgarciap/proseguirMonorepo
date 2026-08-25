<?php

namespace App\Services;

use App\Mail\CargaCompletaCoordinadorMail;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SCRUM-252: notifica al Coordinador Comercial (usuario_registra_id de la
 * SolicitudCredito, decisión de Luis 2026-08-25 — se reutiliza el campo
 * existente en vez de agregar un modelo de asignación nuevo) cuando el
 * cliente termina de cargar TODOS los documentos requeridos de Etapa 1.
 *
 * Alcance acotado a Etapa 1 (documentRequest() — etapa null/'inicial') a
 * pedido explícito de Luis: NO aplica a garantías ni pre-comité, que
 * tienen su propio flujo de habilitación en
 * ClientUploadController::habilitarFormalizacionGarantiasSiAplica() sin
 * notificación por correo. Tampoco se introduce un estado BPMN nuevo
 * ("Pendiente de validación documental" de la spec queda como texto
 * informativo del correo, no como columna) — mínimo viable acordado.
 *
 * "Completo" = ningún ítem requerido sigue en 'pendiente' de carga; no
 * hace falta que estén 'aprobado' (mismo criterio que
 * habilitarFormalizacionGarantiasSiAplica() usa para garantías). Se llama
 * desde los 2 orígenes que la spec nombra (RF-02): ClientUploadController
 * ("Mis Cargas") y CreditoOrdinarioController ("Mis Créditos").
 *
 * Idempotente vía document_requests.notificado_completado_at: sin eso, una
 * validación/consulta posterior que vuelva a pasar por acá reenviaría el
 * correo (alt. 4.2 de la spec).
 */
class DocumentRequestNotificationService
{
    public function notificarCargaCompletaSiAplica(DocumentRequest $request, string $origen): void
    {
        if (!in_array($request->etapa, [null, 'inicial'], true)) {
            return;
        }

        if ($request->notificado_completado_at) {
            return;
        }

        if ($request->items()->count() === 0) {
            return;
        }

        $itemsSinSubir = $request->items()->where('estado', 'pendiente')->count();
        if ($itemsSinSubir > 0) {
            return;
        }

        $solicitud = $request->solicitudCredito()->with(['cliente', 'tipoCredito', 'usuarioRegistra'])->first();
        if (!$solicitud || !$solicitud->usuarioRegistra || !$solicitud->usuarioRegistra->email) {
            Log::warning("SCRUM-252: DocumentRequest {$request->id} completado sin Coordinador Comercial con correo activo para notificar (RF/alt 4.3).");
            return;
        }

        $urlValidacion = ConfiguracionService::urlIngresoSistema('/creditos');
        if ($solicitud->creditoOrdinario) {
            $urlValidacion = ConfiguracionService::urlIngresoSistema('/creditos/' . $solicitud->creditoOrdinario->id);
        }

        try {
            Mail::to($solicitud->usuarioRegistra->email)->send(
                new CargaCompletaCoordinadorMail($solicitud, $origen, $urlValidacion)
            );
            $request->update(['notificado_completado_at' => now()]);
        } catch (Throwable $e) {
            Log::error('SCRUM-252: no se pudo enviar la notificación de carga completa: ' . $e->getMessage());
        }
    }
}
