<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\BandejaDocumentoAprobadoFinalMail;
use App\Mail\BandejaDocumentoAprobadoIntermedioMail;
use App\Mail\BandejaDocumentoDevueltoMail;
use App\Mail\BandejaDocumentoNuevoMail;
use App\Mail\BandejaDocumentoReenviadoMail;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioStep;
use App\Models\DocumentEnvioFile;
use App\Models\DocumentArea;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * SCRUM-311: notificaciones de la Bandeja Interna de Documentos —
 * documento nuevo (al área del primer paso), devuelto (al remitente),
 * aprobación final (al remitente), aprobación intermedia (al remitente y
 * al área del siguiente paso) y reenviado tras devolución (al área del
 * paso que vuelve a quedar pendiente — rebote 2026-09-01, Juan: la
 * acción "reenviar" no estaba entre las 4 notificaciones listadas en el
 * ticket original y no disparaba nada).
 */
class DocumentEnvioController extends Controller
{
    /**
     * Listar envíos según el área del usuario autenticado y el paso actual de cada ruta.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles ?? [];

        $query = DocumentEnvio::with(['sender', 'category', 'priority', 'files', 'steps.area', 'steps.usuario']);

        $isAdmin = in_array('superadmin', $roles);

        if (!$isAdmin) {
            $userAreaIds = DocumentArea::whereIn('codigo', $roles)->pluck('id');

            $query->where(function ($q) use ($user, $userAreaIds) {
                $q->where('sender_id', $user->id)
                    ->orWhereHas('steps', function ($stepQuery) use ($userAreaIds) {
                        $stepQuery->whereIn('area_id', $userAreaIds)
                            ->where(function ($s) {
                                // El área ya actuó en este paso (historial), o es el paso pendiente actual (su turno).
                                $s->where('estado', '!=', 'pendiente')
                                    ->orWhereColumn('orden', 'document_envios.current_step_order');
                            });
                    });
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Crear un nuevo envío con ruta de aprobación ordenada y uno o varios archivos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string',
            'ruta' => 'required|array|min:1',
            'ruta.*' => ['required', 'distinct', 'exists:document_areas,id'],
            'categoria_id' => 'required|exists:accounting_categories,id',
            'prioridad_id' => 'required|exists:accounting_priorities,id',
            'observaciones' => 'nullable|string',
            'archivos' => 'required|array|min:1',
            'archivos.*' => 'file|max:20480',
        ], [
            'titulo.required' => 'Debe ingresar el título del documento.',
            'ruta.required' => 'Debe agregar al menos un área a la ruta de aprobación.',
            'ruta.min' => 'Debe agregar al menos un área a la ruta de aprobación.',
            'ruta.*.distinct' => 'El área ya se encuentra incluida en la ruta.',
            'ruta.*.exists' => 'Una de las áreas seleccionadas no es válida.',
            'archivos.required' => 'Debe seleccionar al menos un archivo para enviar el documento.',
            'archivos.min' => 'Debe seleccionar al menos un archivo para enviar el documento.',
        ]);

        $envio = DB::transaction(function () use ($request) {
            $envio = DocumentEnvio::create([
                'sender_id' => Auth::id(),
                'titulo' => $request->titulo,
                'categoria_id' => $request->categoria_id,
                'prioridad_id' => $request->prioridad_id,
                'observaciones' => $request->observaciones,
                'estado_general' => 'pendiente',
                'current_step_order' => 1,
            ]);

            foreach (array_values($request->ruta) as $index => $areaId) {
                DocumentEnvioStep::create([
                    'envio_id' => $envio->id,
                    'orden' => $index + 1,
                    'area_id' => $areaId,
                    'estado' => 'pendiente',
                ]);
            }

            foreach ($request->file('archivos') as $file) {
                $path = $file->store('internal_docs', 'public');
                DocumentEnvioFile::create([
                    'envio_id' => $envio->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            return $envio;
        });

        $envio->load(['steps.area', 'files', 'sender', 'category', 'priority']);

        // SCRUM-311 (§6.1/§7.1): notifica al área del primer paso de la ruta.
        $primerPaso = $envio->steps->firstWhere('orden', 1);
        if ($primerPaso) {
            $rolOrigen = DocumentArea::etiqueta($envio->sender->roles[0] ?? null);
            $urlIngreso = ConfiguracionService::urlIngresoSistema('/internal-docs');

            $this->notificarArea(
                $primerPaso->area->codigo,
                new BandejaDocumentoNuevoMail($envio, $rolOrigen, $urlIngreso),
                $envio,
                'bandeja_documento_nuevo'
            );
        }

        return response()->json($envio, 201);
    }

    /**
     * Procesar, devolver o reenviar el paso actual de la ruta de aprobación.
     *
     * "Procesar" avanza al siguiente paso, o finaliza la ruta si era el último.
     * "Devolver" es un rechazo: el envío no continúa por la ruta y queda visible
     * para el remitente a modo de notificación, junto con la observación del rechazo.
     * "Reenviar" solo aplica sobre un envío devuelto y solo puede ejecutarlo el
     * remitente (o superadmin): si considera que el rechazo fue un error, retoma
     * la ruta exactamente en el paso que rechazó, con una nueva observación.
     */
    public function processStep(Request $request, $id, $stepId)
    {
        $request->validate([
            'accion' => 'required|in:procesar,devolver,reenviar',
            'observacion' => 'required_if:accion,devolver,reenviar|nullable|string',
        ], [
            'observacion.required_if' => 'Debe registrar una observación para esta acción.',
        ]);

        $envio = DocumentEnvio::findOrFail($id);
        $step = DocumentEnvioStep::with('area')->findOrFail($stepId);

        if ($step->envio_id !== $envio->id) {
            return response()->json(['message' => 'El paso no pertenece a este envío.'], 422);
        }

        $user = Auth::user();
        $roles = $user->roles ?? [];
        $isAdmin = in_array('superadmin', $roles);

        if ($request->accion === 'reenviar') {
            $isSender = $envio->sender_id === Auth::id();

            if (!$isAdmin && !$isSender) {
                return response()->json(['message' => 'No tiene permisos para reenviar este documento.'], 403);
            }

            if ($step->orden !== $envio->current_step_order || $step->estado !== 'devuelto' || $envio->estado_general !== 'devuelto') {
                return response()->json(['message' => 'Este documento no está disponible para reenviar actualmente.'], 422);
            }
        } else {
            if (!$isAdmin && !in_array($step->area->codigo, $roles)) {
                return response()->json(['message' => 'No tiene permisos para procesar este paso.'], 403);
            }

            if ($step->orden !== $envio->current_step_order || $step->estado !== 'pendiente') {
                return response()->json(['message' => 'Este paso no está disponible para procesar actualmente.'], 422);
            }
        }

        $motivoDevolucion = null;
        $fechaDevolucion = null;
        $notaReenvio = null;

        DB::transaction(function () use ($request, $envio, $step, &$motivoDevolucion, &$fechaDevolucion, &$notaReenvio) {
            if ($request->accion === 'devolver') {
                $step->update([
                    'estado' => 'devuelto',
                    'usuario_id' => Auth::id(),
                    'fecha_inicio' => $step->fecha_inicio ?? now(),
                    'fecha_procesamiento' => now(),
                    'observacion' => $request->observacion,
                ]);
                $envio->update(['estado_general' => 'devuelto']);
                return;
            }

            if ($request->accion === 'reenviar') {
                $motivoDevolucion = $step->observacion;
                $fechaDevolucion = $step->fecha_procesamiento;
                $notaReenvio = '[Reenviado ' . now()->format('d/m/Y H:i') . '] ' . $request->observacion;

                $step->update([
                    'estado' => 'pendiente',
                    'usuario_id' => null,
                    'fecha_inicio' => null,
                    'fecha_procesamiento' => null,
                    'observacion' => null,
                ]);

                $envio->update([
                    'observaciones' => trim(($envio->observaciones ? $envio->observaciones . "\n\n" : '') . $notaReenvio),
                    'estado_general' => $step->orden === 1 ? 'pendiente' : 'en_proceso',
                ]);
                return;
            }

            $step->update([
                'estado' => 'procesado',
                'usuario_id' => Auth::id(),
                'fecha_inicio' => $step->fecha_inicio ?? now(),
                'fecha_procesamiento' => now(),
                'observacion' => $request->observacion,
            ]);

            $siguientePaso = $envio->steps()->where('orden', $envio->current_step_order + 1)->first();

            if ($siguientePaso) {
                $envio->update([
                    'current_step_order' => $siguientePaso->orden,
                    'estado_general' => 'en_proceso',
                ]);
            } else {
                $envio->update(['estado_general' => 'procesado']);
            }
        });

        $envio->refresh()->load(['steps.area', 'steps.usuario', 'files', 'sender', 'category', 'priority']);

        // SCRUM-311 (§6.2/§6.4/§6.5/§7.2-7.4) + rebote 2026-09-01 (reenviar):
        // notifica según la acción.
        $rolOrigen = DocumentArea::etiqueta($envio->sender->roles[0] ?? null);
        $urlIngreso = ConfiguracionService::urlIngresoSistema('/internal-docs');
        $stepActualizado = $envio->steps->firstWhere('id', $step->id);

        if ($request->accion === 'devolver') {
            $this->notificarSender(
                new BandejaDocumentoDevueltoMail($envio, $stepActualizado, $rolOrigen, $urlIngreso),
                $envio,
                'bandeja_documento_devuelto'
            );
        } elseif ($request->accion === 'reenviar') {
            $this->notificarArea(
                $stepActualizado->area->codigo,
                new BandejaDocumentoReenviadoMail(
                    $envio, $stepActualizado, $motivoDevolucion, $fechaDevolucion, $notaReenvio, $rolOrigen, $urlIngreso
                ),
                $envio,
                'bandeja_documento_reenviado'
            );
        } else {
            $siguientePaso = $envio->steps->firstWhere('orden', $stepActualizado->orden + 1);

            if ($siguientePaso) {
                $mailable = new BandejaDocumentoAprobadoIntermedioMail(
                    $envio, $stepActualizado, $siguientePaso, $rolOrigen, $urlIngreso
                );
                $this->notificarSender($mailable, $envio, 'bandeja_documento_aprobado_intermedio');
                $this->notificarArea($siguientePaso->area->codigo, $mailable, $envio, 'bandeja_documento_aprobado_intermedio');
            } else {
                $this->notificarSender(
                    new BandejaDocumentoAprobadoFinalMail($envio, $stepActualizado, $rolOrigen, $urlIngreso),
                    $envio,
                    'bandeja_documento_aprobado_final'
                );
            }
        }

        return response()->json($envio);
    }

