<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Mail\DesembolsoAprobadoTesoreriaMail;
use App\Mail\DesembolsoRechazadoOperativoMail;
use App\Mail\DesembolsoRegistradoMail;
use App\Mail\FormalizacionGarantiasCoordinadorMail;
use App\Mail\FormalizacionGarantiasResultadoMail;
use App\Mail\GestionCreditoNotificacionMail;
use App\Mail\RegistroCyfAprobadoCoordinadorMail;
use App\Mail\RegistroCyfAprobadoMail;
use App\Mail\RegistroCyfPendienteAprobacionMail;
use App\Mail\RegistroCyfRechazadoCoordinadorMail;
use App\Mail\TransferenciaRealizadaClienteMail;
use App\Mail\TransferenciaRegistradaInternaMail;
use App\Models\ActivityLog;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\EntidadBancaria;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\ConfiguracionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
 * rol Operativo valida cada garantía por ítem — SCRUM-237, antes lo hacía
 * Coordinador Comercial, ver ROLES_POR_CLAVE) →
 * 'pendiente_registro_cyf' (registroCyf(), captura fecha + radicado) →
 * de ahí SÍ entra al 'aprobacion_registro_cyf' legacy para que Gerencia
 * apruebe con la pantalla ya existente.
 *
 * SCRUM-211/215/219 (2026-08-17) continúan la cadena tomando posesión
 * directa, con pantallas propias acá, de 3 de los 4 estados legacy que ya
 * estaban role-mapeados en CreditoOrdinarioController::transition() pero
 * sin pantalla dedicada ni notificación por correo:
 * 'aprobacion_registro_cyf' (Gerente aprueba/rechaza el registro de
 * Operativo/Coordinador — SCRUM-211) → 'desembolso_ingreso' (Operativo
 * registra la Operación de Desembolso con los documentos del preset —
 * SCRUM-215) → 'desembolso_aprobacion' (Gerente aprueba/rechaza — SCRUM-219,
 * aprobado salía a 'ejecucion_transferencia' para Tesorería sin pantalla
 * propia todavía en ese momento).
 *
 * SCRUM-224 (2026-08-19) completa la cadena tomando posesión también de
 * 'ejecucion_transferencia' (Registro de Transferencia Bancaria, rol
 * Tesorería): transferenciaBancaria() captura la información bancaria del
 * beneficiario y el registro de la transacción, pasa el crédito al estado
 * legacy 'completado' (mismo terminal que ya usaba el switch genérico) y
 * notifica al cliente (con el comprobante adjunto) y a Gerente/Coordinador
 * Comercial. El switch genérico de `CreditoOrdinarioController::transition()`
 * para estos 4 estados queda intacto como vía de escape (mismo criterio
 * no-destructivo que registroCyf()), pero deja de ser el camino esperado.
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
     * SCRUM-268 (§9, CA-01/RN-02): asunto y mensaje predeterminados por
     * escenario — precargan el formulario de notificar() (ver show()) y el
     * Coordinador Comercial los puede editar libremente (RN-03/RN-04); acá
     * solo vive el cuerpo del mensaje de acompañamiento, NUNCA el
     * saludo/firma/lista de documentos/botón, que son componentes
     * automáticos que arma la Mailable (§4 "Composición del correo").
     * {numero} se sustituye por CreditoOrdinario::numero_solicitud al
     * precargar (plantillaSugerida()) — si el Coordinador lo borra o lo
     * reescribe, esa edición es la que se envía (RN-04), no se vuelve a
     * inyectar.
     */
    private const PLANTILLAS = [
        'aprobada_garantias' => [
            'asunto' => 'Documentación requerida para la formalización de garantías - Solicitud {numero}',
            'mensaje' => 'Nos permitimos informarle que su solicitud de crédito {numero} fue aprobada por el Comité de Crédito. Para continuar con la formalización, agradecemos diligenciar y cargar en el sistema los documentos requeridos como garantías.',
        ],
        'sarlaft_desfavorable' => [
            'asunto' => 'Resultado de su solicitud de crédito - {numero}',
            'mensaje' => "Una vez realizadas las validaciones correspondientes a su solicitud de crédito {numero}, le informamos que no es posible continuar con el trámite.\n\nAgradecemos el interés y la confianza depositada en Proseguir Soluciones de Liquidez.",
        ],
        'rechazada_comite' => [
            'asunto' => 'Decisión del Comité de Crédito - Solicitud {numero}',
            'mensaje' => "Le informamos que, después de evaluar integralmente su solicitud de crédito {numero}, el Comité de Crédito decidió no aprobarla en esta oportunidad.\n\nAgradecemos su interés en nuestros servicios y la confianza depositada en Proseguir Soluciones de Liquidez.",
        ],
        'pendiente_comite' => [
            'asunto' => 'Estado de su solicitud de crédito - {numero}',
            'mensaje' => "Le informamos que el Comité de Crédito decidió dejar pendiente su solicitud de crédito {numero}.\n\nEsta decisión no corresponde a una aprobación ni a una negación definitiva. Podrá retomar el proceso más adelante, cuando sus condiciones financieras lo permitan. Nuestro equipo estará disponible para orientarle.",
        ],
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
        // SCRUM-211/215/219: estados legacy que ya existían en el switch de
        // CreditoOrdinarioController::transition() (clave === estado, ver
        // docblock de la clase) — acá pasan a tener tarjeta, pantalla propia
        // y notificación por correo.
        'aprobacion_registro_cyf'           => 'aprobacion_registro_cyf',
        'desembolso_ingreso'                => 'desembolso_ingreso',
        'desembolso_aprobacion'             => 'desembolso_aprobacion',
        // SCRUM-224: Registro de Transferencia Bancaria (Tesorería).
        'ejecucion_transferencia'           => 'ejecucion_transferencia',
    ];

    /**
     * SCRUM-211/215/219: a diferencia de RESULTADOS/ESTADOS_SIMPLES (qué
     * credit cae en cada tarjeta), esto define QUIÉN puede ver/contar cada
     * tarjeta — cada una de las 4 nuevas/reasignadas es del dominio
     * exclusivo de un solo rol (Gerente, Operativo o Tesorería), a
     * diferencia de las 5 restantes que son del Coordinador Comercial.
     * superadmin no pasa por este mapa (ve todo, ver
     * clavesVisiblesParaRol()). 'pendiente_formalizacion_garantias' pasó de
     * Coordinador Comercial a Operativo en SCRUM-237 (mismo rol que ya
     * gestiona el tramo legacy 'formalizacion_garantias', ver docblock de
     * la clase).
     */
    private const ROLES_POR_CLAVE = [
        'sarlaft_desfavorable'               => ['coordinador_comercial'],
        'aprobada_garantias'                 => ['coordinador_comercial'],
        'rechazada_comite'                   => ['coordinador_comercial'],
        'pendiente_comite'                   => ['coordinador_comercial'],
        'pendiente_formalizacion_garantias'  => ['operativo'],
        'pendiente_registro_cyf'             => ['coordinador_comercial'],
        'aprobacion_registro_cyf'            => ['gerente'],
        'desembolso_ingreso'                 => ['operativo'],
        'desembolso_aprobacion'              => ['gerente'],
        'ejecucion_transferencia'            => ['tesoreria'],
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

        // SCRUM-211/215/219: Gerente/Operativo solo ven las solicitudes de
        // SU tarjeta (visibilidad restringida por ticket) — superadmin y
        // coordinador_comercial no se restringen acá, ya que
        // coordinador_comercial solo tiene claves asignadas de todos modos.
        if ($activeRole !== 'superadmin') {
            $clavesVisibles = $this->clavesVisiblesParaRol($activeRole);
            $query->where(function ($q) use ($clavesVisibles) {
                foreach ($clavesVisibles as $clave) {
                    $q->orWhere(function ($qc) use ($clave) {
                        $this->aplicarCondicionClave($qc, $clave);
                    });
                }
            });
        }

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

        if ($request->filled('estado') && $request->estado !== 'todos'
            && (isset(self::RESULTADOS[$request->estado]) || isset(self::ESTADOS_SIMPLES[$request->estado]))) {
            $query->where(function ($q) use ($request) {
                $this->aplicarCondicionClave($q, $request->estado);
            });
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
     * Conteos de tarjetas: solo Solicitud gestionada = No (§3.1). SCRUM-211/
     * 215/219: cada rol solo ve las claves que le pertenecen (ver
     * ROLES_POR_CLAVE) — superadmin ve todas.
     */
    public function tarjetas(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $conteos = [];
        foreach ($this->clavesVisiblesParaRol($activeRole) as $clave) {
            $query = CreditoOrdinario::query();
            $this->aplicarCondicionClave($query, $clave);
            $conteos[$clave] = $query->where('solicitud_gestionada', false)->count();
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

        // SCRUM-211/215/219: mismo scoping de visibilidad que index()/
        // tarjetas() — Gerente/Operativo no pueden abrir el detalle de una
        // solicitud fuera de su propia clave.
        if ($activeRole !== 'superadmin') {
            $clave = $this->claveDelCredito($credito);
            if (!$clave || !in_array($activeRole, self::ROLES_POR_CLAVE[$clave] ?? [], true)) {
                abort(404);
            }
        }

        $credito = $this->conFechaValidacion($credito);

        // SCRUM-268 (CA-01/RN-02): precarga de asunto/mensaje por escenario
        // — solo tiene sentido mientras la solicitud sigue sin gestionar;
        // una vez gestionada, el formulario ya no se muestra (puedeGestionar
        // en el frontend) y gestion_detalle ya conserva lo que se envió.
        $resultado = $this->resolverClaveResultado($credito);
        if ($resultado && !$credito->solicitud_gestionada) {
            $credito->plantilla_sugerida = $this->plantillaSugerida($resultado, $credito);
        }

        return response()->json($credito);
    }

    /**
     * "Registrar y enviar notificación" (§5.5, VAL-01..08): valida,
     * envía el correo y solo si el envío no falla ejecuta la transición,
     * marca Solicitud gestionada = Sí y registra Fecha de la gestión.
     *
     * SCRUM-268 (RN-16/CA-16): la solicitud completa corre dentro de un
     * `lockForUpdate()` sobre el crédito — un doble clic o un reintento de
     * red que llegue mientras el primero todavía está en curso espera a
     * que ese primero libere el lock (commit) y encuentra
     * `solicitud_gestionada = true`, en vez de alcanzar a mandar un
     * segundo correo.
     */
    public function notificar(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

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

        return DB::transaction(function () use ($request, $creditoId, $activeRole, $user) {
            $credito = CreditoOrdinario::with(['cliente', 'solicitudCredito.cliente'])->lockForUpdate()->findOrFail($creditoId);
            $resultado = $this->resolverClaveResultado($credito);

            if (!$resultado) {
                return response()->json([
                    'message' => 'Esta solicitud no tiene un resultado pendiente de gestión.',
                ], 422);
            }

            // CA-16/RN-16: la solicitud ya fue gestionada por otra llamada
            // que ganó el lock primero — no hay un segundo correo que
            // mandar, se informa con el detalle de la gestión existente.
            if ($credito->solicitud_gestionada) {
                $ultimaGestion = collect($credito->gestion_detalle ?? [])->last();
                return response()->json([
                    'message' => trim(sprintf(
                        'Esta solicitud ya fue gestionada%s%s.',
                        $ultimaGestion ? ' por ' . $ultimaGestion['gestionado_por'] : '',
                        $credito->fecha_gestion ? ' el ' . $credito->fecha_gestion->format('d/m/Y H:i') : ''
                    )),
                ], 409);
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
            $activityLog = app(ActivityLogService::class);

            // §4/§9 "Composición del correo": lista dinámica de documentos
            // del preset (garantías, o pendiente_comite con documentos) y
            // URL autenticada del botón de acción (RN-08) — misma
            // información para el envío real y para la vista previa
            // (previsualizarNotificacion()).
            [$documentos, $urlAccion] = $this->documentosYUrlAccion($request, $credito);

            // VAL-07: si el envío falla, la solicitud sigue sin gestionar.
            try {
                Mail::to($destino)->send(new GestionCreditoNotificacionMail(
                    $credito,
                    $asunto,
                    $mensaje,
                    $resultado,
                    $documentos,
                    $urlAccion
                ));
            } catch (Throwable $e) {
                // CA-15/CA-17: un intento fallido también es trazabilidad —
                // antes de SCRUM-268 no quedaba ningún registro de un envío
                // que falló, solo del que finalmente tuvo éxito.
                $intentosPrevios = ActivityLog::where('entidad_type', CreditoOrdinario::class)
                    ->where('entidad_id', $credito->id)
                    ->where('accion', 'gestion_credito_notificacion_fallida')
                    ->count();

                $activityLog->registrar(
                    'gestion_credito_notificacion_fallida',
                    "Falló el envío de la notificación ({$resultado}) de la solicitud {$credito->numero_solicitud}.",
                    $user,
                    $credito,
                    [
                        'escenario' => $resultado,
                        'destino' => $destino,
                        'asunto' => $asunto,
                        'mensaje' => $mensaje,
                        'preset_id' => $request->input('preset_id'),
                        'requiere_documentos' => $request->has('requiere_documentos') ? $request->boolean('requiere_documentos') : null,
                        'error' => $e->getMessage(),
                        'intento_numero' => $intentosPrevios + 1,
                    ]
                );

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
                $this->crearSolicitudDocumentos($credito, (int) $request->input('preset_id'), $user->id, 'pre_comite');
            }

            // SCRUM-193/205: Aprobada para Gestión de Garantías siempre requiere
            // preset (VAL-04) — habilita la carga de garantías en Mis créditos.
            // SCRUM-229: se tagea 'garantias' para que Crédito Ordinario pueda
            // mostrar específicamente este DocumentRequest en la Etapa 4.
            if ($resultado === 'aprobada_garantias') {
                $this->crearSolicitudDocumentos($credito, (int) $request->input('preset_id'), $user->id, 'garantias');
            }

            // CA-17/RN-17: trazabilidad centralizada (SCRUM-246) del envío
            // exitoso, además de gestion_detalle/historial_estados (que ya
            // alimentan el frontend de Gestión de Créditos).
            $activityLog->registrar(
                'gestion_credito_notificacion_enviada',
                "Notificación ({$resultado}) enviada para la solicitud {$credito->numero_solicitud}.",
                $user,
                $credito,
                [
                    'escenario' => $resultado,
                    'destino' => $destino,
                    'asunto' => $asunto,
                    'mensaje' => $mensaje,
                    'preset_id' => $request->input('preset_id'),
                    'requiere_documentos' => $request->has('requiere_documentos') ? $request->boolean('requiere_documentos') : null,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $credito->estado,
                ]
            );

            return response()->json([
                'message' => 'La gestión fue registrada y la notificación enviada correctamente.',
                'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
            ]);
        });
    }

    /**
     * Vista previa (RN-06): renderiza la MISMA Mailable que notificar()
     * usará al enviar, con lo que el Coordinador tenga diligenciado en ese
     * momento — sin enviar el correo ni tocar el estado de la solicitud.
     * Evita que la vista previa se desincronice del correo real (dos
     * plantillas Blade a mantener en paralelo).
     */
    public function previsualizarNotificacion(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $credito = CreditoOrdinario::with(['cliente', 'solicitudCredito.cliente'])->findOrFail($creditoId);
        $resultado = $this->resolverClaveResultado($credito);

        if (!$resultado) {
            return response()->json([
                'message' => 'Esta solicitud no tiene un resultado pendiente de gestión.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'asunto' => 'required|string',
            'mensaje' => 'required|string',
            'preset_id' => 'nullable|exists:document_presets,id',
        ], [
            'asunto.required' => 'Ingrese el asunto del correo.',
            'mensaje.required' => 'Ingrese el mensaje de acompañamiento.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        [$documentos, $urlAccion] = $this->documentosYUrlAccion($request, $credito);

        $mail = new GestionCreditoNotificacionMail(
            $credito,
            $request->input('asunto'),
            $request->input('mensaje'),
            $resultado,
            $documentos,
            $urlAccion
        );

        return response($mail->render(), 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * SCRUM-268 (§4/§9): lista de nombres de documentos del preset
     * seleccionado (vacía si no aplica) + URL autenticada al portal para
     * el botón de acción — compartido entre notificar() y
     * previsualizarNotificacion() para que ambos compongan exactamente el
     * mismo correo.
     */
    private function documentosYUrlAccion(Request $request, CreditoOrdinario $credito): array
    {
        if (!$request->filled('preset_id')) {
            return [[], null];
        }

        $preset = DocumentPreset::find($request->input('preset_id'));
        $documentos = $preset?->requirements()->pluck('nombre')->toArray() ?? [];
        $urlAccion = $this->urlIngresoSistema('/creditos/' . $credito->id);

        return [$documentos, $urlAccion];
    }

    /**
     * SCRUM-268 (CA-01/RN-02): asunto y mensaje predeterminados del
     * escenario, con {numero} ya sustituido por el número de solicitud
     * real (CA-06 — variables automáticas).
     */
    private function plantillaSugerida(string $resultado, CreditoOrdinario $credito): array
    {
        $plantilla = self::PLANTILLAS[$resultado] ?? null;

        if (!$plantilla) {
            return ['asunto' => '', 'mensaje' => ''];
        }

        return [
            'asunto' => str_replace('{numero}', (string) $credito->numero_solicitud, $plantilla['asunto']),
            'mensaje' => str_replace('{numero}', (string) $credito->numero_solicitud, $plantilla['mensaje']),
        ];
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
     * pendiente, el crédito vuelve a `comite_evaluacion` para una nueva
     * Acta de Comité (SCRUM-236 — antes saltaba directo a
     * `aprobada_garantias`, saltándose el Comité).
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

        if ($item->request?->estado === 'cancelado') {
            return response()->json(['message' => 'Esta solicitud de documentos fue reemplazada por una más reciente.'], 422);
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
            $this->retomarComiteSiAplica($credito, $documentRequest, $user);
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
     * SCRUM-205: detalle solo lectura para la pantalla de Formalización de
     * Garantías — info del cliente/representante legal/crédito (ya viene en
     * RELACIONES_DETALLE) + el DocumentRequest más reciente del preset de
     * garantías (mismo criterio de correlación que documentosPendientes()).
     */
    public function formalizacionGarantias(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarAccionOperativa($activeRole);

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
        $this->autorizarAccionOperativa($activeRole);
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

        // SCRUM-284: la notificación al Coordinador Comercial solo se genera
        // cuando todas las garantías quedaron aprobadas (§5 REGLA CRÍTICA del
        // ticket) — si hay ajustes, únicamente se avisa al cliente (arriba).
        if (!$hayNoAprobada) {
            $coordinador = $credito->solicitudCredito?->usuarioRegistra;
            if ($coordinador && $coordinador->email) {
                $urlAcceso = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/registro-cyf');
                try {
                    Mail::to($coordinador->email)->send(new FormalizacionGarantiasCoordinadorMail($credito, $nombreCliente, $urlAcceso));
                } catch (Throwable $e) {
                    // Informativo — no revierte la transición ya guardada.
                }
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
        // SCRUM-211: antes quedaba en true porque 'aprobacion_registro_cyf'
        // solo alimentaba la pantalla legacy de Crédito Ordinario, sin
        // tarjeta propia acá. Ahora sí la tiene (aprobacionRegistroCyf()) y
        // necesita aparecer como pendiente de gestión para Gerencia.
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->historial_estados = $historial;
        $credito->save();

        // SCRUM-288: 'gerente' no tiene modelo de asignación por crédito
        // (igual que 'operativo' en SCRUM-280) — se notifica a todos los
        // activos con el rol, vía notificarPorRol().
        $urlAcceso = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/aprobacion-registro-cyf');
        $this->notificarPorRol('gerente', new RegistroCyfPendienteAprobacionMail(
            $credito,
            $this->nombreClienteParaCorreo($credito),
            $user->name,
            $urlAcceso
        ));

        return response()->json([
            'message' => 'El crédito quedó registrado en CYF y disponible para la aprobación de Gerencia.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-211: Gerencia aprueba o rechaza el Registro de Crédito en CYF
     * (fecha + radicado capturados por registroCyf()). Estado de entrada
     * obligatorio 'aprobacion_registro_cyf'. Aprobar pasa a
     * 'desembolso_ingreso' (SCRUM-215) y notifica a Operativo; rechazar
     * limpia fecha/radicado y vuelve a 'pendiente_registro_cyf' para que se
     * registre de nuevo (§8.2 del ticket) — no se notifica en ese caso, el
     * ticket solo define correo para el resultado aprobado.
     */
    public function aprobacionRegistroCyf(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarAccionGerencial($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with('cliente')->findOrFail($creditoId);

        if ($credito->estado !== 'aprobacion_registro_cyf') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Aprobación de Registro de Crédito en CYF.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:aprobar,rechazar',
            'observaciones' => 'required_if:decision,rechazar|nullable|string',
        ], [
            'decision.required' => 'Seleccione una decisión.',
            'observaciones.required_if' => 'Ingrese las observaciones del rechazo.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $aprobado = $request->input('decision') === 'aprobar';
        $observaciones = $request->input('observaciones');
        $estadoAnterior = $credito->estado;

        if ($aprobado) {
            $credito->estado = 'desembolso_ingreso';
            $comentario = 'Registro de Crédito en CYF aprobado por Gerencia. Pasa a Registro de Operación de Desembolso en CYF.';
        } else {
            $documentos = $credito->documentos_raw ?? [];
            $documentos['registro_cyf'] = null;
            $credito->documentos = $documentos;
            $credito->fecha_registro_cyf = null;
            $credito->radicado_cyf = null;
            $credito->estado = 'pendiente_registro_cyf';
            $comentario = 'Registro de Crédito en CYF rechazado por Gerencia.'
                . ($observaciones ? " Observaciones: {$observaciones}." : '')
                . ' Debe registrarse nuevamente.';
        }

        // SCRUM-238 (numeral 6.7 de la historia de usuario): la pantalla de
        // Registro de Operación de Desembolso necesita mostrar "usuario que
        // realizó la operación" con número de identificación y nombres
        // completos — hasta acá el historial solo guardaba $user->name.
        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'usuario_documento' => $user->numero_documento,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => $comentario,
        ];
        $credito->historial_estados = $historial;
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->save();

        // SCRUM-294: NTF-01 y NTF-02 son independientes entre sí (una no
        // depende de que la otra haya tenido éxito) — Operativo recibe la
        // acción a ejecutar, Coordinador Comercial solo el aviso de que el
        // proceso continúa. En rechazo, únicamente NTF-03 a Coordinador
        // Comercial (§4.2 del ticket: no se notifica a Operativo ni se envía
        // aviso de continuidad).
        if ($aprobado) {
            $urlIngresoOperativo = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/desembolso-ingreso');
            $this->notificarPorRol('operativo', new RegistroCyfAprobadoMail($credito, $urlIngresoOperativo), $credito, 'registro_cyf_aprobado_operativo');

            $urlIngresoCoordinador = $this->urlIngresoSistema('/gestion-creditos');
            $this->notificarPorRol('coordinador_comercial', new RegistroCyfAprobadoCoordinadorMail($credito, $urlIngresoCoordinador), $credito, 'registro_cyf_aprobado_coordinador');
        } else {
            $urlIngresoCoordinador = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/registro-cyf');
            $this->notificarPorRol('coordinador_comercial', new RegistroCyfRechazadoCoordinadorMail($credito, $observaciones, $urlIngresoCoordinador), $credito, 'registro_cyf_rechazado_coordinador');
        }

        return response()->json([
            'message' => $aprobado
                ? 'Registro de Crédito en CYF aprobado. Disponible para el Registro de Operación de Desembolso.'
                : 'Registro de Crédito en CYF rechazado. Vuelve a Registro de Crédito en CYF para corrección.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-215: Operativo (o Super Admin) registra la Operación de
     * Desembolso en CYF adjuntando los documentos obligatorios de un preset
     * (DocumentPreset/DocumentRequirement, mismo catálogo de
     * document-presets ya usado en Formalización de Garantías/notificar()).
     * A diferencia de esos flujos, acá el propio Operativo sube los
     * archivos directamente — no hay solicitud al cliente que esperar — por
     * eso se guarda un snapshot propio en `documentos_desembolso` en vez de
     * crear un DocumentRequest. Estado de entrada obligatorio
     * 'desembolso_ingreso' (reutilizable también para editar tras un
     * rechazo de SCRUM-219, que devuelve la solicitud a este mismo estado).
     */
    public function desembolsoIngreso(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarAccionOperativa($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with('cliente')->findOrFail($creditoId);

        if ($credito->estado !== 'desembolso_ingreso') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Registro de Operación de Desembolso en CYF.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_preset_id' => 'required|integer|exists:document_presets,id',
            'observaciones' => 'nullable|string',
        ], [
            'document_preset_id.required' => 'Seleccione el preset de documentos de la operación.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $preset = DocumentPreset::with('requirements')->findOrFail($request->input('document_preset_id'));
        if ($preset->requirements->isEmpty()) {
            return response()->json(['message' => 'El preset seleccionado no tiene documentos configurados.'], 422);
        }

        $archivos = $request->file('documentos') ?? [];
        $faltantes = [];
        foreach ($preset->requirements as $requerimiento) {
            $archivo = $archivos[$requerimiento->id] ?? null;
            if (!$archivo || !$archivo->isValid()) {
                $faltantes[] = $requerimiento->nombre;
            }
        }
        if (!empty($faltantes)) {
            return response()->json([
                'message' => 'Faltan documentos obligatorios: ' . implode(', ', $faltantes) . '.',
            ], 422);
        }

        $documentosGuardados = [];
        foreach ($preset->requirements as $requerimiento) {
            $archivo = $archivos[$requerimiento->id];
            $nombreArchivo = $archivo->getClientOriginalName();
            $path = $archivo->storeAs('credito_documentos/' . $credito->id . '/desembolso', $nombreArchivo, 'public');
            $documentosGuardados[] = [
                'requirement_id' => $requerimiento->id,
                'nombre' => $requerimiento->nombre,
                'path' => $path,
                'original_name' => $nombreArchivo,
            ];
        }

        $observaciones = $request->input('observaciones');

        // Legacy compat: mismo criterio que registroCyf() con
        // 'registro_cyf' — satisface el gate de CreditoOrdinarioController
        // (`!empty($documentos['desembolso_egreso'])`) sin duplicar esa
        // lógica acá.
        $documentosRaw = $credito->documentos_raw ?? [];
        $documentosRaw['desembolso_egreso'] = array_column($documentosGuardados, 'path');
        $credito->documentos = $documentosRaw;

        $estadoAnterior = $credito->estado;
        $credito->documentos_desembolso = [
            'preset_id' => $preset->id,
            'preset_nombre' => $preset->nombre,
            'observaciones' => $observaciones,
            'documentos' => $documentosGuardados,
        ];
        $credito->estado = 'desembolso_aprobacion';

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => 'Operación de desembolso registrada en CYF (' . count($documentosGuardados)
                . ' documento(s), preset "' . $preset->nombre . '").'
                . ($observaciones ? " Observaciones: {$observaciones}." : '')
                . ' Pasa a aprobación de Gerencia.',
        ];
        $credito->historial_estados = $historial;
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->save();

        // SCRUM-299: mismo endpoint atiende el registro inicial y la
        // corrección posterior a un rechazo de Gerencia (desembolsoAprobacion()
        // devuelve el estado a 'desembolso_ingreso') — cada llamada aquí es
        // un evento de notificación nuevo, no un reintento del anterior, así
        // que no hay guard de duplicado que aplicar además del de estado ya
        // validado arriba.
        $urlIngreso = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/desembolso-aprobacion');
        $this->notificarPorRol('gerente', new DesembolsoRegistradoMail($credito, $urlIngreso), $credito, 'desembolso_registrado_gerente');

        return response()->json([
            'message' => 'Operación de desembolso registrada. Disponible para la aprobación de Gerencia.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-219: Gerencia aprueba o rechaza el Registro de Operación de
     * Desembolso en CYF hecho por Operativo (SCRUM-215). Estado de entrada
     * obligatorio 'desembolso_aprobacion'. Aprobar pasa al estado legacy
     * 'ejecucion_transferencia' (pantalla ya existente de Crédito Ordinario
     * para Tesorería, sin cambios) y notifica a Tesorería; rechazar vuelve a
     * 'desembolso_ingreso' para que Operativo ajuste registro y documentos,
     * y notifica a Operativo con las observaciones.
     */
    public function desembolsoAprobacion(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarAccionGerencial($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with('cliente')->findOrFail($creditoId);

        if ($credito->estado !== 'desembolso_aprobacion') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Aprobación de Registro de Operación de Desembolso en CYF.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:aprobar,rechazar',
            'observaciones' => 'nullable|string',
        ], [
            'decision.required' => 'Seleccione una decisión.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $aprobado = $request->input('decision') === 'aprobar';
        $observaciones = $request->input('observaciones');
        $estadoAnterior = $credito->estado;

        if ($aprobado) {
            $credito->estado = 'ejecucion_transferencia';
            $comentario = 'Operación de desembolso aprobada por Gerencia. Enviada a Tesorería para ejecutar la transferencia.';
        } else {
            $credito->estado = 'desembolso_ingreso';
            $comentario = 'Registro de operación de desembolso rechazado por Gerencia. Vuelve a Dirección Administrativa para ajustes.';
        }
        $comentario .= $observaciones ? " Observaciones: {$observaciones}." : '';

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => $comentario,
        ];
        $credito->historial_estados = $historial;
        // SCRUM-224: aprobado pasa a 'ejecucion_transferencia', que ahora SÍ
        // es una tarjeta activa (Tesorería) — antes de esto quedaba
        // 'gestionada = true' porque ese estado todavía no tenía tarjeta
        // propia. Rechazado sigue volviendo a 'desembolso_ingreso', también
        // una tarjeta activa. Ambos casos: pendiente de gestión.
        $credito->solicitud_gestionada = false;
        $credito->fecha_gestion = null;
        $credito->save();

        if ($aprobado) {
            // SCRUM-224: antes apuntaba a la pantalla legacy de Crédito
            // Ordinario (/creditos/:id) porque Tesorería todavía no tenía
            // pantalla propia — ahora sí (transferenciaBancaria()).
            $urlIngreso = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/transferencia-bancaria');
            $this->notificarPorRol('tesoreria', new DesembolsoAprobadoTesoreriaMail($credito, $urlIngreso));
        } else {
            $urlIngreso = $this->urlIngresoSistema('/gestion-creditos/' . $credito->id . '/desembolso-ingreso');
            $this->notificarPorRol('operativo', new DesembolsoRechazadoOperativoMail($credito, $observaciones, $urlIngreso));
        }

        return response()->json([
            'message' => $aprobado
                ? 'Operación de desembolso aprobada. Enviada a Tesorería para ejecutar la transferencia.'
                : 'Operación de desembolso rechazada. Vuelve a Registro de Operación de Desembolso para ajustes.',
            'credito' => $this->conFechaValidacion($credito->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * SCRUM-224: Tesorería (o Super Admin) registra los datos bancarios del
     * beneficiario y la transacción de la transferencia que efectúa el
     * desembolso, para una solicitud con la Operación de Desembolso en CYF
     * ya aprobada por Gerencia. Estado de entrada obligatorio
     * 'ejecucion_transferencia'. La confirmación final (valor, beneficiario,
     * cuenta enmascarada) es responsabilidad del frontend (SweetAlert) antes
     * de este único POST — acá no hay un segundo paso de "confirmar", igual
     * que guardarFormalizacionGarantias()/desembolsoIngreso().
     *
     * Al guardar: pasa a 'completado' (mismo terminal que ya usaba el switch
     * genérico de CreditoOrdinarioController para este estado — no se
     * inventa un estado nuevo), guarda un snapshot completo en
     * 'transferencia_bancaria' + 'numero_transaccion_bancaria' (columna
     * plana para la unicidad real de RN-08), y por compatibilidad hacia
     * atrás escribe también 'documentos.comprobante_transferencia' (mismo
     * criterio que desembolsoIngreso() con 'desembolso_egreso' — satisface
     * el gate legacy de CreditoOrdinarioController sin duplicar su lógica).
     */
    public function transferenciaBancaria(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarAccionTesoreria($activeRole);
        $user = Auth::user();

        $credito = CreditoOrdinario::with(self::RELACIONES_DETALLE)->findOrFail($creditoId);

        if ($credito->estado !== 'ejecucion_transferencia') {
            return response()->json([
                'message' => 'Esta solicitud no está pendiente de Registro de Transferencia Bancaria.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'titular_cuenta' => 'required|string|max:255',
            'tipo_documento_titular_id' => 'required|integer|exists:document_types,id',
            'numero_documento_titular' => 'required|string|max:50',
            'entidad_bancaria_id' => 'required|integer|exists:entidades_bancarias,id',
            'tipo_cuenta' => 'required|in:ahorros,corriente',
            'numero_cuenta' => 'required|string|max:50',
            'numero_cuenta_confirmacion' => 'required|string|max:50',
            'moneda_cuenta' => 'required|string|max:10',
            'correo_notificacion_pago' => 'required|email',
            'certificado_bancario' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'observaciones_bancarias' => 'nullable|string',
            'fecha_transferencia' => 'required|date|before_or_equal:today',
            'hora_transferencia' => 'required|string',
            'valor_transaccion' => 'required|numeric|min:0.01',
            'valor_transaccion_confirmacion' => 'required|numeric',
            'numero_transaccion' => 'required|string|max:100|unique:credito_ordinarios,numero_transaccion_bancaria',
            'comprobante_transferencia' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'declaracion_validacion_bancaria' => 'required|accepted',
        ], [
            'tipo_documento_titular_id.required' => 'Seleccione el tipo de documento del titular.',
            'entidad_bancaria_id.required' => 'Seleccione la entidad bancaria.',
            'certificado_bancario.required' => 'Adjunte el certificado bancario para continuar.',
            'comprobante_transferencia.required' => 'Adjunte el comprobante de la transferencia para continuar.',
            'fecha_transferencia.before_or_equal' => 'La fecha de la transferencia no puede ser posterior a la fecha actual.',
            'numero_transaccion.unique' => 'El número de la transacción ya se encuentra registrado.',
            'declaracion_validacion_bancaria.required' => 'Confirme la validación de los datos bancarios del beneficiario.',
            'declaracion_validacion_bancaria.accepted' => 'Confirme la validación de los datos bancarios del beneficiario.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // RN-04: número de cuenta y confirmación deben coincidir.
        if ($request->input('numero_cuenta') !== $request->input('numero_cuenta_confirmacion')) {
            return response()->json(['message' => 'El número de cuenta y su confirmación no coinciden.'], 422);
        }

        // RN-05: valor de la transacción y confirmación deben coincidir.
        $valor = (string) $request->input('valor_transaccion');
        $valorConfirmacion = (string) $request->input('valor_transaccion_confirmacion');
        if (bccomp($valor, $valorConfirmacion, 2) !== 0) {
            return response()->json(['message' => 'El valor de la transacción y su confirmación no coinciden.'], 422);
        }

        // RN-06: el valor debe ser igual al valor aprobado para desembolso —
        // no se permiten transferencias parciales en esta funcionalidad.
        if (bccomp($valor, (string) $credito->monto, 2) !== 0) {
            return response()->json(['message' => 'El valor de la transacción debe ser igual al valor aprobado para desembolso.'], 422);
        }

        $entidadBancaria = EntidadBancaria::findOrFail($request->input('entidad_bancaria_id'));

        $certificado = $request->file('certificado_bancario');
        $comprobante = $request->file('comprobante_transferencia');
        $rutaCertificado = $certificado->store('credito_documentos/' . $credito->id . '/transferencia', 'public');
        $rutaComprobante = $comprobante->store('credito_documentos/' . $credito->id . '/transferencia', 'public');

        $numeroCuenta = (string) $request->input('numero_cuenta');
        $cuentaEnmascarada = str_repeat('*', max(strlen($numeroCuenta) - 4, 0)) . substr($numeroCuenta, -4);

        $transferencia = [
            'titular_cuenta' => $request->input('titular_cuenta'),
            'tipo_documento_titular_id' => (int) $request->input('tipo_documento_titular_id'),
            'numero_documento_titular' => $request->input('numero_documento_titular'),
            'entidad_bancaria_id' => $entidadBancaria->id,
            'entidad_bancaria_nombre' => $entidadBancaria->nombre,
            'tipo_cuenta' => $request->input('tipo_cuenta'),
            'numero_cuenta' => $numeroCuenta,
            'cuenta_enmascarada' => $cuentaEnmascarada,
            'moneda_cuenta' => $request->input('moneda_cuenta'),
            'correo_notificacion_pago' => $request->input('correo_notificacion_pago'),
            'certificado_bancario' => $rutaCertificado,
            'observaciones_bancarias' => $request->input('observaciones_bancarias'),
            'fecha_transferencia' => $request->input('fecha_transferencia'),
            'hora_transferencia' => $request->input('hora_transferencia'),
            'valor_transaccion' => $valor,
            'numero_transaccion' => $request->input('numero_transaccion'),
            'comprobante_transferencia' => $rutaComprobante,
            'declaracion_validacion_bancaria' => true,
            'cliente_nombre' => $this->nombreClienteParaCorreo($credito),
            'registrado_por_id' => $user->id,
            'registrado_por_nombre' => $user->name,
            'registrado_en' => now()->toIso8601String(),
        ];

        // Legacy compat: mismo criterio que desembolsoIngreso() con
        // 'desembolso_egreso' — satisface el gate de CreditoOrdinarioController
        // (`!empty($documentos['comprobante_transferencia'])`) sin duplicar
        // esa lógica acá.
        $documentosRaw = $credito->documentos_raw ?? [];
        $documentosRaw['comprobante_transferencia'] = $rutaComprobante;
        $credito->documentos = $documentosRaw;

        $estadoAnterior = $credito->estado;
        $credito->transferencia_bancaria = $transferencia;
        $credito->numero_transaccion_bancaria = $transferencia['numero_transaccion'];
        $credito->estado = 'completado';

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $credito->estado,
            'comentario' => 'Transferencia bancaria registrada por Tesorería (transacción '
                . $transferencia['numero_transaccion'] . ', cuenta ' . $cuentaEnmascarada
                . ' — ' . $entidadBancaria->nombre . '). ¡Proceso BPMN Completado con Éxito!',
        ];
        $credito->historial_estados = $historial;
        $credito->solicitud_gestionada = true;
        $credito->fecha_gestion = now();
        $credito->save();

        // FA-04: un fallo de envío no revierte la transferencia, que ya
        // quedó guardada (mismo criterio best-effort que notificarPorRol()).
        try {
            Mail::to($request->input('correo_notificacion_pago'))
                ->send(new TransferenciaRealizadaClienteMail($credito, $transferencia, $cuentaEnmascarada));
        } catch (Throwable $e) {
            // Notificación informativa — no revierte la transición.
        }

        $urlIngresoGerente = $this->urlIngresoSistema('/gestion-creditos');
        $this->notificarPorRol('gerente', new TransferenciaRegistradaInternaMail($credito, $transferencia, $urlIngresoGerente));
        $this->notificarPorRol('coordinador_comercial', new TransferenciaRegistradaInternaMail($credito, $transferencia, $urlIngresoGerente));

        return response()->json([
            'message' => 'Transferencia bancaria registrada. El cliente y los usuarios de Gerencia/Coordinación Comercial fueron notificados.',
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
     * SCRUM-236: todos los documentos que el Comité pidió como condición
     * "pendiente" quedaron aprobados — el crédito vuelve a
     * `comite_evaluacion` (mismo patrón de reseteo de
     * `solicitud_gestionada`/`fecha_gestion` que
     * ActaComiteController::sincronizarCreditosOrdinarios()) para que quede
     * disponible de nuevo en el pool de créditos elegibles de una Acta de
     * Comité (`ActaComiteController::creditosElegibles()`, que solo mira
     * `estado === 'comite_evaluacion'`). Antes de este fix saltaba directo
     * a `aprobada_garantias`/`comite_aprobado`, como si el Comité ya lo
     * hubiera aprobado sin haber vuelto a evaluarlo — error reportado en el
     * ticket. `resultado_origen` se limpia a null porque el crédito entra
     * a una evaluación de Comité nueva, sin resultado todavía (ver
     * feedback_resultado_origen_no_se_limpia). Defensivo: solo si sigue en
     * `pendiente_comite` (si ya se movió por otro camino, no lo tocamos de
     * nuevo).
     */
    private function retomarComiteSiAplica(CreditoOrdinario $credito, DocumentRequest $documentRequest, $user): void
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
            'comentario' => 'Documentación reenviada por el cliente aprobada en su totalidad. Vuelve a estar disponible para una nueva Acta de Comité.',
        ];

        $credito->estado = 'comite_evaluacion';
        $credito->resultado_origen = null;
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
    private function crearSolicitudDocumentos(CreditoOrdinario $credito, int $presetId, int $creadoPorId, ?string $etapa = null): void
    {
        $preset = DocumentPreset::findOrFail($presetId);
        $requirementIds = $preset->requirements()->pluck('document_requirements.id')->toArray();

        if (empty($requirementIds)) {
            return;
        }

        // SCRUM-199/223 (2026-08-18, 2ª vuelta): el guard anterior de "misma
        // request, mismo preset" (comparar sets de document_requirement_id)
        // seguía no-opeando en silencio cuando dos presets distintos (o el
        // mismo reenviado) terminaban apuntando al mismo set de requirements
        // — el catálogo de document_requirements es chico y se reutiliza
        // entre presets, así que "mismo contenido" no es una señal confiable
        // de "misma solicitud". En vez de adivinar duplicados por contenido,
        // cada llamada legítima del Coordinador (acción explícita, no un
        // reintento automático) cierra cualquier request 'pendiente' previa
        // de este crédito y crea una nueva siempre — la vieja deja de
        // aparecerle al cliente como pendiente de cargar.
        DocumentRequest::where('cliente_id', $credito->cliente_id)
            ->where('solicitud_credito_id', $credito->solicitud_credito_id)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'cancelado']);

        $documentRequest = DocumentRequest::create([
            'cliente_id' => $credito->cliente_id,
            'creado_por' => $creadoPorId,
            'solicitud_credito_id' => $credito->solicitud_credito_id,
            'estado' => 'pendiente',
            'etapa' => $etapa,
            'preset_id' => $preset->id,
            'preset_nombre' => $preset->nombre,
        ]);

        foreach ($requirementIds as $reqId) {
            DocumentRequestItem::create([
                'document_request_id' => $documentRequest->id,
                'document_requirement_id' => $reqId,
                'estado' => 'pendiente',
            ]);
        }
    }

    /**
     * SCRUM-211/215/219/224: acceso de módulo (bandeja/tarjetas/detalle)
     * amplía a Gerente, Operativo y Tesorería — la restricción fina de QUÉ
     * pueden ver/hacer dentro del módulo vive en ROLES_POR_CLAVE
     * (visibilidad) y en autorizarAccionGerencial()/autorizarAccionOperativa()/
     * autorizarAccionTesoreria() (las acciones de escritura), no acá.
     */
    private function autorizarRol(string $activeRole): void
    {
        if (in_array($activeRole, ['superadmin', 'coordinador_comercial', 'gerente', 'operativo', 'tesoreria'], true)) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para acceder a Gestión de Créditos.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    private function autorizarAccionGerencial(string $activeRole): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'gerente') {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para realizar esta acción.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    private function autorizarAccionOperativa(string $activeRole): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'operativo') {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para realizar esta acción.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    private function autorizarAccionTesoreria(string $activeRole): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'tesoreria') {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para realizar esta acción.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    /** Claves (RESULTADOS ∪ ESTADOS_SIMPLES) visibles para un rol — ver
     * docblock de ROLES_POR_CLAVE. superadmin ve todas. */
    private function clavesVisiblesParaRol(string $role): array
    {
        if ($role === 'superadmin') {
            return array_keys(self::ROLES_POR_CLAVE);
        }

        return array_keys(array_filter(
            self::ROLES_POR_CLAVE,
            fn ($roles) => in_array($role, $roles, true)
        ));
    }

    /** Aplica el where de estado(+resultado_origen) de una clave dada a una
     * query — extraído de index() para reusarlo también en tarjetas() y en
     * el scoping por rol. */
    private function aplicarCondicionClave($query, string $clave): void
    {
        if (isset(self::RESULTADOS[$clave])) {
            $resultado = self::RESULTADOS[$clave];
            $query->where('estado', $resultado['estado'])->where('resultado_origen', $resultado['origen']);
            return;
        }

        if (isset(self::ESTADOS_SIMPLES[$clave])) {
            $query->where('estado', self::ESTADOS_SIMPLES[$clave]);
        }
    }

    /** Inversa de aplicarCondicionClave(): a qué clave pertenece un crédito
     * ya cargado (para el scoping de visibilidad en show()). */
    private function claveDelCredito(CreditoOrdinario $credito): ?string
    {
        $clave = $this->resolverClaveResultado($credito);
        if ($clave) {
            return $clave;
        }

        foreach (self::ESTADOS_SIMPLES as $clave => $estado) {
            if ($credito->estado === $estado) {
                return $clave;
            }
        }

        return null;
    }

    /** SCRUM-211/215/219: envío best-effort a todos los usuarios activos
     * con un rol dado — mismo idioma que ListasRestrictivasSarlaftController
     * (User::whereJsonContains) e InternalDocumentController. Un fallo de
     * envío no revierte la transición ya guardada (igual criterio que
     * guardarFormalizacionGarantias()).
     *
     * SCRUM-294/299 (§"Registro de..."/"Auditoría"): $credito y
     * $tipoNotificacion son opcionales para no tocar los call sites que ya
     * estaban en producción (SCRUM-219/224) — cuando se pasan, cada intento
     * (enviado, fallido, o sin destinatarios activos) queda trazado en
     * ActivityLog, mismo criterio de auditoría que notificar()
     * (gestion_credito_notificacion_enviada/_fallida). */
    private function notificarPorRol(string $role, $mailable, ?CreditoOrdinario $credito = null, ?string $tipoNotificacion = null): void
    {
        $destinatarios = User::whereJsonContains('roles', $role)->pluck('email')->filter()->all();

        if (empty($destinatarios)) {
            if ($credito && $tipoNotificacion) {
                app(ActivityLogService::class)->registrar(
                    'notificacion_rol_sin_destinatarios',
                    "No hay usuarios activos con rol {$role} para la notificación {$tipoNotificacion} de la solicitud {$credito->numero_solicitud}.",
                    Auth::user(),
                    $credito,
                    ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $role]
                );
            }

            return;
        }

        try {
            Mail::to($destinatarios)->send($mailable);

            if ($credito && $tipoNotificacion) {
                app(ActivityLogService::class)->registrar(
                    'notificacion_rol_enviada',
                    "Notificación {$tipoNotificacion} enviada a rol {$role} para la solicitud {$credito->numero_solicitud}.",
                    Auth::user(),
                    $credito,
                    ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $role, 'destinatarios' => $destinatarios]
                );
            }
        } catch (Throwable $e) {
            // Notificación informativa — no revierte la transición.
            if ($credito && $tipoNotificacion) {
                app(ActivityLogService::class)->registrar(
                    'notificacion_rol_fallida',
                    "Falló el envío de la notificación {$tipoNotificacion} a rol {$role} para la solicitud {$credito->numero_solicitud}.",
                    Auth::user(),
                    $credito,
                    ['tipo_notificacion' => $tipoNotificacion, 'rol_destino' => $role, 'destinatarios' => $destinatarios, 'error' => $e->getMessage()]
                );
            }
        }
    }

    /** URL del botón "Ingresar al sistema" de los correos de SCRUM-211/215/
     * 219 (§10.3 del ticket 219: base HTTPS configurable por ambiente +
     * ruta de retorno tras el login). */
    private function urlIngresoSistema(string $returnPath): string
    {
        $base = rtrim((string) ConfiguracionService::get(
            'URL_BASE_SISTEMA_GESTION_LIQUIDEZ',
            env('FRONTEND_URL', config('app.url'))
        ), '/');

        return $base . '/login?returnTo=' . urlencode($returnPath);
    }
}
