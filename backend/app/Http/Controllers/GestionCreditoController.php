<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Mail\GestionCreditoNotificacionMail;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Gestión de Créditos (SCRUM-178). Bandeja + pantallas de gestión de los
 * resultados que salen de SARLAFT desfavorable o de la decisión del Comité
 * de Crédito (Actas de Comité, SCRUM-169/183). "Registrar y enviar
 * notificación" es el único punto que marca Solicitud gestionada = Sí y
 * ejecuta la transición de estado correspondiente (ver
 * docs/architecture/scrum-178-gestion-creditos-diseno.md).
 */
class GestionCreditoController extends Controller
{
    use ResolvesActiveRole;

    private const RELACIONES_DETALLE = [
        'cliente',
        'solicitudCredito.cliente.tipoPersona',
        'solicitudCredito.cliente.documentType',
        'solicitudCredito.cliente.repDocumentType',
        'solicitudCredito.tipoCredito',
        'solicitudCredito.amortizacion',
        'sarlaftDiligenciadoPor',
        'actaComiteSolicitudes.actaComite',
    ];

    /**
     * Estado + resultado_origen para cada uno de los 4 resultados de la
     * bandeja (tarjetas, filtro "Estado" y validación de notificar()).
     */
    private const RESULTADOS = [
        'sarlaft_desfavorable' => ['estado' => 'rechazado', 'origen' => 'sarlaft'],
        'aprobada_garantias'   => ['estado' => 'aprobada_garantias', 'origen' => 'comite_aprobado'],
        'rechazada_comite'     => ['estado' => 'rechazado', 'origen' => 'comite_rechazado'],
        'pendiente_comite'     => ['estado' => 'pendiente_comite', 'origen' => 'comite_pendiente'],
    ];

