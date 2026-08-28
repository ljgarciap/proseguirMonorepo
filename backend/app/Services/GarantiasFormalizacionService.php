<?php

namespace App\Services;

use App\Mail\FormalizacionGarantiasPendienteOperativoMail;
use App\Models\CreditoOrdinario;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SCRUM-193/205 (2026-08-17): cuando el cliente terminó de subir TODOS los
 * ítems del preset de garantías (no hace falta que estén aprobados
 * todavía, eso lo decide el rol Operativo en Formalización de Garantías —
 * SCRUM-237, antes Coordinador Comercial) el crédito pasa de
 * 'aprobada_garantias' a 'pendiente_formalizacion_garantias' y se notifica
 * al rol Operativo.
 *
 * SCRUM-293: originalmente vivía como métodos privados de
 * ClientUploadController (única pantalla que la disparaba: "Solicitud de
 * Documentos"/"Mis Cargas"). Se extrae a servicio porque la carga directa
 * desde Crédito Ordinario, Etapa 4/5 (CreditoOrdinarioController::
 * transition(), habilitada para el rol cliente por SCRUM-292) nunca la
 * invocaba — un crédito gestionado con preset de garantías y cargado
 * ÍNTEGRAMENTE desde esa pantalla se quedaba trabado en 'aprobada_garantias'
 * para siempre: nunca avanzaba a 'pendiente_formalizacion_garantias', nunca
 * aparecía en la bandeja de Operativo, y nunca se enviaba el correo.
 */
class GarantiasFormalizacionService
{
    public function habilitarSiAplica(DocumentRequest $request): void
    {
        if ($request->etapa !== 'garantias' || !$request->solicitud_credito_id) {
            return;
        }

        $credito = CreditoOrdinario::with('solicitudCredito.cliente')
            ->where('solicitud_credito_id', $request->solicitud_credito_id)
            ->first();
        if (!$credito || $credito->estado !== 'aprobada_garantias') {
            return;
        }

        $itemsSinSubir = $request->items()->where('estado', 'pendiente')->count();
        if ($itemsSinSubir > 0) {
            return;
        }

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => 'Sistema',
            'rol' => 'sistema',
            'estado_anterior' => $credito->estado,
            'estado_nuevo' => 'pendiente_formalizacion_garantias',
            'comentario' => 'El cliente terminó de diligenciar las garantías solicitadas. Bandeja de Formalización de Garantías habilitada para el rol Operativo.',
        ];

        $credito->estado = 'pendiente_formalizacion_garantias';
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->historial_estados = $historial;
        $credito->save();

        $this->notificarOperativo($credito);
    }

    /**
     * SCRUM-280: 'operativo' no tiene modelo de asignación por crédito (a
     * diferencia de Coordinador Comercial, ver
     * ValidacionDocumentalNotificationService) — se notifica a todos los
     * usuarios activos con ese rol, cada uno en su propio try/catch para que
     * un fallo puntual no bloquee al resto (mismo criterio que
     * ValidacionDocumentalNotificationService::notificarAprobacion()).
     */
    private function notificarOperativo(CreditoOrdinario $credito): void
    {
        $cliente = $credito->solicitudCredito?->cliente;
        $nombreCliente = $cliente?->nombre_razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->primer_apellido ?? ''))
            ?: 'cliente';

        $urlAcceso = rtrim(env('FRONTEND_URL', config('app.url')), '/')
            . '/login?returnTo=' . urlencode('/gestion-creditos/' . $credito->id . '/formalizacion-garantias');

        $usuarios = User::whereJsonContains('roles', 'operativo')->whereNotNull('email')->get();
        if ($usuarios->isEmpty()) {
            Log::warning("SCRUM-280: crédito {$credito->id} con cargue completo de Formalización de Garantías sin ningún usuario activo con rol 'operativo' para notificar.");
            return;
        }

        foreach ($usuarios as $usuario) {
            try {
                Mail::to($usuario->email)->send(new FormalizacionGarantiasPendienteOperativoMail($credito, $nombreCliente, $urlAcceso));
            } catch (Throwable $e) {
                Log::error("SCRUM-280: no se pudo enviar notificación de cargue completo del crédito {$credito->id} a {$usuario->email}: " . $e->getMessage());
            }
        }
    }
}