    /**
     * Descargar un archivo adjunto de un envío, sujeto a los mismos permisos
     * de visibilidad que la bandeja (remitente, área con paso en el envío, o superadmin).
     */
    public function downloadFile($id, $fileId)
    {
        $envio = DocumentEnvio::with('steps.area')->findOrFail($id);
        $file = DocumentEnvioFile::findOrFail($fileId);

        if ($file->envio_id !== $envio->id) {
            return response()->json(['message' => 'El archivo no pertenece a este envío.'], 404);
        }

        if (!$this->canAccessEnvio(Auth::user(), $envio)) {
            return response()->json(['message' => 'No tiene permisos para descargar este archivo.'], 403);
        }

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['message' => 'El archivo no existe.'], 404);
        }

        return Storage::disk('public')->download($file->path, $file->original_name);
    }

    /**
     * Eliminar un envío. El remitente solo puede hacerlo mientras siga pendiente
     * (nadie procesó el primer paso todavía); superadmin puede en cualquier estado.
     */
    public function destroy($id)
    {
        $envio = DocumentEnvio::with('files')->findOrFail($id);

        $user = Auth::user();
        $roles = $user->roles ?? [];
        $isAdmin = in_array('superadmin', $roles);
        $isSender = $envio->sender_id === Auth::id();

        if (!$isSender && !$isAdmin) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($isSender && !$isAdmin && $envio->estado_general !== 'pendiente') {
            return response()->json(['message' => 'No puede eliminar un envío que ya está en proceso.'], 403);
        }

        foreach ($envio->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $envio->delete();

        return response()->json(['message' => 'Envío eliminado con éxito.']);
    }

    /**
     * Misma regla de visibilidad que index(): remitente, área con paso en el envío
     * (ya actuado o su turno actual), o superadmin.
     */
    private function canAccessEnvio($user, DocumentEnvio $envio): bool
    {
        $roles = $user->roles ?? [];

        if (in_array('superadmin', $roles) || $envio->sender_id === $user->id) {
            return true;
        }

        foreach ($envio->steps as $step) {
            $esSuArea = in_array($step->area->codigo, $roles);
            $yaActuoOEsSuTurno = $step->estado !== 'pendiente' || $step->orden === $envio->current_step_order;

            if ($esSuArea && $yaActuoOEsSuTurno) {
                return true;
            }
        }

        return false;
    }

    /**
     * SCRUM-311: envía $mailable a todos los usuarios activos con rol
     * $codigoArea y audita el resultado — mismo patrón que
     * GestionCreditoController::notificarPorRol(), generalizado a
     * DocumentEnvio como $entidad.
     */
    private function notificarArea(string $codigoArea, $mailable, DocumentEnvio $envio, string $tipoNotificacion): void
    {
        $destinatarios = User::whereJsonContains('roles', $codigoArea)->pluck('email')->filter()->all();

        if (empty($destinatarios)) {
            app(ActivityLogService::class)->registrar(
                'notificacion_rol_sin_destinatarios',
                "No hay usuarios activos con rol {$codigoArea} para la notificación {$tipoNotificacion} del envío \"{$envio->titulo}\".",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $codigoArea]
            );

            return;
        }

        try {
            Mail::to($destinatarios)->send($mailable);

            app(ActivityLogService::class)->registrar(
                'notificacion_rol_enviada',
                "Notificación {$tipoNotificacion} enviada a rol {$codigoArea} para el envío \"{$envio->titulo}\".",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $codigoArea, 'destinatarios' => $destinatarios]
            );
        } catch (Throwable $e) {
            app(ActivityLogService::class)->registrar(
                'notificacion_rol_fallida',
                "Falló el envío de la notificación {$tipoNotificacion} a rol {$codigoArea} para el envío \"{$envio->titulo}\".",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $codigoArea, 'destinatarios' => $destinatarios, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * SCRUM-311: envía $mailable al usuario origen (remitente) del envío y
     * audita el resultado — best-effort, un fallo de envío no revierte la
     * acción ya guardada (mismo criterio que notificarArea()).
     */
    private function notificarSender($mailable, DocumentEnvio $envio, string $tipoNotificacion): void
    {
        $destino = $envio->sender?->email;

        if (!$destino) {
            app(ActivityLogService::class)->registrar(
                'notificacion_rol_sin_destinatarios',
                "El usuario origen del envío \"{$envio->titulo}\" no tiene correo registrado para la notificación {$tipoNotificacion}.",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion]
            );

            return;
        }

        try {
            Mail::to($destino)->send($mailable);

            app(ActivityLogService::class)->registrar(
                'notificacion_rol_enviada',
                "Notificación {$tipoNotificacion} enviada al usuario origen para el envío \"{$envio->titulo}\".",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion, 'destinatarios' => [$destino]]
            );
        } catch (Throwable $e) {
            app(ActivityLogService::class)->registrar(
                'notificacion_rol_fallida',
                "Falló el envío de la notificación {$tipoNotificacion} al usuario origen para el envío \"{$envio->titulo}\".",
                Auth::user(),
                $envio,
                ['tipo_notificacion' => $tipoNotificacion, 'destinatarios' => [$destino], 'error' => $e->getMessage()]
            );
        }
    }
}