    /**
     * Bandeja principal: filtros, columnas y ordenamiento predeterminado
     * (ticket SCRUM-178 §3.2-3.4).
     */
    public function index(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $query = $this->queryBase();

        if ($request->filled('busqueda')) {
            $texto = $request->busqueda;
            $query->where(function ($q) use ($texto) {
                $q->where('numero_solicitud', 'like', "%{$texto}%")
                    ->orWhereHas('cliente', function ($qc) use ($texto) {
                        $qc->where('name', 'like', "%{$texto}%")
                            ->orWhere('numero_documento', 'like', "%{$texto}%");
                    });
            });
        }

        if ($request->filled('tipo_credito') && $request->tipo_credito !== 'todos') {
            $query->whereHas('solicitudCredito.tipoCredito', function ($q) use ($request) {
                $q->where('codigo', $request->tipo_credito);
            });
        }

        if ($request->filled('tipo_persona') && $request->tipo_persona !== 'todos') {
            $query->whereHas('solicitudCredito.cliente.tipoPersona', function ($q) use ($request) {
                $q->where('codigo', $request->tipo_persona);
            });
        }

        if ($request->filled('estado') && $request->estado !== 'todos' && isset(self::RESULTADOS[$request->estado])) {
            $resultado = self::RESULTADOS[$request->estado];
            $query->where('estado', $resultado['estado'])->where('resultado_origen', $resultado['origen']);
        }

        if ($request->filled('gestionada') && $request->gestionada !== 'todos') {
            $query->where('solicitud_gestionada', $request->gestionada === 'si');
        }

        $creditos = $query->get()->map(function (CreditoOrdinario $credito) {
            return $this->conFechaValidacion($credito);
        });

        // Ordenamiento predeterminado (§3.4): fecha_validacion asc, luego
        // fecha_gestion asc con NULLS FIRST, luego numero_solicitud asc.
        // No es orderable directamente en SQL por ser un campo calculado
        // (viene de sarlaft_diligenciado_at o de la fecha del acta según el
        // origen), así que se ordena en colección.
        $ordenados = $creditos->sort(function (CreditoOrdinario $a, CreditoOrdinario $b) {
            $fechaA = $a->fecha_validacion;
            $fechaB = $b->fecha_validacion;
            if ($fechaA !== $fechaB) {
                return strcmp((string) $fechaA, (string) $fechaB);
            }

            $gestionA = $a->fecha_gestion;
            $gestionB = $b->fecha_gestion;
            if ($gestionA === null && $gestionB !== null) return -1;
            if ($gestionA !== null && $gestionB === null) return 1;
            if ($gestionA !== null && $gestionB !== null) {
                $cmp = $gestionA <=> $gestionB;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return strcmp($a->numero_solicitud, $b->numero_solicitud);
        })->values();

        return response()->json($ordenados);
    }

    /**
     * Conteos de las 4 tarjetas: solo Solicitud gestionada = No (§3.1).
     */
    public function tarjetas(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $conteos = [];
        foreach (self::RESULTADOS as $clave => $resultado) {
            $conteos[$clave] = CreditoOrdinario::where('estado', $resultado['estado'])
                ->where('resultado_origen', $resultado['origen'])
                ->where('solicitud_gestionada', false)
                ->count();
        }

        return response()->json($conteos);
    }

    /**
     * Detalle solo lectura, para las pantallas de gestión y para "Ver".
     */
    public function show(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $credito = $this->queryBase()->findOrFail($creditoId);

        return response()->json($this->conFechaValidacion($credito));
    }

    /**
     * "Registrar y enviar notificación" (§5.5, VAL-01..08): valida,
     * envía el correo y solo si el envío no falla ejecuta la transición,
     * marca Solicitud gestionada = Sí y registra Fecha de la gestión.
     */
    public function notificar(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with('cliente')->findOrFail($creditoId);
        $resultado = $this->resolverClaveResultado($credito);

        if (!$resultado) {
            return response()->json([
                'message' => 'Esta solicitud no tiene un resultado pendiente de gestión.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'destino' => 'required|email',
            'asunto' => 'required|string',
            'mensaje' => 'required|string',
            'preset_id' => 'nullable|exists:document_presets,id',
            'requiere_documentos' => 'nullable|boolean',
        ], [
            'destino.required' => 'Ingrese una dirección de correo electrónico válida.',
            'destino.email' => 'Ingrese una dirección de correo electrónico válida.',
            'asunto.required' => 'Ingrese el asunto del correo.',
            'mensaje.required' => 'Ingrese el mensaje de acompañamiento.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // VAL-04: preset obligatorio para Aprobada para gestión de garantías.
        if ($resultado === 'aprobada_garantias' && !$request->filled('preset_id')) {
            return response()->json(['message' => 'Seleccione la documentación requerida.'], 422);
        }

        // VAL-05: Pendiente por Comité debe responder si requiere
        // documentación; si la respuesta es Sí, el preset es obligatorio.
        if ($resultado === 'pendiente_comite') {
            if (!$request->has('requiere_documentos')) {
                return response()->json(['message' => 'Indique si el cliente debe enviar documentación.'], 422);
            }
            if ($request->boolean('requiere_documentos') && !$request->filled('preset_id')) {
                return response()->json(['message' => 'Seleccione la documentación requerida.'], 422);
            }
            // SCRUM-190 (2026-08-12): créditos materializados desde una
            // solicitud manual del Acta de Comité pueden no tener cuenta de
            // portal asociada (cliente_id null — ver ActaComiteController::
            // materializarSolicitudesManuales()). crearSolicitudDocumentos()
            // exige un cliente_id real (FK NOT NULL contra users), así que
            // se bloquea acá con un mensaje claro en vez de romper por FK.
            if ($request->boolean('requiere_documentos') && !$credito->cliente_id) {
                return response()->json([
                    'message' => 'Este cliente no tiene una cuenta de portal para recibir la solicitud de documentación. Gestione sin requerir documentos o contacte al administrador.',
                ], 422);
            }
        }

        // VAL-06: síntesis SARLAFT debe existir antes de notificar.
        if ($resultado === 'sarlaft_desfavorable' && empty(($credito->documentos ?? [])['sintesis_oficial_cumplimiento'] ?? null)) {
            return response()->json([
                'message' => 'No fue posible consultar la síntesis de validación. Intente nuevamente o contacte al administrador.',
            ], 422);
        }

        $asunto = $request->input('asunto');
        $mensaje = $request->input('mensaje');
        $destino = $request->input('destino');

        // VAL-07: si el envío falla, la solicitud sigue sin gestionar.
        try {
            Mail::to($destino)->send(new GestionCreditoNotificacionMail($credito, $asunto, $mensaje));
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'La notificación no pudo enviarse. La solicitud continúa pendiente de gestión.',
            ], 422);
        }

        $estadoAnterior = $credito->estado;
        if ($resultado === 'aprobada_garantias') {
            $credito->estado = 'formalizacion_garantias';
        } elseif ($resultado === 'pendiente_comite' && !$request->boolean('requiere_documentos')) {
            // SCRUM-191 (2026-08-12): si no se requiere documentación
            // adicional, no hay nada que esperar del cliente — el crédito
            // vuelve directo a la cola del Comité en vez de quedar
            // estancado para siempre en pendiente_comite (antes no existía
            // ninguna salida de este estado sin documentos).
            $credito->estado = 'comite_evaluacion';
        }
        // sarlaft_desfavorable, rechazada_comite y pendiente_comite CON
        // documentos requeridos conservan el mismo estado (§5.5): solo
        // cambia la marca de gestión — la salida de pendiente_comite con
        // documentos ocurre en revisarDocumento(), cuando el Coordinador
        // aprueba todo lo reenviado por el cliente.

        $credito->solicitud_gestionada = true;
        $credito->fecha_gestion = now();

        $detalle = $credito->gestion_detalle ?? [];
        $detalle[] = [
            'fecha' => now()->toIso8601String(),
            'destino' => $destino,
            'asunto' => $asunto,
            'mensaje' => $mensaje,
            'preset_id' => $request->input('preset_id'),
            'requiere_documentos' => $request->has('requiere_documentos') ? $request->boolean('requiere_documentos') : null,
            'gestionado_por_id' => $user->id,
            'gestionado_por' => $user->name,
        ];
        $credito->gestion_detalle = $detalle;

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => 'Gestión registrada y notificación enviada desde Gestión de Créditos.',
        ];
        $credito->historial_estados = $historial;

        $credito->save();

        // Pendiente por Comité con requiere_documentos = Sí: habilita la
        // carga en Mis créditos vía el mismo mecanismo de SCRUM-146.
        if ($resultado === 'pendiente_comite' && $request->boolean('requiere_documentos')) {
            $this->crearSolicitudDocumentos($credito, (int) $request->input('preset_id'), $user->id);
        }

        return response()->json([
            'message' => 'La gestión fue registrada y la notificación enviada correctamente.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-190 (2026-08-12): el filtro original miraba solo el `estado`
     * ACTUAL del crédito, pero `notificar()` avanza `aprobada_garantias` →
     * `formalizacion_garantias` al gestionar (línea ~234) — el crédito
     * desaparecía de la bandeja justo al gestionarse, cuando debía seguir
     * visible con "Gestionada: Sí" (`solicitud_gestionada`/`fecha_gestion`,
     * ya soportado por el frontend). Los otros 3 orígenes no cambian de
     * `estado` al notificar (ver comentario en notificar()), así que no
     * tienen el mismo problema — solo se amplía la rama de comité aprobado.
     */
    /**
     * SCRUM-191 (2026-08-12, punto 1): documentos que el cliente reenvió
     * tras la notificación de "Pendiente por Comité con documentos
     * requeridos" — para que el Coordinador Comercial los revise sin salir
     * de Gestión de Créditos. Se ubica el `DocumentRequest` más reciente
     * por `solicitud_credito_id` (mismo criterio de correlación que usa
     * `crearSolicitudDocumentos()` al crearlo).
     */
    public function documentosPendientes(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $credito = CreditoOrdinario::findOrFail($creditoId);

        if (!$credito->solicitud_credito_id) {
            return response()->json(null);
        }

        $documentRequest = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)
            ->with(['items.requirement', 'items.upload'])
            ->orderByDesc('created_at')
            ->first();

        return response()->json($documentRequest);
    }

    /**
     * SCRUM-191 (2026-08-12, punto 1): aprobación/rechazo en un solo paso
     * por el Coordinador Comercial — a propósito NO reutiliza el flujo
     * genérico Operativo→Gerente de `ClientUploadController` (usado en el
     * onboarding inicial, SCRUM-146/152): ese es un pipeline de 2 pasos con
     * roles distintos, y acá el ticket pide explícitamente que sea el mismo
     * Coordinador Comercial quien decide, dentro de la pantalla que ya es
     * su dominio (Gestión de Créditos). Al aprobar el último ítem
     * pendiente, el crédito vuelve solo a `comite_evaluacion` — la
     * funcionalidad que el ticket marcó como inexistente.
     */
    public function revisarDocumento(Request $request, $creditoId, $itemId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::findOrFail($creditoId);

        $request->validate([
            'accion' => 'required|in:aprobar,rechazar',
            'observaciones' => 'nullable|string',
        ]);

        $item = DocumentRequestItem::with('request', 'upload')->findOrFail($itemId);
        if (!$credito->solicitud_credito_id || $item->request?->solicitud_credito_id !== $credito->solicitud_credito_id) {
            abort(404);
        }

        if (!$item->client_upload_id) {
            return response()->json(['message' => 'El cliente todavía no ha cargado este documento.'], 422);
        }

        $accion = $request->input('accion');
        $observaciones = $request->input('observaciones');
        $nuevoEstado = $accion === 'aprobar' ? 'aprobado' : 'rechazado';

        $item->upload->update([
            'status' => $nuevoEstado,
            'observations' => $observaciones ?: $item->upload->observations,
            'approved_by' => $user->id,
        ]);
        $item->update(['estado' => $nuevoEstado, 'observaciones' => $observaciones]);

        $documentRequest = $item->request;
        $pendientes = $documentRequest->items()->where('estado', '!=', 'aprobado')->count();

        if ($pendientes === 0) {
            $documentRequest->update(['estado' => 'completado']);
            $this->habilitarComiteSiAplica($credito, $documentRequest, $user);
        } else {
            $documentRequest->update(['estado' => 'pendiente']);
        }

        return response()->json([
            'message' => $accion === 'aprobar' ? 'Documento aprobado.' : 'Documento rechazado — el cliente puede volver a cargarlo.',
            'document_request' => $documentRequest->fresh(['items.requirement', 'items.upload']),
            'credito_disponible_comite' => $credito->fresh()->estado === 'comite_evaluacion',
        ]);
    }

    /**
     * Todos los documentos reenviados quedaron aprobados: el crédito vuelve
     * a `comite_evaluacion` (elegible de nuevo para Actas de Comité, ver
     * ActaComiteController::sincronizarSolicitudesElegibles()). Defensivo:
     * solo si sigue en `pendiente_comite` (si ya se movió por otro camino,
     * no lo tocamos de nuevo).
     */
    private function habilitarComiteSiAplica(CreditoOrdinario $credito, DocumentRequest $documentRequest, $user): void
    {
        if ($credito->estado !== 'pendiente_comite') {
            return;
        }

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => 'coordinador_comercial',
            'estado_anterior' => $credito->estado,
            'estado_nuevo' => 'comite_evaluacion',
            'comentario' => 'Documentación reenviada por el cliente aprobada en su totalidad. Vuelve a la cola del Comité de Crédito.',
        ];

        $credito->estado = 'comite_evaluacion';
        $credito->historial_estados = $historial;
        $credito->save();
    }

    private function queryBase()
    {
        return CreditoOrdinario::with(self::RELACIONES_DETALLE)
            ->where(function ($q) {
                $q->where(function ($qa) {
                    $qa->where('resultado_origen', 'comite_aprobado')
                        ->whereIn('estado', ['aprobada_garantias', 'formalizacion_garantias']);
                })
                    ->orWhere('estado', 'pendiente_comite')
                    ->orWhere(function ($qr) {
                        $qr->where('estado', 'rechazado')->whereIn('resultado_origen', ['sarlaft', 'comite_rechazado']);
                    });
            });
    }

    private function resolverClaveResultado(CreditoOrdinario $credito): ?string
    {
        foreach (self::RESULTADOS as $clave => $resultado) {
            if ($credito->estado === $resultado['estado'] && $credito->resultado_origen === $resultado['origen']) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * Fecha de validación (§3.3, columna 10): fecha del resultado
     * desfavorable del Auditor Interno, o fecha de la decisión del Comité
     * (fecha_reunion de la última Acta registrada que incluyó este
     * crédito).
     */
    private function conFechaValidacion(CreditoOrdinario $credito): CreditoOrdinario
    {
        if ($credito->resultado_origen === 'sarlaft') {
            $credito->fecha_validacion = optional($credito->sarlaft_diligenciado_at)->toDateString();
            return $credito;
        }

        $solicitudActa = $credito->actaComiteSolicitudes
            ->filter(fn ($s) => $s->actaComite && $s->actaComite->estado === 'aprobada')
            ->sortByDesc(fn ($s) => optional($s->actaComite->registrada_at)->timestamp)
            ->first();

        $credito->fecha_validacion = optional($solicitudActa?->actaComite?->fecha_reunion)->toDateString();

        return $credito;
    }

    /**
     * Reusa el mismo mecanismo de SCRUM-146 (DocumentRequest + items desde
     * un preset) para habilitar la carga del cliente en Mis créditos. No se
     * llama la ruta document-requests (restringida a superadmin/operativo)
     * porque acá el disparador legítimo es el Coordinador Comercial vía
     * Gestión de Créditos.
     */
    private function crearSolicitudDocumentos(CreditoOrdinario $credito, int $presetId, int $creadoPorId): void
    {
        $existente = DocumentRequest::where('cliente_id', $credito->cliente_id)
            ->where('estado', 'pendiente')
            ->first();

        if ($existente) {
            return;
        }

        $preset = DocumentPreset::findOrFail($presetId);
        $requirementIds = $preset->requirements()->pluck('document_requirements.id')->toArray();

        if (empty($requirementIds)) {
            return;
        }

        $documentRequest = DocumentRequest::create([
            'cliente_id' => $credito->cliente_id,
            'creado_por' => $creadoPorId,
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'pendiente',
        ]);

        foreach ($requirementIds as $reqId) {
            DocumentRequestItem::create([
                'document_request_id' => $documentRequest->id,
                'document_requirement_id' => $reqId,
                'estado' => 'pendiente',
            ]);
        }
    }

    private function autorizarRol(string $activeRole): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'coordinador_comercial') {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para acceder a Gestión de Créditos.',
            'rol_activo' => $activeRole,
        ], 403));
    }
}
