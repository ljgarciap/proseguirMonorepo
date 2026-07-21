<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\ClientUpload;
use App\Models\CreditoOrdinario;
use App\Models\DocumentRequestItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        
        $query = CreditoOrdinario::with(['cliente', 'solicitudCredito.documentRequest.items.requirement']);

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
        $credito = CreditoOrdinario::with(['cliente', 'solicitudCredito.documentRequest.items.requirement'])->findOrFail($id);
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
        $estadoActual = $credito->estado;
        $estadoNuevo = $estadoActual;

        // Bypassear validaciones estrictas de rol si es superadmin (para facilitar pruebas)
        $isAuthorized = ($activeRole === 'superadmin');

        // Mapa de roles autorizados por estado para transiciones
        $rolesAutorizados = [
            'revision_documental' => ['coordinador_comercial', 'cliente'],
            'completar_solicitud' => ['cliente'],
            'pendiente_analisis_financiero' => ['coordinador_comercial'],
            'aprobacion_presentacion' => ['gerente'],
            'comite_evaluacion' => ['comite_credito'],
            'formalizacion_garantias' => ['coordinador_comercial', 'cliente', 'operativo'],
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

        $documentos = $credito->documentos ?? [];

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

            $nombres = [];
            foreach ($request->file('archivos') as $file) {
                $fileName   = $file->getClientOriginalName();
                $path       = $file->storeAs('credito_documentos/' . $credito->id, $fileName, 'public');
                $existing[] = Storage::disk('public')->url($path);
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
            $documentos[$campoDoc] = Storage::disk('public')->url($path);
            $credito->documentos   = $documentos;
            $credito->save();

            $comentario .= " (Archivo '$fileName' cargado correctamente en '$campoDoc').";
        }

        // 2. Lógica de Máquina de Estados BPMN
        if ($accion === 'rechazar') {
            if ($estadoActual === 'aprobacion_presentacion') {
                $estadoNuevo = 'pendiente_analisis_financiero';
                $comentario = 'Presentación rechazada por Gerencia. Retorna a análisis financiero. ' . $comentario;
            } elseif ($estadoActual === 'comite_evaluacion') {
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
                $estadoNuevo = 'rechazado';
                $comentario = 'Crédito rechazado en etapa: ' . $estadoActual . '. ' . $comentario;
            }
        } elseif ($accion === 'devolver') {
            if ($estadoActual === 'comite_evaluacion') {
                // Returns to Gerente Presentation approval (Revision e Aprobacion de Presentacion)
                $estadoNuevo = 'aprobacion_presentacion';
                $comentario = 'Crédito devuelto por el Comité para corrección de la presentación. ' . $comentario;
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
            }
        } elseif ($accion === 'aprobar' || $accion === 'subir_archivo') {
            switch ($estadoActual) {
                case 'revision_documental':
                    // La carga de un solo soporte (subir_archivo) no debe cerrar la
                    // etapa: solo el Coordinador Comercial, con 'aprobar' explícito,
                    // decide que el expediente inicial está completo.
                    if ($accion === 'aprobar') {
                        $hasEtapa1 = collect($this->etapa1DocumentKeys($credito))->every(function ($key) use ($documentos) {
                            $valor = $documentos[$key] ?? null;
                            return is_array($valor) ? count($valor) > 0 : !empty($valor);
                        });

                        if ($hasEtapa1) {
                            $estadoNuevo = 'sarlaft_control_interno';
                            $comentario = 'Documentación revisada y aprobada. Pasa a validación de Listas Restrictivas y SARLAFT.';
                        } else {
                            $comentario = 'Faltan documentos obligatorios del expediente inicial. No se puede aprobar todavía.';
                        }
                    }
                    break;

                case 'completar_solicitud':
                    if ($accion === 'aprobar') {
                        $estadoNuevo = 'revision_documental';
                        $comentario = 'El cliente completó la solicitud. Retorna a revisión documental.';
                    }
                    break;

                case 'pendiente_analisis_financiero':
                    // El Oficial de Cumplimiento ya emitió concepto favorable (módulo Listas
                    // Restrictivas y SARLAFT, SCRUM-128). Aquí solo falta el Coordinador Comercial.
                    $hasFinancial = !empty($documentos['analisis_financiero']) && !empty($documentos['presentacion_comite']);

                    if ($hasFinancial) {
                        $estadoNuevo = 'aprobacion_presentacion';
                        $comentario = 'Análisis financiero finalizado y documentos cargados por Comercial. Pasa a aprobación de presentación por Gerencia.';
                    } else {
                        $comentario = 'Archivo cargado en análisis financiero. Aún faltan documentos complementarios para transicionar de etapa.';
                    }
                    break;

                case 'aprobacion_presentacion':
                    if ($accion === 'aprobar') {
                        $estadoNuevo = 'comite_evaluacion';
                        $comentario = 'Presentación aprobada por Gerencia. Pasa a evaluación del Comité de Crédito.';
                    }
                    break;

                case 'comite_evaluacion':
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

        return response()->json($credito->load('cliente'));
    }
}
