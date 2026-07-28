<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCredito;
use App\Models\Visita;
use App\Models\Cliente;
use App\Models\User;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\TipoPersona;
use App\Mail\SolicitudCreditoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SolicitudCreditoController extends Controller
{
    /**
     * List pending credit requests from visits.
     */
    public function indexPending(Request $request)
    {
        // Get visits where requires credit is true and haven't been registered in solicitudes_credito yet
        $registeredVisits = SolicitudCredito::whereNotNull('visita_id')->pluck('visita_id')->toArray();

        $query = Visita::where('requiere_credito', true)
            ->whereNotIn('id', $registeredVisits)
            ->with(['cliente.tipoPersona', 'tipoCredito', 'amortizacion']);

        return response()->json($query->orderBy('fecha', 'desc')->get());
    }

    /**
     * List all registered credit requests (for history/audit).
     */
    public function index(Request $request)
    {
        $query = SolicitudCredito::with(['visita', 'cliente.tipoPersona', 'usuarioRegistra', 'tipoCredito', 'amortizacion', 'preset']);

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created credit request.
     */
    public function store(Request $request)
    {
        $rules = [
            'visita_id' => 'nullable|exists:visitas,id',
            'cliente_id' => 'required|exists:clientes,id',
            'tipo_credito_id' => 'required|exists:tipo_creditos,id',
            'monto_solicitado' => 'required|numeric|min:0.01',
            'proyecto' => 'nullable|string',
            'plazo_meses' => 'required|integer|min:1',
            'amortizacion_id' => 'required|exists:amortizaciones,id',
            'destino_recurso' => 'required|string',
            'garantia' => 'nullable|string',
            'fuente_pago' => 'required|string',
            'correo_notificacion' => 'required|email',
            'asunto_notificacion' => 'required|string',
            'mensaje_notificacion' => 'required|string',
            'document_preset_id' => 'nullable|exists:document_presets,id',
        ];

        // Conditional client info validations based on person type
        $cliente = Cliente::findOrFail($request->cliente_id);
        $tipoPersona = $cliente->tipoPersona;
        $codigoPersona = $tipoPersona ? strtoupper($tipoPersona->codigo) : 'NATURAL';

        // Información del Proyecto es obligatoria solo para Crédito Constructor
        // (SCRUM-120 Fase 2 / SCRUM-141) — la bandeja de Informe Técnico la necesita.
        $tipoCreditoSolicitud = \App\Models\TipoCredito::find($request->tipo_credito_id);
        $codigoTipoCreditoSolicitud = $tipoCreditoSolicitud ? strtoupper($tipoCreditoSolicitud->codigo) : null;
        if ($codigoTipoCreditoSolicitud === 'CONSTRUCTOR') {
            $rules['proyecto'] = 'required|string';
            $rules['proyecto_direccion'] = 'required|string';
            $rules['proyecto_departamento_id'] = 'required|exists:departamentos,id';
            $rules['proyecto_ciudad_id'] = [
                'required',
                Rule::exists('ciudades', 'id')->where(function ($query) use ($request) {
                    $query->where('departamento_id', $request->proyecto_departamento_id);
                }),
            ];
        }

        if ($codigoPersona === 'NATURAL') {
            $rules['nombres'] = 'required|string';
            $rules['primer_apellido'] = 'required|string';
            $rules['segundo_apellido'] = 'nullable|string';
            $rules['correo_electronico'] = 'required|email';
            $rules['telefono'] = 'required|string';
            $rules['direccion'] = 'required|string';
            $rules['pais'] = 'required|string';
            $rules['departamento_id'] = 'required|exists:departamentos,id';
            $rules['ciudad_id'] = [
                'required',
                Rule::exists('ciudades', 'id')->where(function ($query) use ($request) {
                    $query->where('departamento_id', $request->departamento_id);
                }),
            ];
        } else {
            // Juridica
            $rules['nombre_razon_social'] = 'required|string';
            $rules['tipo_empresa'] = 'required|string';
            $rules['actividad_economica'] = 'required|string';
            $rules['correo_electronico_empresarial'] = 'required|email';
            $rules['telefono'] = 'required|string';
            $rules['direccion'] = 'required|string';
            $rules['pais'] = 'required|string';
            $rules['departamento_id'] = 'required|exists:departamentos,id';
            $rules['ciudad_id'] = [
                'required',
                Rule::exists('ciudades', 'id')->where(function ($query) use ($request) {
                    $query->where('departamento_id', $request->departamento_id);
                }),
            ];

            // Representative Legal
            $rules['rep_tipo_documento_id'] = 'required|exists:document_types,id';
            $rules['rep_numero_documento'] = 'required|string';
            $rules['rep_nombres'] = 'required|string';
            $rules['rep_primer_apellido'] = 'required|string';
            $rules['rep_segundo_apellido'] = 'nullable|string';
            $rules['rep_cargo'] = 'required|string';
            $rules['rep_correo_electronico'] = 'required|email';
            $rules['rep_telefono'] = 'required|string';
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($request, $cliente, $codigoPersona, $validated) {
            // 1. Update Cliente information with the validated client details
            if ($codigoPersona === 'NATURAL') {
                $cliente->update([
                    'nombres' => $validated['nombres'],
                    'primer_apellido' => $validated['primer_apellido'],
                    'segundo_apellido' => $validated['segundo_apellido'] ?? null,
                    'correo_electronico' => $validated['correo_electronico'],
                    'telefono' => $validated['telefono'],
                    'direccion' => $validated['direccion'],
                    'pais' => $validated['pais'],
                    'departamento_id' => $validated['departamento_id'],
                    'ciudad_id' => $validated['ciudad_id'],
                ]);
            } else {
                $cliente->update([
                    'nombre_razon_social' => $validated['nombre_razon_social'],
                    'tipo_empresa' => $validated['tipo_empresa'],
                    'actividad_economica' => $validated['actividad_economica'],
                    'correo_electronico_empresarial' => $validated['correo_electronico_empresarial'],
                    'telefono' => $validated['telefono'],
                    'direccion' => $validated['direccion'],
                    'pais' => $validated['pais'],
                    'departamento_id' => $validated['departamento_id'],
                    'ciudad_id' => $validated['ciudad_id'],
                    'rep_tipo_documento_id' => $validated['rep_tipo_documento_id'],
                    'rep_numero_documento' => $validated['rep_numero_documento'],
                    'rep_nombres' => $validated['rep_nombres'],
                    'rep_primer_apellido' => $validated['rep_primer_apellido'],
                    'rep_segundo_apellido' => $validated['rep_segundo_apellido'] ?? null,
                    'rep_cargo' => $validated['rep_cargo'],
                    'rep_correo_electronico' => $validated['rep_correo_electronico'],
                    'rep_telefono' => $validated['rep_telefono'],
                ]);
            }

            // 2. Ensure User exists and sync their details
            $cleanPassword = str_replace('-', '', $cliente->numero_documento);
            $email = ($codigoPersona === 'NATURAL') 
                ? $cliente->correo_electronico 
                : $cliente->correo_electronico_empresarial;

            $user = User::withTrashed()->where('numero_documento', $cliente->numero_documento)->first();

            $userData = [
                'name' => $cliente->nombre,
                'email' => $email,
                'tipo_documento_id' => $cliente->tipo_documento_id,
                'roles' => ['cliente'],
            ];

            if (!$user) {
                $userData['numero_documento'] = $cliente->numero_documento;
                $userData['password'] = Hash::make($cleanPassword);
                $user = User::create($userData);
            } else {
                $user->update($userData);
                if ($user->trashed()) {
                    $user->restore();
                }
            }

            // 3. Save SolicitudCredito
            $solicitud = SolicitudCredito::create([
                'visita_id' => $validated['visita_id'] ?? null,
                'cliente_id' => $cliente->id,
                'usuario_registra_id' => $request->user()->id,
                'tipo_credito_id' => $validated['tipo_credito_id'],
                'proyecto' => $validated['proyecto'] ?? null,
                'proyecto_direccion' => $validated['proyecto_direccion'] ?? null,
                'proyecto_departamento_id' => $validated['proyecto_departamento_id'] ?? null,
                'proyecto_ciudad_id' => $validated['proyecto_ciudad_id'] ?? null,
                'monto_solicitado' => $validated['monto_solicitado'],
                'plazo_meses' => $validated['plazo_meses'],
                'amortizacion_id' => $validated['amortizacion_id'],
                'destino_recurso' => $validated['destino_recurso'],
                'garantia' => $validated['garantia'] ?? null,
                'fuente_pago' => $validated['fuente_pago'],
                'correo_notificacion' => $validated['correo_notificacion'],
                'asunto_notificacion' => $validated['asunto_notificacion'],
                'mensaje_notificacion' => $validated['mensaje_notificacion'],
                'document_preset_id' => $validated['document_preset_id'] ?? null,
            ]);

            // 3.1. Automatically start the BPMN CreditoOrdinario workflow for
            // ORDINARIO (comité completo) y CONSTRUCTOR (SCRUM-120: solo hasta
            // Informe Técnico en esta fase, sin pasar por comité/desembolso).
            $tipoCredito = \App\Models\TipoCredito::find($validated['tipo_credito_id']);
            $codigoTipoCredito = $tipoCredito ? strtoupper($tipoCredito->codigo) : null;

            if (in_array($codigoTipoCredito, ['ORDINARIO', 'CONSTRUCTOR'])) {
                $activeRole = $request->header('X-Active-Role')
                    ?? (($request->user()->roles && is_array($request->user()->roles))
                        ? ($request->user()->roles[0] ?? 'coordinador_comercial')
                        : 'coordinador_comercial');

                if ($codigoTipoCredito === 'CONSTRUCTOR') {
                    \App\Models\CreditoOrdinario::iniciar(
                        clienteId:  $user->id,
                        monto:      $validated['monto_solicitado'],
                        plazoMeses: $validated['plazo_meses'],
                        usuario:    $request->user()->name,
                        rol:        $activeRole,
                        comentario: 'Solicitud de crédito Constructor registrada. Esperando validación de documentación para habilitar el Informe Técnico.',
                        solicitudCreditoId: $solicitud->id,
                        estadoInicial: 'validacion_documental_constructor',
                        documentosIniciales: []
                    );
                } else {
                    \App\Models\CreditoOrdinario::iniciar(
                        clienteId:  $user->id,
                        monto:      $validated['monto_solicitado'],
                        plazoMeses: $validated['plazo_meses'],
                        usuario:    $request->user()->name,
                        rol:        $activeRole,
                        comentario: 'Solicitud de crédito ordinario registrada e iniciada desde el módulo de solicitudes.',
                        solicitudCreditoId: $solicitud->id
                    );
                }
            }

            // 4. Create DocumentRequest in the database if document_preset_id is provided
            // SCRUM-152: cada SolicitudCredito tiene su propio DocumentRequest 1:1
            // (SolicitudCredito::documentRequest() es hasOne por solicitud_credito_id).
            // Antes se reutilizaba cualquier DocumentRequest "pendiente" del mismo
            // cliente y se le mezclaban los requisitos del preset nuevo — si ese
            // request ya pertenecía a otra solicitud, esta solicitud quedaba sin
            // documentRequest propio y Etapa 1 caía al fallback genérico de 4
            // documentos fijos en vez de mostrar los del preset elegido.
            $documentosRequeridos = [];
            if ($validated['document_preset_id'] ?? null) {
                $preset = DocumentPreset::findOrFail($validated['document_preset_id']);
                $requirementIds = $preset->requirements()->pluck('document_requirements.id')->toArray();
                $documentosRequeridos = $preset->requirements()->pluck('nombre')->toArray();

                if (count($requirementIds) > 0) {
                    $docRequest = DocumentRequest::create([
                        'cliente_id' => $user->id,
                        'creado_por' => $request->user()->id,
                        'solicitud_credito_id' => $solicitud->id,
                        'estado' => 'pendiente'
                    ]);

                    foreach ($requirementIds as $reqId) {
                        DocumentRequestItem::create([
                            'document_request_id' => $docRequest->id,
                            'document_requirement_id' => $reqId,
                            'estado' => 'pendiente'
                        ]);
                    }
                }
            }

            // 5. Send Notification Email
            Mail::to($solicitud->correo_notificacion)->send(new SolicitudCreditoMail($solicitud, $documentosRequeridos));

            return response()->json($solicitud->load(['visita', 'cliente', 'usuarioRegistra', 'tipoCredito', 'amortizacion']), 201);
        });
    }
}
