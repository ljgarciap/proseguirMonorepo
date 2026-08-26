<?php

namespace App\Services;

use App\Mail\SarlaftDesfavorableCoordinadorMail;
use App\Mail\SarlaftFavorableCoordinadorMail;
use App\Models\CreditoOrdinario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SCRUM-267: notifica al Coordinador Comercial responsable de la solicitud
 * inmediatamente después de que Control Interno (rol oficial_cumplimiento)
 * finaliza la validación en Listas Restrictivas y SARLAFT — disparada desde
 * ListasRestrictivasSarlaftController::finalizar().
 *
 * RF-04 (destinatario): "responsable de la solicitud" = SolicitudCredito::
 * usuarioRegistra, mismo criterio ya establecido en SCRUM-252
 * (DocumentRequestNotificationService) — a diferencia de roles sin modelo
 * de asignación (ingeniero/oficial_cumplimiento, ver SCRUM-258), Coordinador
 * Comercial SÍ tiene uno: quien registró la SolicitudCredito. Reemplaza el
 * comportamiento previo de SCRUM-128 (avisaba a TODOS los coordinadores).
 *
 * RF-06 (estado nuevo tras concepto desfavorable): ya lo resuelve
 * ListasRestrictivasSarlaftController::finalizar() ANTES de llamar acá
 * (estado='rechazado' + resultado_origen='sarlaft', que ya alimenta la
 * tarjeta "Listas Restrictivas y SARLAFT desfavorable" de Gestión de
 * Créditos — ver GestionCreditoController) — no hizo falta un estado nuevo.
 *
 * RF-07 (no envío al cliente): a propósito, esta clase nunca correo al
 * cliente — ese envío sigue siendo manual desde Gestión de Crédito (fuera
 * de alcance de este ticket, ver punto 3.2 de la spec).
 *
 * RF-10/RF-11 (trazabilidad/idempotencia): sin tabla ni columna nueva,
 * mismo criterio "mínimo viable" que ValidacionDocumentalNotificationService
 * (SCRUM-258) — la llamada vive dentro de finalizar(), que ya exige
 * estado='sarlaft_control_interno' vía autorizarAccion(); tras finalizar
 * una vez el estado cambia, así que un reintento no vuelve a encontrar el
 * crédito en el estado de origen y no puede re-disparar la notificación.
 * La traza queda en historial_estados (usuario/rol/fecha/comentario, ya
 * registrado por el controller) + los logs de éxito/fallo de acá.
 *
 * RF-12 (reintentos): igual que el resto de las notificaciones del
 * proyecto, el envío es síncrono envuelto en try/catch — sin cola/reintento
 * automático (no hay infraestructura de colas de correo en este proyecto).
 * Un fallo queda en Log::error para seguimiento manual, no bloquea la
 * transición ya persistida.
 */
class SarlaftValidacionNotificationService
{
    public function notificar(CreditoOrdinario $credito, string $concepto): void
    {
        $credito->loadMissing('solicitudCredito.cliente.documentType', 'solicitudCredito.usuarioRegistra');
        $solicitud = $credito->solicitudCredito;

        if (!$solicitud || !$solicitud->usuarioRegistra || !$solicitud->usuarioRegistra->email) {
            Log::warning("SCRUM-267: crédito {$credito->id} finalizó validación SARLAFT (concepto {$concepto}) sin Coordinador Comercial responsable con correo activo para notificar (RF-04).");
            return;
        }

        $urlValidacion = ConfiguracionService::urlIngresoSistema('/creditos/' . $credito->id);

        try {
            $mail = $concepto === 'favorable'
                ? new SarlaftFavorableCoordinadorMail($credito, $urlValidacion)
                : new SarlaftDesfavorableCoordinadorMail($credito, $urlValidacion);

            Mail::to($solicitud->usuarioRegistra->email)->send($mail);
        } catch (Throwable $e) {
            Log::error("SCRUM-267: no se pudo enviar la notificación de concepto SARLAFT ({$concepto}) del crédito {$credito->id}: " . $e->getMessage());
        }
    }
}
