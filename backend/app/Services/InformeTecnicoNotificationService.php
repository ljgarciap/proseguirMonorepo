<?php

namespace App\Services;

use App\Mail\InformeTecnicoFinalizadoControlInternoMail;
use App\Mail\InformeTecnicoListoCoordinadorMail;
use App\Models\CreditoOrdinario;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SCRUM-262: notifica al siguiente responsable del Informe Técnico
 * (Ingeniero -> Coordinador Comercial -> Control Interno) cuando se
 * registra cada intervención — disparado desde
 * InformeTecnicoController::registrar().
 *
 * RF-15 (idempotencia): sin columna de control nueva — cada llamada vive
 * dentro de la rama de registrar() que corresponde al estado de origen
 * exacto (informe_tecnico_ingeniero / informe_tecnico_coordinador), así que
 * un reintento ya no encuentra el crédito en ese estado (mismo criterio que
 * ValidacionDocumentalNotificationService, SCRUM-258).
 */
class InformeTecnicoNotificationService
{
    /**
     * RF-05/RF-06: Coordinador Comercial asignado a la solicitud
     * (SolicitudCredito::usuarioRegistra — mismo campo reutilizado desde
     * SCRUM-252, sin modelo de asignación nuevo).
     */
    public function notificarRegistroIngeniero(CreditoOrdinario $credito): void
    {
        $coordinador = $credito->solicitudCredito?->usuarioRegistra;

        if (!$coordinador || !$coordinador->email) {
            Log::warning("SCRUM-262: crédito {$credito->id} — Ingeniero registró el informe sin Coordinador Comercial con correo activo para notificar (RF-05).");
            return;
        }

        try {
            Mail::to($coordinador->email)->send(new InformeTecnicoListoCoordinadorMail($credito));
        } catch (Throwable $e) {
            Log::error("SCRUM-262: no se pudo enviar 'Informe técnico listo' al Coordinador Comercial del crédito {$credito->id}: " . $e->getMessage());
        }
    }

    /**
     * RF-10/RF-11: TODOS los usuarios activos con rol 'oficial_cumplimiento'
     * ('Control Interno' de la spec) — mismo criterio "todos los activos"
     * decidido en SCRUM-258, sin modelo de asignación por crédito.
     */
    public function notificarFinalizacionCoordinador(CreditoOrdinario $credito): void
    {
        $usuarios = User::whereJsonContains('roles', 'oficial_cumplimiento')->whereNotNull('email')->get();

        if ($usuarios->isEmpty()) {
            Log::warning("SCRUM-262: crédito {$credito->id} — informe técnico finalizado sin ningún usuario activo con rol 'oficial_cumplimiento' para notificar (RF-10).");
            return;
        }

        foreach ($usuarios as $usuario) {
            try {
                Mail::to($usuario->email)->send(new InformeTecnicoFinalizadoControlInternoMail($credito));
            } catch (Throwable $e) {
                Log::error("SCRUM-262: no se pudo enviar 'Informe técnico finalizado' a {$usuario->email} del crédito {$credito->id}: " . $e->getMessage());
            }
        }
    }
}
