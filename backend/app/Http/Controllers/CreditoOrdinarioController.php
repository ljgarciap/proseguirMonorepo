<?php

namespace App\Http\Controllers;

use App\Models\CreditoOrdinario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreditoOrdinarioController extends Controller
{
    /**
     * Listar las solicitudes de crédito ordinario
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $activeRole = $request->header('X-Active-Role') ?? (($user->roles && is_array($user->roles)) ? ($user->roles[0] ?? 'cliente') : 'cliente');
        
        $query = CreditoOrdinario::with('cliente');

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
        $activeRole = $request->header('X-Active-Role') ?? (($user->roles && is_array($user->roles)) ? ($user->roles[0] ?? 'cliente') : 'cliente');

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
        $credito = CreditoOrdinario::with('cliente')->findOrFail($id);
        return response()->json($credito);
    }

    /**
     * Procesar transición de estado y carga de archivos según el rol activo
     */
    public function transition(Request $request, $id)
    {
        $credito = CreditoOrdinario::findOrFail($id);
        $user = Auth::user();
        $activeRole = $request->header('X-Active-Role') ?? (($user->roles && is_array($user->roles)) ? ($user->roles[0] ?? 'cliente') : 'cliente');
        
        $request->validate([
            'accion' => 'required|string|in:aprobar,rechazar,completar,subir_archivo,devolver',
            'comentario' => 'nullable|string',
            'archivo' => 'nullable|string', // base64 representation of the file
            'archivo_nombre' => 'nullable|string',
            'campo_documento' => 'nullable|string' // field in the documentos array
        ]);

        $accion = $request->accion;
        $comentario = $request->comentario ?? 'Acción ejecutada en el flujo de crédito.';
        $estadoActual = $credito->estado;
        $estadoNuevo = $estadoActual;

        // Bypassear validaciones estrictas de rol si es superadmin (para facilitar pruebas)
        $isAuthorized = ($activeRole === 'superadmin');

        // Mapa de roles autorizados por estado para transiciones
        $rolesAutorizados = [
            'revision_documental' => ['coordinador_comercial'],
            'completar_solicitud' => ['cliente'],
            'analisis_sarlaft_financiero' => ['coordinador_comercial', 'oficial_cumplimiento'],
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
        if ($request->has('archivo') && $request->filled('archivo') && $request->filled('campo_documento')) {
            $base64File = $request->archivo;
            $fileName = $request->archivo_nombre ?? 'documento_' . Str::random(10) . '.pdf';
            
            // Decodificar base64
            if (preg_match('/^data:application\/pdf;base64,/', $base64File)) {
                $base64File = substr($base64File, strpos($base64File, ',') + 1);
            }
            $fileData = base64_decode($base64File);
            
            $path = 'credito_documentos/' . $credito->id . '/' . $fileName;
            Storage::disk('public')->put($path, $fileData);
            
            $campoDoc = $request->campo_documento;
            $documentos[$campoDoc] = Storage::url($path);
            $credito->documentos = $documentos;
            $credito->save();

            $comentario .= " (Archivo '$fileName' cargado correctamente en '$campoDoc').";
        }

        // 2. Lógica de Máquina de Estados BPMN
        if ($accion === 'rechazar') {
            if ($estadoActual === 'aprobacion_presentacion') {
                $estadoNuevo = 'analisis_sarlaft_financiero';
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
                    $estadoNuevo = 'analisis_sarlaft_financiero';
                    $comentario = 'Documentación revisada y aprobada. Pasa a análisis paralelo (SARLAFT y Financiero).';
                    break;

                case 'completar_solicitud':
                    $estadoNuevo = 'revision_documental';
                    $comentario = 'El cliente completó la solicitud. Retorna a revisión documental.';
                    break;

                case 'analisis_sarlaft_financiero':
                    // Si se cargan los documentos, validamos si ya están listos los del Oficial de Cumplimiento y los del Coordinador Comercial
                    $hasSarlaft = !empty($documentos['sarlft_sintesis']) && !empty($documentos['sarlft_datacredito']);
                    $hasFinancial = !empty($documentos['analisis_financiero']) && !empty($documentos['presentacion_comite']);
                    
                    if ($hasSarlaft && $hasFinancial) {
                        $estadoNuevo = 'aprobacion_presentacion';
                        $comentario = 'Análisis finalizado y documentos cargados por Cumplimiento y Comercial. Pasa a aprobación de presentación por Gerencia.';
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
