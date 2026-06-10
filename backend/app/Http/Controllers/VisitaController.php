<?php

namespace App\Http\Controllers;

use App\Models\Visita;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'ciudad' => 'required|string|max:255',
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

        $visita = Visita::create($validated);
        
        return response()->json($visita->load(['cliente.tipoPersona', 'tipoCredito', 'amortizacion']), 201);
    }

    public function update(Request $request, $id)
    {
        $visita = Visita::findOrFail($id);

        $rules = [
            'fecha' => 'required|date',
            'ciudad' => 'required|string|max:255',
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

        $visita->update($validated);

        return response()->json($visita->load(['cliente.tipoPersona', 'tipoCredito', 'amortizacion']));
    }

    public function destroy($id)
    {
        $visita = Visita::findOrFail($id);
        $visita->delete();

        return response()->json(['message' => 'Visita eliminada correctamente']);
    }
}
