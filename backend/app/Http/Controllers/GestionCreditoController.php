<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Mail\FormalizacionGarantiasResultadoMail;
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
 *
 * SCRUM-193/205 (2026-08-17) agregan, después de 'aprobada_garantias', un
 * tramo nuevo y separado del flujo legacy de Crédito Ordinario (rol
 * Operativo en 'formalizacion_garantias', rol Gerente en
 * 'aprobacion_registro_cyf' — ninguno de los dos se toca ni se retira):
 * 'pendiente_formalizacion_garantias' (guardarFormalizacionGarantias(), el
 * Coordinador Comercial valida cada garantía por ítem) →
 * 'pendiente_registro_cyf' (registroCyf(), captura fecha + radicado) →
 * de ahí SÍ entra al 'aprobacion_registro_cyf' legacy para que Gerencia
 * apruebe con la pantalla ya existente.
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
     * SCRUM-193/205 (2026-08-17): las 2 tarjetas nuevas (Formalización de
     * Garantías, Registro de Crédito en CYF) no necesitan resultado_origen
     * — a diferencia de RESULTADOS, cada `estado` acá es único y no se
     * comparte con ningún otro resultado. Tienen sus propios endpoints
     * (formalizacionGarantias()/registroCyf()), no pasan por notificar().
     */
    private const ESTADOS_SIMPLES = [
        'pendiente_formalizacion_garantias' => 'pendiente_formalizacion_garantias',
        'pendiente_registro_cyf'            => 'pendiente_registro_cyf',
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
        } elseif ($request->filled('estado') && $request->estado !== 'todos' && isset(self::ESTADOS_SIMPLES[$request->estado])) {
            $query->where('estado', self::ESTADOS_SIMPLES[$request->estado]);
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
        foreach (self::ESTADOS_SIMPLES as $clave => $estado) {
            $conteos[$clave] = CreditoOrdinario::where('estado', $estado)
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
        if ($resultado === 'aprobada_garantias') {
            if (!$request->filled('preset_id')) {
                return response()->json(['message' => 'Seleccione la documentación requerida.'], 422);
            }
            // SCRUM-193/205: mismo guard que SCRUM-190 para pendiente_comite
            // (ver más abajo) — crearSolicitudDocumentos() exige cliente_id
            // real (FK NOT NULL contra users), y créditos materializados
            // desde una solicitud manual del Acta pueden no tenerlo.
            if (!$credito->cliente_id) {
                return response()->json([
                    'message' => 'Este cliente no tiene una cuenta de portal para recibir la solicitud de documentación. Contacte al administrador.',
                ], 422);
            }
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
        // SCRUM-193/205 (2026-08-17): 'aprobada_garantias' YA NO transiciona
        // a 'formalizacion_garantias' — ese estado es del flujo legacy
        // (rol Operativo, pantalla de Crédito Ordinario), que se mantiene
        // intacto y sin credits nuevos entrando por acá. El estado se queda
        // en 'aprobada_garantias' mientras el cliente diligencia las
        // garantías del preset (mismo patrón que 'pendiente_comite' más
        // abajo); crearSolicitudDocumentos() habilita esa carga y
        // ClientUploadController::syncRequestItem() avanza automáticamente
        // a 'pendiente_formalizacion_garantias' cuando el cliente termina.
        if ($resultado === 'pendiente_comite' && !$request->boolean('requiere_documentos')) {
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

        // SCRUM-193/205: Aprobada para Gestión de Garantías siempre requiere
        // preset (VAL-04) — habilita la carga de garantías en Mis créditos.
        if ($resultado === 'aprobada_garantias') {
            $this->crearSolicitudDocumentos($credito, (int) $request->input('preset_id'), $user->id);
        }

        return response()->json([
            'message' => 'La gestión fue registrada y la notificación enviada correctamente.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-190 (2026-08-12): el filtro original de `queryBase()` miraba
     * solo el `estado` ACTUAL del crédito, pero antes de SCRUM-193/205
     * `notificar()` avanzaba `aprobada_garantias` → `formalizacion_garantias`
     * al gestionar — el crédito desaparecía de la bandeja justo al
     * gestionarse, cuando debía seguir visible con "Gestionada: Sí"
     * (`solicitud_gestionada`/`fecha_gestion`, ya soportado por el
     * frontend). Ese `orWhere` de `queryBase()` sigue existiendo por
     * compatibilidad con créditos que ya quedaron en ese estado antes del
     * cambio — ningún crédito nuevo vuelve a entrar por ahí (ver
     * `notificar()`, rama `aprobada_garantias`).
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
     * pendiente, el crédito pasa a `aprobada_garantias` (SCRUM-199).
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
            $this->habilitarGarantiasSiAplica($credito, $documentRequest, $user);
        } else {
            $documentRequest->update(['estado' => 'pendiente']);
        }

        return response()->json([
            'message' => $accion === 'aprobar' ? 'Documento aprobado.' : 'Documento rechazado — el cliente puede volver a cargarlo.',
            'document_request' => $documentRequest->fresh(['items.requirement', 'items.upload']),
            'credito_disponible_garantias' => $credito->fresh()->estado === 'aprobada_garantias',
        ]);
    }

    /**
     * SCRUM-205: detalle solo lectura para la pantalla de Formalización de
     * Garantías — info del cliente/representante legal/crédito (ya viene en
     * RELACIONES_DETALLE) + el DocumentRequest más reciente del preset de
     * garantías (mismo criterio de correlación que documentosPendientes()).
     */
    public function formalizacionGarantias(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $credito = $this->queryBase()->findOrFail($creditoId);

        $documentRequest = $credito->solicitud_credito_id
            ? DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)
                ->with(['items.requirement', 'items.upload'])
                ->orderByDesc('created_at')
                ->first()
            : null;

        return response()->json([
            'credito' => $this->conFechaValidacion($credito),
            'document_request' => $documentRequest,
        ]);
    }

    /**
     * SCRUM-205: valida cada garantía del preset (aprobada/no aprobada +
     * observaciones obligatorias si no aprobada) y decide el destino del
     * crédito según el resultado consolidado (§9 del ticket):
     * - alguna no aprobada → vuelve a 'aprobada_garantias' para que el
     *   cliente corrija solo los ítems marcados 'rechazado' (mismo mecanismo
     *   de reenvío por ítem que ya usa pendiente_comite/SCRUM-191).
     * - todas aprobadas → pasa a 'pendiente_registro_cyf' (SCRUM-193).
     * Estado de entrada obligatorio 'pendiente_formalizacion_garantias' —
     * no reutiliza el 'formalizacion_garantias' legacy (rol Operativo, ver
     * docblock de la clase) a propósito, para no pisarlo.
     */
    public function guardarFormalizacionGarantias(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with('cliente')->findOrFail($creditoId);

        if ($credito->estado !== 'pendiente_formalizacion_garantias') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Formalización de Garantías.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:document_request_items,id',
            'items.*.validacion' => 'required|in:aprobada,no_aprobada',
            'items.*.observaciones' => 'required_if:items.*.validacion,no_aprobada|nullable|string',
        ], [
            'items.required' => 'No hay garantías para validar.',
            'items.*.validacion.required' => 'Toda garantía debe tener un resultado de validación.',
            'items.*.observaciones.required_if' => 'Las garantías no aprobadas requieren observaciones.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $documentRequest = DocumentRequest::where('solicitud_credito_id', $credito->solicitud_credito_id)
            ->with('items.requirement')
            ->orderByDesc('created_at')
            ->first();

        if (!$documentRequest) {
            return response()->json(['message' => 'No se encontró la solicitud de garantías de este crédito.'], 422);
        }

        $itemsPorId = $documentRequest->items->keyBy('id');
        $detalleCorreo = [];
        $hayNoAprobada = false;

        foreach ($request->input('items') as $entrada) {
            $item = $itemsPorId->get($entrada['item_id']);
            if (!$item) {
                return response()->json(['message' => 'Una de las garantías no pertenece a esta solicitud.'], 422);
            }

            $aprobada = $entrada['validacion'] === 'aprobada';
            $hayNoAprobada = $hayNoAprobada || !$aprobada;

            $item->update([
                'estado' => $aprobada ? 'aprobado' : 'rechazado',
                'observaciones' => $entrada['observaciones'] ?? null,
            ]);

            $detalleCorreo[] = [
                'garantia' => $item->requirement->nombre ?? 'Garantía',
                'resultado' => $aprobada ? 'Aprobada' : 'No aprobada',
                'observaciones' => $entrada['observaciones'] ?? null,
            ];
        }

        $documentRequest->update(['estado' => $hayNoAprobada ? 'pendiente' : 'completado']);

        $estadoAnterior = $credito->estado;
        $credito->estado = $hayNoAprobada ? 'aprobada_garantias' : 'pendiente_registro_cyf';
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => $hayNoAprobada
                ? 'Formalización de Garantías: una o más garantías no fueron aprobadas. Vuelve al cliente para ajustes.'
                : 'Formalización de Garantías: todas las garantías fueron aprobadas. Pasa a Registro de Crédito en CYF.',
        ];
        $credito->historial_estados = $historial;
        $credito->save();

        $nombreCliente = $this->nombreClienteParaCorreo($credito);
        $destino = $this->correoClienteParaNotificacion($credito);
        if ($destino) {
            try {
                Mail::to($destino)->send(new FormalizacionGarantiasResultadoMail($credito, $nombreCliente, $detalleCorreo, $hayNoAprobada));
            } catch (Throwable $e) {
                // La validación ya quedó guardada — un fallo de envío no debe
                // revertirla (a diferencia de notificar(), acá el correo es
                // solo informativo, no la acción que dispara la transición).
            }
        }

        return response()->json([
            'message' => $hayNoAprobada
                ? 'Validación registrada. La solicitud vuelve al cliente para ajustes.'
                : 'Validación registrada. La solicitud pasa a Registro de Crédito en CYF.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-193: captura fecha + radicado del registro del crédito en CYF.
     * Estado de entrada obligatorio 'pendiente_registro_cyf' (viene de
     * guardarFormalizacionGarantias()). Al guardar, el crédito pasa al
     * estado legacy 'aprobacion_registro_cyf' para que la aprobación de
     * Gerencia ya existente (pantalla de Crédito Ordinario) se mantenga
     * intacta — se escribe `documentos_raw['registro_cyf']` con el radicado
     * para satisfacer el gate de esa pantalla
     * (`!empty($documentos['registro_cyf'])`, ver CreditoOrdinarioController)
     * sin duplicar esa lógica acá.
     */
    public function registroCyf(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::findOrFail($creditoId);

        if ($credito->estado !== 'pendiente_registro_cyf') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Registro de Crédito en CYF.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'fecha_registro_cyf' => 'required|date',
            'radicado_cyf' => 'required|string',
        ], [
            'fecha_registro_cyf.required' => 'Ingrese la fecha del registro del crédito en CYF.',
            'fecha_registro_cyf.date' => 'Ingrese una fecha válida.',
            'radicado_cyf.required' => 'Ingrese el número de radicado en CYF.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $radicado = trim((string) $request->input('radicado_cyf'));
        if ($radicado === '') {
            return response()->json(['message' => 'Ingrese el número de radicado en CYF.'], 422);
        }

        $documentos = $credito->documentos_raw ?? [];
        $documentos['registro_cyf'] = $radicado;

        $estadoAnterior = $credito->estado;
        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => 'aprobacion_registro_cyf',
            'comentario' => "Crédito registrado en CYF (radicado {$radicado}). Pasa a aprobación de Gerencia.",
        ];

        $credito->fecha_registro_cyf = $request->input('fecha_registro_cyf');
        $credito->radicado_cyf = $radicado;
        $credito->documentos = $documentos;
        $credito->estado = 'aprobacion_registro_cyf';
        $credito->solicitud_gestionada = true;
        $credito->fecha_gestion = now();
        $credito->historial_estados = $historial;
        $credito->save();

        return response()->json([
            'message' => 'El crédito quedó registrado en CYF y disponible para la aprobación de Gerencia.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /** Mismo criterio de resolución que el frontend (correoCliente() en
     * gestion-creditos-detalle.component.ts): correo de persona natural o
     * jurídica, con fallback al email de la cuenta de portal si existe. */
    private function correoClienteParaNotificacion(CreditoOrdinario $credito): ?string
    {
        $cliente = $credito->solicitudCredito?->cliente;

        return $cliente?->correo_electronico
            ?: $cliente?->correo_electronico_empresarial
            ?: $credito->cliente?->email
            ?: null;
    }

    private function nombreClienteParaCorreo(CreditoOrdinario $credito): string
    {
        $cliente = $credito->solicitudCredito?->cliente;
        if (!$cliente) {
            return 'cliente';
        }

        if ($cliente->nombre_razon_social) {
            return $cliente->nombre_razon_social;
        }

        return trim(($cliente->nombres ?? '') . ' ' . ($cliente->primer_apellido ?? '')) ?: 'cliente';
    }

    /**
     * Todos los documentos reenviados quedaron aprobados: el crédito pasa a
     * `aprobada_garantias` y vuelve a aparecer pendiente de gestión en esa
     * bandeja (mismo patrón de reseteo de `solicitud_gestionada`/`fecha_gestion`
     * que ActaComiteController::sincronizarCreditosOrdinarios()), para que el
     * Coordinador Comercial lo re-gestione seleccionando el preset de
     * garantías (SCRUM-199). Defensivo: solo si sigue en `pendiente_comite`
     * (si ya se movió por otro camino, no lo tocamos de nuevo).
     */
    private function habilitarGarantiasSiAplica(CreditoOrdinario $credito, DocumentRequest $documentRequest, $user): void
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
            'estado_nuevo' => 'aprobada_garantias',
            'comentario' => 'Documentación reenviada por el cliente aprobada en su totalidad. Pasa a Aprobada para gestión de garantías.',
        ];

        $credito->estado = 'aprobada_garantias';
        $credito->resultado_origen = 'comite_aprobado';
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
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
                    // SCRUM-193/205: estados propios del flujo nuevo de
                    // Formalización de Garantías / Registro CYF — no llevan
                    // resultado_origen (ver ESTADOS_SIMPLES).
                    ->orWhereIn('estado', array_values(self::ESTADOS_SIMPLES))
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
        // SCRUM-193/205 (2026-08-17): acotado por solicitud_credito_id, no
        // solo cliente_id — un cliente puede tener más de un crédito en
        // trámite (ej. una re-solicitud de documentos de otro crédito
        // todavía 'pendiente'); sin este filtro, esa request ajena hacía
        // que este método no-opeara en silencio y el cliente nunca viera la
        // solicitud de garantías de ESTE crédito.
        $existente = DocumentRequest::where('cliente_id', $credito->cliente_id)
            ->where('solicitud_credito_id', $credito->solicitud_credito_id)
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
