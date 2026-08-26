<?php

namespace App\Services;

use App\Mail\AjustesDocumentalesClienteMail;
use App\Mail\DocumentacionValidadaClienteMail;
use App\Mail\InformeTecnicoPendienteIngenieroMail;
use App\Mail\ListasSarlaftPendienteControlInternoMail;
use App\Mail\SolicitudNegadaClienteMail;
use App\Models\CreditoOrdinario;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SCRUM-258: notificaciones por correo tras la decisión del Coordinador
 * Comercial sobre la validación documental de Etapa 1 (Aprobar Documentos /
 * Solicitar Completar Soportes / Rechazar Solicitud) — disparadas desde
 * CreditoOrdinarioController::transition().
 *
 * "Control Interno" de la spec = rol 'oficial_cumplimiento' (mismo flujo de
 * Listas Restrictivas y SARLAFT, ver ListasRestrictivasSarlaftController).
 * Ni 'ingeniero' ni 'oficial_cumplimiento' tienen un modelo de asignación
 * por crédito (a diferencia de Coordinador Comercial, que ya usa
 * usuario_registra_id desde SCRUM-252) — decisión de Luis 2026-08-26: se
 * notifica a TODOS los usuarios activos con el rol correspondiente, no solo
 * al primero.
 *
 * RF-14 (independencia): la notificación al cliente y la(s) notificación(es)
 * interna(s) son eventos independientes, cada uno en su propio try/catch —
 * que falle uno no bloquea a los demás ni la transición ya persistida.
 *
 * RF-15 (idempotencia): no hay columna de control nueva. Cada llamada a
 * notificar() vive DENTRO de la rama de CreditoOrdinarioController::
 * transition() que efectivamente produjo el cambio de estado (p.ej.
 * revision_documental -> sarlaft_control_interno), así que solo se dispara
 * una vez por decisión real — un reintento ya no encuentra el crédito en el
 * mismo estado de origen. Mismo criterio que ya usan el resto de los
 * correos de este controlador (SarlaftDesfavorableClienteMail,
 * DesembolsoRechazadoOperativoMail), ninguno con columna dedicada.
 */
class ValidacionDocumentalNotificationService
{
    public function notificar(string $tipo, CreditoOrdinario $credito, string $comentario): void
    {
        match ($tipo) {
            'aprobar_constructor' => $this->notificarAprobacion($credito, $comentario, 'constructor'),
            'aprobar_ordinario'   => $this->notificarAprobacion($credito, $comentario, 'ordinario'),
            'completar'           => $this->notificarCompletarSoportes($credito, $comentario),
            'rechazar'            => $this->notificarNegacion($credito, $comentario),
            default               => null,
        };
    }

    /**
     * RF-04/RF-05: notifica al cliente (siempre) y bifurca la notificación
     * interna según el flujo ya resuelto por el llamador (el estado de
     * origen de la transición ya distingue Constructor de Ordinario — no
     * hace falta re-derivarlo de tipoCredito acá).
     */
    private function notificarAprobacion(CreditoOrdinario $credito, string $comentario, string $flujo): void
    {
        $cliente = $credito->cliente;
        if ($cliente && $cliente->email) {
            try {
                Mail::to($cliente->email)->send(new DocumentacionValidadaClienteMail($credito, $comentario));
            } catch (Throwable $e) {
                Log::error("SCRUM-258: no se pudo enviar 'Documentación validada' al cliente del crédito {$credito->id}: " . $e->getMessage());
            }
        } else {
            Log::warning("SCRUM-258: crédito {$credito->id} aprobado sin cliente con correo activo para notificar (RF-04).");
        }

        $rolInterno = $flujo === 'constructor' ? 'ingeniero' : 'oficial_cumplimiento';
        $usuarios = User::whereJsonContains('roles', $rolInterno)->whereNotNull('email')->get();

        if ($usuarios->isEmpty()) {
            Log::warning("SCRUM-258: crédito {$credito->id} aprobado sin ningún usuario activo con rol '{$rolInterno}' para notificar (RF-06/RF-07).");
            return;
        }

        foreach ($usuarios as $usuario) {
            try {
                $mail = $flujo === 'constructor'
                    ? new InformeTecnicoPendienteIngenieroMail($credito)
                    : new ListasSarlaftPendienteControlInternoMail($credito);
                Mail::to($usuario->email)->send($mail);
            } catch (Throwable $e) {
                Log::error("SCRUM-258: no se pudo enviar notificación interna ({$rolInterno}) del crédito {$credito->id} a {$usuario->email}: " . $e->getMessage());
            }
        }
    }

    private function notificarCompletarSoportes(CreditoOrdinario $credito, string $comentario): void
    {
        $cliente = $credito->cliente;
        if (!$cliente || !$cliente->email) {
            Log::warning("SCRUM-258: crédito {$credito->id} enviado a completar soportes sin cliente con correo activo para notificar (RF-08).");
            return;
        }

        try {
            Mail::to($cliente->email)->send(new AjustesDocumentalesClienteMail($credito, $comentario));
        } catch (Throwable $e) {
            Log::error("SCRUM-258: no se pudo enviar 'Ajustes requeridos' al cliente del crédito {$credito->id}: " . $e->getMessage());
        }
    }

    private function notificarNegacion(CreditoOrdinario $credito, string $comentario): void
    {
        $cliente = $credito->cliente;
        if (!$cliente || !$cliente->email) {
            Log::warning("SCRUM-258: crédito {$credito->id} negado sin cliente con correo activo para notificar (RF-09).");
            return;
        }

        try {
            Mail::to($cliente->email)->send(new SolicitudNegadaClienteMail($credito, $comentario));
        } catch (Throwable $e) {
            Log::error("SCRUM-258: no se pudo enviar 'Solicitud negada' al cliente del crédito {$credito->id}: " . $e->getMessage());
        }
    }
}
