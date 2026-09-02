<?php

namespace App\Http\Controllers;

use App\Mail\VisitaCreditoDetectadoCoordinadorMail;
use App\Models\Visita;
use App\Models\Cliente;
use App\Models\Ciudad;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class VisitaController extends Controller
{
    public function index(Request $request)
    {
        $query = Visita::select('visitas.*')
            ->join('clientes', 'visitas.cliente_id', '=', 'clientes.id')
            ->leftJoin('tipo_personas', 'clientes.tipo_persona_id', '=', 'tipo_personas.id')
            ->with(['cliente.tipoPersona', 'tipoCredito', 'amortizacion']);

        // Filtering
        if ($request->filled('id')) {
            $query->where('visitas.id', $request->id);
        }
        if ($request->filled('fecha')) {
            $query->whereDate('visitas.fecha', $request->fecha);
        }
        if ($request->filled('cliente')) {
            $query->where('clientes.nombre', 'like', "%{$request->cliente}%");
        }
        if ($request->filled('tipo_persona')) {
            $query->where('tipo_personas.nombre', 'like', "%{$request->tipo_persona}%");
        }
        if ($request->filled('identificacion')) {
            $query->where('clientes.numero_documento', 'like', "%{$request->identificacion}%");
        }
        if ($request->filled('ciudad')) {
            $query->where('visitas.ciudad', 'like', "%{$request->ciudad}%");
        }

        // Sorting: Ascending order according to ID, Date, Client name, Person type
        $query->orderBy('visitas.id', 'asc')
              ->orderBy('visitas.fecha', 'asc')
              ->orderBy('clientes.nombre', 'asc')
              ->orderBy('tipo_personas.nombre', 'asc');

        return response()->json($query->get());
    }

    public function show($id)
    {
        $visita = Visita::with(['cliente.tipoPersona', 'tipoCredito', 'amortizacion'])->findOrFail($id);
        return response()->json($visita);
    }

    public function store(Request $request)
    {
        $rules = [
            'fecha' => 'required|date',
            'departamento_id' => 'required|exists:departamentos,id',
            'ciudad_id' => 'required|exists:ciudades,id',
            'cliente_id' => 'required|exists:clientes,id',
            'asistentes' => 'required|string',
            'observaciones' => 'nullable|string',
            'requiere_credito' => 'required|boolean',
        ];

        // Conditional validations
        if (filter_var($request->requiere_credito, FILTER_VALIDATE_BOOLEAN)) {
            $rules['tipo_credito_id'] = 'required|exists:tipo_creditos,id';
            $rules['monto_solicitado'] = 'required|numeric|min:0.01';
            $rules['plazo'] = 'required|integer|min:1';
            $rules['amortizacion_id'] = 'required|exists:amortizaciones,id';
            $rules['destino_recurso'] = 'required|string';
            $rules['garantia'] = 'nullable|string';
            $rules['fuente_pago'] = 'required|string';
        }

        $validated = $request->validate($rules);

        // Check if selected client is active
        $cliente = Cliente::findOrFail($request->cliente_id);
        if (!$cliente->activo) {
            return response()->json(['message' => 'Solo se pueden registrar visitas a clientes activos.'], 422);
        }

        // If requires credit is false, clear out credit details to maintain clean record data
        if (!filter_var($request->requiere_credito, FILTER_VALIDATE_BOOLEAN)) {
            $validated['tipo_credito_id'] = null;
            $validated['monto_solicitado'] = null;
            $validated['plazo'] = null;
            $validated['amortizacion_id'] = null;
            $validated['destino_recurso'] = null;
            $validated['garantia'] = null;
            $validated['fuente_pago'] = null;
        }

        $validated['ciudad'] = $this->resolverTextoCiudad($validated['departamento_id'], $validated['ciudad_id']);

        $visita = Visita::create($validated);
        $visita->load(['cliente.tipoPersona', 'cliente.documentType', 'tipoCredito', 'amortizacion']);

        // SCRUM-316: el registro exitoso de una visita que sí requiere
        // crédito notifica a Coordinador Comercial — se dispara siempre que
        // la condición se cumpla, sin importar qué rol tiene quien la
        // registró (decisión explícita de Luis 2026-09-02: Registro de
        // Visita a Cliente hoy también lo usan Operativo/Superadmin, no
        // solo Gerente).
        if ($visita->requiere_credito) {
            $this->notificarCoordinadorComercial($visita);
        }

        return response()->json($visita, 201);
    }

    public function update(Request $request, $id)
    {
        $visita = Visita::findOrFail($id);

        $rules = [
            'fecha' => 'required|date',
            'departamento_id' => 'required|exists:departamentos,id',
            'ciudad_id' => 'required|exists:ciudades,id',
            'cliente_id' => 'required|exists:clientes,id',
            'asistentes' => 'required|string',
            'observaciones' => 'nullable|string',
            'requiere_credito' => 'required|boolean',
        ];

        // Conditional validations
        if (filter_var($request->requiere_credito, FILTER_VALIDATE_BOOLEAN)) {
            $rules['tipo_credito_id'] = 'required|exists:tipo_creditos,id';
            $rules['monto_solicitado'] = 'required|numeric|min:0.01';
            $rules['plazo'] = 'required|integer|min:1';
            $rules['amortizacion_id'] = 'required|exists:amortizaciones,id';
            $rules['destino_recurso'] = 'required|string';
            $rules['garantia'] = 'nullable|string';
            $rules['fuente_pago'] = 'required|string';
        }

        $validated = $request->validate($rules);

        // Check if selected client is active
        $cliente = Cliente::findOrFail($request->cliente_id);
        if (!$cliente->activo) {
            return response()->json(['message' => 'Solo se pueden registrar visitas a clientes activos.'], 422);
        }

        // Clear details if requires credit is false
        if (!filter_var($request->requiere_credito, FILTER_VALIDATE_BOOLEAN)) {
            $validated['tipo_credito_id'] = null;
            $validated['monto_solicitado'] = null;
            $validated['plazo'] = null;
            $validated['amortizacion_id'] = null;
            $validated['destino_recurso'] = null;
            $validated['garantia'] = null;
            $validated['fuente_pago'] = null;
        }

        $validated['ciudad'] = $this->resolverTextoCiudad($validated['departamento_id'], $validated['ciudad_id']);

        $visita->update($validated);

        return response()->json($visita->load(['cliente.tipoPersona', 'tipoCredito', 'amortizacion']));
    }

    public function destroy($id)
    {
        $visita = Visita::findOrFail($id);
        $visita->delete();

        return response()->json(['message' => 'Visita eliminada correctamente']);
    }

    /**
     * SCRUM-316: envío best-effort a todos los usuarios activos con rol
     * Coordinador Comercial — mismo idioma que
     * GestionCreditoController::notificarPorRol() (User::whereJsonContains,
     * un fallo de envío no revierte la visita ya guardada). Cada intento
     * (enviado, fallido, o sin destinatarios) queda trazado en ActivityLog.
     */
    private function notificarCoordinadorComercial(Visita $visita): void
    {
        $gerente = Auth::user();
        $destinatarios = User::whereJsonContains('roles', 'coordinador_comercial')
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        if (empty($destinatarios)) {
            app(ActivityLogService::class)->registrar(
                'visita_notificacion_sin_destinatarios',
                "No hay usuarios activos con rol Coordinador Comercial para notificar la visita {$visita->id} ({$visita->cliente->nombre}).",
                $gerente,
                $visita,
            );

            return;
        }

        $urlAcceso = rtrim(env('FRONTEND_URL', config('app.url')), '/')
            . '/login?returnTo=' . urlencode('/visitas');

        try {
            Mail::to($destinatarios)->send(
                new VisitaCreditoDetectadoCoordinadorMail($visita, $gerente?->name ?? 'Usuario', $urlAcceso)
            );

            app(ActivityLogService::class)->registrar(
                'visita_notificacion_enviada',
                "Notificación de visita con crédito enviada a Coordinador Comercial para la visita {$visita->id} ({$visita->cliente->nombre}).",
                $gerente,
                $visita,
                ['destinatarios' => $destinatarios]
            );
        } catch (Throwable $e) {
            Log::error("SCRUM-316: no se pudo enviar la notificación de la visita {$visita->id} a Coordinador Comercial: " . $e->getMessage());

            app(ActivityLogService::class)->registrar(
                'visita_notificacion_fallida',
                "Fallo al enviar la notificación de la visita {$visita->id} ({$visita->cliente->nombre}) a Coordinador Comercial.",
                $gerente,
                $visita,
                ['destinatarios' => $destinatarios, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * SCRUM-118: 'ciudad' (texto libre) se conserva por compatibilidad con
     * el filtro de index() y con datos históricos, pero ahora se deriva
     * siempre del desplegable — nunca se vuelve a pedir como input aparte.
     * Valida además que la ciudad realmente pertenezca al departamento
     * elegido (no confiar en que el frontend mande una combinación
     * consistente).
     */
    private function resolverTextoCiudad(int $departamentoId, int $ciudadId): string
    {
        $ciudad = Ciudad::where('id', $ciudadId)->where('departamento_id', $departamentoId)->first();

        if (!$ciudad) {
            abort(response()->json([
                'message' => 'La ciudad seleccionada no pertenece al departamento elegido.'
            ], 422));
        }

        return $ciudad->nombre;
    }
}
