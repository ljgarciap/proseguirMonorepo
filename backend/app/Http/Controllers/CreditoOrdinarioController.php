<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\ClientUpload;
use App\Models\CreditoOrdinario;
use App\Models\DocumentRequestItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditoOrdinarioController extends Controller
{
    use ResolvesActiveRole;

    /**
     * Listar las solicitudes de crédito ordinario
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->resolveActiveRole($request);
        
        $query = CreditoOrdinario::with(['cliente', 'informeTecnico', 'analisisFinanciero', 'solicitudCredito.tipoCredito', 'solicitudCredito.documentRequest.items.requirement', 'solicitudCredito.documentRequest.items.upload', 'solicitudCredito.garantiasDocumentRequest.items.requirement', 'solicitudCredito.garantiasDocumentRequest.items.upload']);

        // Si el rol es cliente, solo puede ver sus propias solicitudes
        if ($activeRole === 'cliente') {
            $query->where('cliente_id', $user->id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Crear una nueva solicitud de crédito ordinario
     */
    public function store(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric|min:1',
            'plazo_meses' => 'required|integer|min:1',
            'cliente_id' => 'nullable|exists:users,id'
        ]);

        $user = Auth::user();
        $activeRole = $this->resolveActiveRole($request);

        // Determinar el cliente
        $clienteId = $request->cliente_id;
        if ($activeRole === 'cliente') {
            $clienteId = $user->id;
        } elseif (!$clienteId) {
            // Si lo crea un comercial u otro rol, y no especifica cliente, lo asociamos a un cliente de prueba
            $clientePrueba = User::whereJsonContains('roles', 'cliente')->first();
            $clienteId = $clientePrueba ? $clientePrueba->id : $user->id;
        }

        $credito = CreditoOrdinario::iniciar(
            clienteId:   $clienteId,
            monto:       $request->monto,
            plazoMeses:  $request->plazo_meses,
            usuario:     $user->name,
            rol:         $activeRole,
            comentario:  'Solicitud de crédito ordinario registrada e iniciada en revisión documental.'
        );

        return response()->json($credito->load('cliente'), 201);
    }

    /**
     * Obtener el detalle de una solicitud
     */
    public function show($id)
    {
        $credito = CreditoOrdinario::with(['cliente', 'informeTecnico', 'analisisFinanciero', 'solicitudCredito.tipoCredito', 'solicitudCredito.documentRequest.items.requirement', 'solicitudCredito.documentRequest.items.upload', 'solicitudCredito.garantiasDocumentRequest.items.requirement', 'solicitudCredito.garantiasDocumentRequest.items.upload'])->findOrFail($id);
        return response()->json($credito);
    }

    /**
     * Claves de documentos que debe satisfacer la Etapa 1 (Registro e
     * Identificación) para este crédito. Si la SolicitudCredito que lo
     * originó tiene una Solicitud de Documentos (DocumentRequest) ligada a
     * un preset, se usa un ítem por cada documento del preset
     * (SCRUM-146). Si no (créditos legacy sin preset), se mantiene la
     * lista fija original.
     */
    private function etapa1DocumentKeys(CreditoOrdinario $credito): array
    {
        $items = $credito->solicitudCredito?->documentRequest?->items;

        if ($items && $items->count() > 0) {
            return $items->map(fn ($item) => 'req_item_' . $item->id)->all();
        }

        return ['formulario_solicitud', 'documentos_identidad', 'estados_financieros', 'certificados_laborales'];
    }

    /**
     * Determina si un documento de Etapa 1 (clave 'req_item_{id}' del preset,
     * o clave fija legacy) ya está satisfecho. SCRUM-176: hay dos caminos de
     * carga válidos que antes no se hablaban entre sí —
     * a) directo en credito.documentos, vía el widget "Subir" dentro de
     *    Crédito Ordinario / Mis Créditos (este mismo transition()), o
     * b) el portal "Mis Cargas" (ClientUploadController), que es el nav más
     *    visible del cliente y el que nombra el documento exacto requerido,
     *    pero solo deja rastro en DocumentRequestItem.estado — nunca en
     *    documentos_raw. Antes solo se miraba (a), así que una carga hecha
     *    por (b) nunca destrababa la etapa: el crédito quedaba atascado en
     *    'Faltan documentos obligatorios' para siempre, sin ningún error
     *    visible ni para el cliente (ve "Carga Exitosa") ni para el
     *    Coordinador. No se exige que el item llegue a 'aprobado' (eso
     *    requeriría el circuito completo de validar+aprobar de Operativo,
     *    ajeno a esta pantalla) — basta con que el cliente ya haya subido
     *    algo ('subido' en adelante, distinto de 'pendiente'/'rechazado').
     */
    // SCRUM-256: para claves 'req_item_{id}' el DocumentRequestItem.estado
    // es la fuente de verdad y se chequea PRIMERO — antes se miraba
    // documentos_raw[key] primero, así que un ítem marcado 'rechazado' por
    // el Coordinador (corrección solicitada) seguía contando como
    // "satisfecho" mientras el archivo viejo (ya rechazado) siguiera en el
    // arreglo, dejando aprobar la etapa sin que el cliente corrigiera nada,
    // y bloqueando el re-cargo del documento corregido (ver guard nuevo en
    // transition()). El arreglo documentos_raw solo sigue siendo la fuente
    // para las 4 claves legacy sin preset, que no tienen DocumentRequestItem.
    private function etapa1KeySatisfecha(string $key, array $documentos, CreditoOrdinario $credito): bool
    {
        if (str_starts_with($key, 'req_item_')) {
            $itemId = (int) substr($key, strlen('req_item_'));
            $item = $credito->solicitudCredito?->documentRequest?->items?->firstWhere('id', $itemId);
            return $item && !in_array($item->estado, ['pendiente', 'rechazado'], true);
        }

        $valor = $documentos[$key] ?? null;
        return is_array($valor) ? count($valor) > 0 : !empty($valor);
    }

    /**
     * Procesar transición de estado y carga de archivos según el rol activo
     */
    public function transition(Request $request, $id)
    {
        $credito = CreditoOrdinario::with('solicitudCredito.documentRequest.items')->findOrFail($id);
        $user = Auth::user();
        $activeRole = $this->resolveActiveRole($request);

        $request->validate([
            'accion'          => 'required|string|in:aprobar,rechazar,completar,subir_archivo,devolver',
            'comentario'      => 'nullable|string',
            'archivo'         => 'nullable|file|mimes:pdf|max:102400',
            'archivos'        => 'nullable|array',
            'archivos.*'      => 'file|mimes:pdf|max:102400',
            'campo_documento' => 'nullable|string',
        ]);

        $accion = $request->accion;
        $comentario = $request->comentario ?? 'Acción ejecutada en el flujo de crédito.';
        // SCRUM-258: el correo debe incluir el comentario del Coordinador
        // "sin alteraciones" (RF-04) — $comentario se muta más abajo con
        // prefijos de auditoría para el historial_estados, así que se
        // conserva el original acá para las notificaciones.
        $comentarioOriginal = $comentario;
        $estadoActual = $credito->estado;
        $estadoNuevo = $estadoActual;
        // SCRUM-258: qué notificar tras persistir la transición (ver bloque
        // al final del método, después de $credito->save()). Se setea desde
        // las ramas de Etapa 1 (aprobar/completar/rechazar) más abajo.
        $notificacionValidacion = null;

        // Bypassear validaciones estrictas de rol si es superadmin (para facilitar pruebas)
        $isAuthorized = ($activeRole === 'superadmin');

        // SCRUM-178: 'comite_evaluacion' ya no se transiciona manualmente
        // desde acá — la única salida de ese estado es registrar el Acta de
        // Comité (ActaComiteController::registrar()), que decide
        // aprobado/rechazado/pendiente por solicitud. Cortamos temprano con
        // un mensaje explícito en vez de dejar caer en el mapa de roles
        // genérico de abajo (que, sin una entrada para este estado, dejaría
        // pasar a cualquier rol).
        if ($estadoActual === 'comite_evaluacion' && !$isAuthorized) {
            return response()->json([
                'message' => 'Esta transición ahora se ejecuta desde Actas de Comité al registrar el acta.',
            ], 422);
        }

        // Mapa de roles autorizados por estado para transiciones
        $rolesAutorizados = [
            'validacion_documental_constructor' => ['coordinador_comercial', 'cliente'],
            'completar_solicitud_constructor' => ['cliente'],
            'revision_documental' => ['coordinador_comercial', 'cliente'],
            'completar_solicitud' => ['cliente'],
            // SCRUM-183: 'aprobacion_presentacion' se retiró — Análisis
            // Financiero confirmado pasa directo a comite_evaluacion (ver
            // AnalisisFinancieroController::confirmar()). El único rol que
            // sigue teniendo algo que hacer en 'pendiente_analisis_financiero'
            // vía este endpoint es 'rechazar' (genérico, caso else de abajo).
            'pendiente_analisis_financiero' => ['coordinador_comercial'],
            // 'comite_evaluacion' ya NO tiene rol autorizado acá: SCRUM-178
            // retiró el botón manual de aprobar/rechazar — la única salida
            // de ese estado es ActaComiteController::registrar().
            'formalizacion_garantias' => ['coordinador_comercial', 'cliente', 'operativo'],
            // SCRUM-292: sin esta entrada, isset() más abajo era false para
            // este estado y CUALQUIER rol activo podía subir archivos sin
            // restricción — el gap real no era solo de UI.
            'aprobada_garantias' => ['coordinador_comercial', 'cliente'],
            'aprobacion_registro_cyf' => ['coordinador_comercial', 'gerente'],
            'desembolso_ingreso' => ['operativo'],
            'desembolso_aprobacion' => ['gerente'],
            'ejecucion_transferencia' => ['tesoreria'],
        ];

        // Validar que el rol activo tiene permisos en la etapa actual
        if (!$isAuthorized && isset($rolesAutorizados[$estadoActual])) {
            if (!in_array($activeRole, $rolesAutorizados[$estadoActual])) {
                return response()->json([
                    'message' => 'No tienes autorización para realizar acciones en esta etapa.',
                    'rol_activo' => $activeRole,
                    'roles_requeridos' => $rolesAutorizados[$estadoActual]
                ], 403);
            }
        }

        // SCRUM-292: 'aprobada_garantias' se fija apenas el Comité aprueba
        // (ActaComiteController), ANTES de que el Coordinador Comercial
        // gestione la solicitud y elija el preset (GestionCreditoController
        // ::notificar(), que recién ahí crea el DocumentRequest 'garantias'
        // y marca solicitud_gestionada = true). Sin esta guarda, el cliente
        // podía cargar documentos contra claves fijas que no correspondían
        // a ningún preset real todavía elegido.
        if (!$isAuthorized && $accion === 'subir_archivo' && $estadoActual === 'aprobada_garantias' && !$credito->solicitud_gestionada) {
            return response()->json([
                'message' => 'La gestión de garantías aún no ha sido completada por el Coordinador Comercial. La carga de documentos se habilita una vez enviada esa gestión.',
            ], 422);
        }

        // documentos_raw (no documentos): esto se relee y se reescribe
        // completo más abajo; si leyéramos la versión ya resuelta a URL
        // absoluta, cada transición hornearía de nuevo el APP_URL vigente
        // en todos los campos, no solo en el que cambia (SCRUM-148).
        $documentos = $credito->documentos_raw ?? [];

        // SCRUM-256: no permitir volver a cargar un documento de Etapa 1 que
        // ya fue cargado — antes el widget "Subir" quedaba habilitado
        // siempre y cada envío nuevo se apilaba sobre lo ya cargado en
        // documentos_raw[campo], produciendo entradas duplicadas visibles en
        // el expediente (defensa en profundidad: el frontend ya oculta el
        // botón vía puedeSubirDocumento(), esto cubre el endpoint directo).
        // Se excluye el caso 'rechazado' del DocumentRequestItem —
        // etapa1KeySatisfecha() ya lo trata como "no satisfecho" para
        // permitir la corrección pedida por el Coordinador.
        if ($request->filled('campo_documento') && ($request->hasFile('archivos') || $request->hasFile('archivo'))) {
            $campoDocumentoIntento = $request->campo_documento;
            if (in_array($campoDocumentoIntento, $this->etapa1DocumentKeys($credito), true)
                && $this->etapa1KeySatisfecha($campoDocumentoIntento, $documentos, $credito)) {
                return response()->json([
                    'message' => 'Este documento ya fue cargado. Si necesitas reemplazarlo, contacta al Coordinador Comercial.',
                ], 422);
            }
        }

        // 1. Manejo de Carga de Archivos
        // Documentos de Etapa 1 dirigidos por preset (claves 'req_item_{id}',
        // SCRUM-146) admiten varios archivos por documento; el resto del
        // flujo BPMN sigue aceptando un único archivo por campo.
        if ($request->hasFile('archivos') && $request->filled('campo_documento')) {
            $campoDoc = $request->campo_documento;
            $existing = $documentos[$campoDoc] ?? [];
            if (!is_array($existing)) {
                $existing = $existing ? [$existing] : [];
            }

            // Si el campo corresponde a un documento de Etapa 1 dirigido por
            // preset ('req_item_{id}'), sincronizamos también el
            // DocumentRequestItem asociado: sin esto, las pantallas de
            // "Solicitudes Documentos" y "sube tus documentos" del cliente
            // seguían mostrando el documento como pendiente aunque ya se
            // hubiera cargado desde Crédito Ordinario (SCRUM-146).
            $requestItemId = str_starts_with($campoDoc, 'req_item_')
                ? (int) substr($campoDoc, strlen('req_item_'))
                : null;

            // SCRUM-256 (comentario Juan Andrés, 2026-08-26): valida ANTES de
            // guardar nada — un archivo duplicado en un lote de varios no
            // debe dejar los demás ya persistidos a medias.
            if ($requestItemId) {
                $requestItemActual = DocumentRequestItem::find($requestItemId);
                if ($requestItemActual) {
                    $guard = new \App\Services\DuplicateDocumentGuard();
                    foreach ($request->file('archivos') as $archivoAValidar) {
                        $mensajeDuplicado = $guard->archivoDuplicadoEnOtroDocumento($requestItemActual, $archivoAValidar);
                        if ($mensajeDuplicado) {
                            return response()->json(['message' => $mensajeDuplicado], 422);
                        }
                    }
                }
            }

            $nombres = [];
            foreach ($request->file('archivos') as $file) {
                $fileName   = $file->getClientOriginalName();
                $path       = $file->storeAs('credito_documentos/' . $credito->id, $fileName, 'public');
                // Se guarda la ruta relativa, no la URL absoluta: el modelo
                // la resuelve al leer con el APP_URL vigente (SCRUM-148).
                $existing[] = $path;
                $nombres[]  = $fileName;

                if ($requestItemId) {
                    $upload = ClientUpload::create([
                        'user_id'       => $user->id,
                        'upload_role'   => $activeRole,
                        'filename'      => $path,
                        'original_name' => $fileName,
                        'status'        => 'pendiente',
                        'ocr_status'    => 'exitoso',
                        'ocr_message'   => 'No requiere procesamiento OCR',
                    ]);

                    DocumentRequestItem::where('id', $requestItemId)->update([
                        'client_upload_id' => $upload->id,
                        'estado'           => 'subido',
                        'observaciones'    => null,
                    ]);

                    // SCRUM-252: "Mis Créditos" es el otro origen nombrado en
                    // la spec (RF-02) — mismo chequeo de completitud que
                    // ClientUploadController::store() para "Mis Cargas".
                    $requestItem = DocumentRequestItem::with('request')->find($requestItemId);
                    if ($requestItem && $requestItem->request) {
                        (new \App\Services\DocumentRequestNotificationService())
                            ->notificarCargaCompletaSiAplica($requestItem->request, 'Mis Créditos');

                        // SCRUM-293: este mismo punto (carga directa desde
                        // Crédito Ordinario, habilitada para el rol cliente
                        // por SCRUM-292) nunca disparaba el avance de
                        // 'aprobada_garantias' a 'pendiente_formalizacion_
                        // garantias' — solo ClientUploadController lo hacía.
                        // Un crédito cargado ÍNTEGRAMENTE desde esta pantalla
                        // se quedaba trabado sin notificar a Operativo ni
                        // aparecer en su bandeja.
                        (new \App\Services\GarantiasFormalizacionService())
                            ->habilitarSiAplica($requestItem->request);
                    }
                }
            }

            $documentos[$campoDoc] = $existing;
            $credito->documentos   = $documentos;
            $credito->save();

            $comentario .= ' (' . count($nombres) . " archivo(s) cargado(s) en '$campoDoc': " . implode(', ', $nombres) . ').';
        } elseif ($request->hasFile('archivo') && $request->filled('campo_documento')) {
            $file     = $request->file('archivo');
            $fileName = $file->getClientOriginalName();
            $path     = $file->storeAs('credito_documentos/' . $credito->id, $fileName, 'public');

            $campoDoc              = $request->campo_documento;
            // Ruta relativa; el modelo resuelve la URL al leer (SCRUM-148).
            $documentos[$campoDoc] = $path;
            $credito->documentos   = $documentos;
            $credito->save();

            $comentario .= " (Archivo '$fileName' cargado correctamente en '$campoDoc').";
        }

        // 2. Lógica de Máquina de Estados BPMN
        if ($accion === 'rechazar') {
            // SCRUM-183: el caso 'aprobacion_presentacion' se retiró junto
            // con el estado — ya no existe ese paso.
            if ($estadoActual === 'comite_evaluacion') {
                // SCRUM-178: solo alcanzable por superadmin (el guard de más
                // arriba bloquea a comite_credito). Se conserva como vía de
                // escape manual; el camino normal es Actas de Comité, que
                // además setea resultado_origen/gestión — este atajo no lo
                // hace.
                $estadoNuevo = 'rechazado';
                $comentario = 'Crédito rechazado formalmente por el Comité de Crédito. ' . $comentario;
            } elseif ($estadoActual === 'formalizacion_garantias') {
                // If Dirección Administrativa rejects/returns garantías, keep state but clear the signed file
                $estadoNuevo = 'formalizacion_garantias';
                $documentos['garantias_firmadas'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Garantías rechazadas por Dirección Administrativa. Deben ser corregidas y firmadas nuevamente. ' . $comentario;
            } elseif ($estadoActual === 'aprobacion_registro_cyf') {
                // If Gerente rejects CYF registration, keep state but clear the file so it can be re-registered
                $estadoNuevo = 'aprobacion_registro_cyf';
                $documentos['registro_cyf'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Registro de Crédito en CYF rechazado por Gerencia. Debe registrarse nuevamente. ' . $comentario;
            } elseif ($estadoActual === 'desembolso_aprobacion') {
                // If Gerente rejects desembolso approval, return to Ingreso Desembolso in CYF
                $estadoNuevo = 'desembolso_ingreso';
                $documentos['desembolso_egreso'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Aprobación de desembolso rechazada por Gerencia. Retorna a Dirección Administrativa. ' . $comentario;
            } else {
                // SCRUM-258 (5.3): solo la negación de Etapa 1 dispara el
                // correo al cliente de este ticket — las demás etapas que
                // caen acá (o las que ya tienen su propia rama arriba) no
                // están en el alcance de 258.
                if (in_array($estadoActual, ['revision_documental', 'validacion_documental_constructor'], true)) {
                    $notificacionValidacion = 'rechazar';
                }
                $estadoNuevo = 'rechazado';
                $comentario = 'Crédito rechazado en etapa: ' . $estadoActual . '. ' . $comentario;
            }
        } elseif ($accion === 'devolver') {
            if ($estadoActual === 'comite_evaluacion') {
                // SCRUM-178: solo alcanzable por superadmin, ver nota en la rama 'rechazar'.
                // SCRUM-183: ya no existe 'aprobacion_presentacion' — vuelve
                // directo a análisis financiero para corrección.
                $estadoNuevo = 'pendiente_analisis_financiero';
                $comentario = 'Crédito devuelto por el Comité para corrección del análisis financiero. ' . $comentario;
            } elseif ($estadoActual === 'formalizacion_garantias') {
                $estadoNuevo = 'formalizacion_garantias';
                $documentos['garantias_firmadas'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Garantías devueltas por Dirección Administrativa para corrección. ' . $comentario;
            } elseif ($estadoActual === 'aprobacion_registro_cyf') {
                $estadoNuevo = 'aprobacion_registro_cyf';
                $documentos['registro_cyf'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Registro de CYF devuelto para corrección. ' . $comentario;
            } elseif ($estadoActual === 'desembolso_aprobacion') {
                $estadoNuevo = 'desembolso_ingreso';
                $documentos['desembolso_egreso'] = null;
                $credito->documentos = $documentos;
                $comentario = 'Desembolso devuelto por Gerencia. Retorna a Dirección Administrativa para corregir egreso. ' . $comentario;
            } else {
                return response()->json(['message' => 'La acción devolver no está soportada en esta etapa.'], 422);
            }
        } elseif ($accion === 'completar') {
            if ($estadoActual === 'revision_documental') {
                $estadoNuevo = 'completar_solicitud';
                $comentario = 'Documentación incompleta. Solicitud enviada al cliente para completar. ' . $comentario;
                $notificacionValidacion = 'completar'; // SCRUM-258 (5.2)
            } elseif ($estadoActual === 'validacion_documental_constructor') {
                $estadoNuevo = 'completar_solicitud_constructor';
                $comentario = 'Documentación incompleta del expediente inicial. Solicitud enviada al cliente para completar. ' . $comentario;
                $notificacionValidacion = 'completar'; // SCRUM-258 (5.2)
            }
        } elseif ($accion === 'aprobar' || $accion === 'subir_archivo') {
            switch ($estadoActual) {
                case 'validacion_documental_constructor':
                    // SCRUM-151: Coordinador Comercial revisa y aprueba el
                    // expediente inicial de Constructor en esta misma pantalla,
                    // igual que revision_documental en Ordinario. Al aprobar,
                    // pasa directo a Informe Técnico (no a SARLAFT, que en
                    // Constructor corre después del Informe Técnico).
                    if ($accion === 'aprobar') {
                        $hasEtapa1 = collect($this->etapa1DocumentKeys($credito))->every(
                            fn ($key) => $this->etapa1KeySatisfecha($key, $documentos, $credito)
                        );

                        if ($hasEtapa1) {
                            $estadoNuevo = 'informe_tecnico_ingeniero';
                            $comentario = 'Documentación revisada y aprobada. Bandeja de Informe Técnico habilitada para el Ingeniero.';
                            $notificacionValidacion = 'aprobar_constructor'; // SCRUM-258 (RF-05/RF-06)
                        } else {
                            $comentario = 'Faltan documentos obligatorios del expediente inicial. No se puede aprobar todavía.';
                        }
                    }
                    break;

                case 'revision_documental':
                    // La carga de un solo soporte (subir_archivo) no debe cerrar la
                    // etapa: solo el Coordinador Comercial, con 'aprobar' explícito,
                    // decide que el expediente inicial está completo.
                    if ($accion === 'aprobar') {
                        $hasEtapa1 = collect($this->etapa1DocumentKeys($credito))->every(
                            fn ($key) => $this->etapa1KeySatisfecha($key, $documentos, $credito)
                        );

                        if ($hasEtapa1) {
                            $estadoNuevo = 'sarlaft_control_interno';
                            $comentario = 'Documentación revisada y aprobada. Pasa a validación de Listas Restrictivas y SARLAFT.';
                            $notificacionValidacion = 'aprobar_ordinario'; // SCRUM-258 (RF-05/RF-07)
                        } else {
                            $comentario = 'Faltan documentos obligatorios del expediente inicial. No se puede aprobar todavía.';
                        }
                    }
                    break;

                case 'completar_solicitud':
                    // SCRUM-252 (feedback QA 2026-08-26): antes el cliente
                    // podía "Enviar Solicitud Completada" sin haber cargado
                    // ningún documento del preset — esta rama nunca chequeaba
                    // completitud, a diferencia de la del Coordinador en
                    // 'revision_documental' (arriba), que sí usa
                    // etapa1DocumentKeys()/etapa1KeySatisfecha().
                    if ($accion === 'aprobar') {
                        $hasEtapa1 = collect($this->etapa1DocumentKeys($credito))->every(
                            fn ($key) => $this->etapa1KeySatisfecha($key, $documentos, $credito)
                        );

                        if (!$hasEtapa1) {
                            return response()->json([
                                'message' => 'Debe cargar todos los documentos requeridos antes de enviar la solicitud completada.',
                            ], 422);
                        }

                        $estadoNuevo = 'revision_documental';
                        $comentario = 'El cliente completó la solicitud. Retorna a revisión documental.';
                    }
                    break;

                case 'completar_solicitud_constructor':
                    // SCRUM-252: mismo guard que 'completar_solicitud' — ver
                    // comentario arriba.
                    if ($accion === 'aprobar') {
                        $hasEtapa1 = collect($this->etapa1DocumentKeys($credito))->every(
                            fn ($key) => $this->etapa1KeySatisfecha($key, $documentos, $credito)
                        );

                        if (!$hasEtapa1) {
                            return response()->json([
                                'message' => 'Debe cargar todos los documentos requeridos antes de enviar la solicitud completada.',
                            ], 422);
                        }

                        $estadoNuevo = 'validacion_documental_constructor';
                        $comentario = 'El cliente completó la solicitud. Retorna a validación documental del expediente inicial (Constructor).';
                    }
                    break;

                // SCRUM-183: los casos 'pendiente_analisis_financiero' y
                // 'aprobacion_presentacion' se retiraron de acá — confirmar
                // el Análisis Financiero (módulo dedicado, SCRUM-155) ya
                // transiciona directo a 'comite_evaluacion' por sí solo, ver
                // AnalisisFinancieroController::confirmar(). Ya no hay
                // ninguna acción manual de 'aprobar'/'subir_archivo' que
                // hacer en ese tramo desde acá.

                case 'comite_evaluacion':
                    // SCRUM-178: solo alcanzable por superadmin, ver nota en la rama 'rechazar'.
                    if ($accion === 'aprobar' && !empty($documentos['acta_comite_firmada'])) {
                        $estadoNuevo = 'formalizacion_garantias';
                        $comentario = 'Crédito aprobado por el Comité y Acta firmada cargada. Pasa a formalización de garantías.';
                    } elseif ($accion === 'subir_archivo') {
                        $comentario = 'Acta de Comité firmada cargada correctamente.';
                    } else {
                        $comentario = 'Para aprobar el crédito en Comité, es obligatorio cargar el Acta de Comité firmada.';
                        return response()->json(['message' => $comentario], 422);
                    }
                    break;

                case 'formalizacion_garantias':
                    // Deben estar firmadas por el cliente y revisadas por Dirección Administrativa
                    if (!empty($documentos['garantias_firmadas'])) {
                        // Si el rol es el operativo (Dirección Administrativa) y aprueba
                        if ($activeRole === 'operativo' || $activeRole === 'superadmin') {
                            $estadoNuevo = 'aprobacion_registro_cyf';
                            $comentario = 'Garantías revisadas, aprobadas y registradas por Dirección Administrativa. Pasa a registro en CYF.';
                        } else {
                            $comentario = 'Garantías cargadas. Pendiente revisión y aprobación de Dirección Administrativa.';
                        }
                    }
                    break;

                case 'aprobacion_registro_cyf':
                    // El comercial registra en CYF, luego aprueba gerencia
                    if (!empty($documentos['registro_cyf'])) {
                        if ($activeRole === 'gerente' || $activeRole === 'superadmin') {
                            $estadoNuevo = 'desembolso_ingreso';
                            $comentario = 'Registro de Crédito en CYF aprobado por Gerencia. Pasa a ingreso de operación de desembolso.';
                        } else {
                            $comentario = 'Crédito registrado en CYF por Coordinador Comercial. Esperando aprobación de Gerencia.';
                        }
                    } else {
                        $comentario = 'El Coordinador Comercial debe registrar primero el soporte de CYF antes de proceder.';
                    }
                    break;

                case 'desembolso_ingreso':
                    if ($accion === 'aprobar' && !empty($documentos['desembolso_egreso'])) {
                        $estadoNuevo = 'desembolso_aprobacion';
                        $comentario = 'Operación de desembolso registrada en CYF por Dirección Administrativa. Esperando aprobación de desembolso por Gerencia.';
                    } elseif ($accion === 'subir_archivo') {
                        $comentario = 'Comprobante o instrucción de egreso de CYF cargado correctamente.';
                    } else {
                        $comentario = 'Es necesario cargar el comprobante o instrucción de egreso de CYF para proceder.';
                        return response()->json(['message' => $comentario], 422);
                    }
                    break;

                case 'desembolso_aprobacion':
                    if ($activeRole === 'gerente' || $activeRole === 'superadmin') {
                        $estadoNuevo = 'ejecucion_transferencia';
                        $comentario = 'Operación de desembolso aprobada por Gerencia. Enviada a Tesorería para ejecución.';
                    }
                    break;

                case 'ejecucion_transferencia':
                    if ($accion === 'aprobar' && !empty($documentos['comprobante_transferencia'])) {
                        $estadoNuevo = 'completado';
                        $comentario = 'Transferencia bancaria ejecutada por Tesorería y soporte de pago enviado al cliente. ¡Proceso BPMN Completado con Éxito!';
                    } elseif ($accion === 'subir_archivo') {
                        $comentario = 'Comprobante de transferencia bancaria cargado correctamente.';
                    } else {
                        $comentario = 'Es obligatorio cargar el comprobante de transferencia bancaria para finalizar.';
                        return response()->json(['message' => $comentario], 422);
                    }
                    break;
            }
        }

        // Registrar transición en el historial si cambió de estado o se hizo una acción
        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoActual,
            'estado_nuevo' => $estadoNuevo,
            'comentario' => $comentario
        ];

        $credito->estado = $estadoNuevo;
        $credito->historial_estados = $historial;
        $credito->save();

        // SCRUM-258: se dispara DESPUÉS de persistir, con el comentario
        // original del Coordinador (sin los prefijos de auditoría que
        // $comentario acumuló arriba para el historial).
        if ($notificacionValidacion) {
            (new \App\Services\ValidacionDocumentalNotificationService())
                ->notificar($notificacionValidacion, $credito, $comentarioOriginal);
        }

        return response()->json($credito->load('cliente'));
    }
}
