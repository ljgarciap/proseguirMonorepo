<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlanillaFinca;
use App\Models\PlanillaTrabajador;
use App\Models\PlanillaLabor;
use App\Models\PlanillaActividad;
use App\Models\PlanillaGasto;
use Illuminate\Support\Facades\DB;

class PlanillaController extends Controller
{
    // --- Catalogs ---

    public function getFincas() { return response()->json(PlanillaFinca::all()); }
    public function storeFinca(Request $request) {
        $data = $request->validate(['nombre' => 'required', 'descripcion' => 'nullable']);
        return response()->json(PlanillaFinca::create($data));
    }

    public function getTrabajadores() { return response()->json(PlanillaTrabajador::orderBy('nombre')->get()); }
    public function storeTrabajador(Request $request) {
        $data = $request->validate([
            'nombre' => 'required',
            'identificacion' => 'nullable|unique:planilla_trabajadors',
            'telefono' => 'nullable',
            'retencion_pactada' => 'nullable|numeric'
        ]);
        return response()->json(PlanillaTrabajador::create($data));
    }

    public function getLabores() { return response()->json(PlanillaLabor::orderBy('nombre')->get()); }
    public function storeLabor(Request $request) {
        $data = $request->validate([
            'nombre' => 'required',
            'unidad' => 'required',
            'precio_sugerido' => 'nullable|numeric',
            'retencion_sugerida' => 'nullable|numeric'
        ]);
        return response()->json(PlanillaLabor::create($data));
    }

    // --- Activities (Individual Entries) ---

    public function getActividades(Request $request) {
        $query = PlanillaActividad::with(['finca', 'trabajador', 'labor'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($fincaId = $request->query('planilla_finca_id')) {
            $query->where('planilla_finca_id', $fincaId);
        }

        return response()->json($query->paginate(20));
    }

    public function storeActividad(Request $request) {
        $data = $request->validate([
            'planilla_finca_id' => 'required|exists:planilla_fincas,id',
            'planilla_trabajador_id' => 'required|exists:planilla_trabajadors,id',
            'planilla_labor_id' => 'required|exists:planilla_labors,id',
            'fecha' => 'required|date',
            'cantidad' => 'required|numeric',
            'precio_unitario' => 'nullable|numeric',
            'retencion_porcentaje' => 'nullable|numeric'
        ]);

        $trabajador = PlanillaTrabajador::find($data['planilla_trabajador_id']);
        $labor = PlanillaLabor::find($data['planilla_labor_id']);

        // 1. Determine Price (Input > Labor Default)
        $precio = $data['precio_unitario'] ?? $labor->precio_sugerido ?? 0;

        // 2. Determine Retention % (Input > Worker Pacted > Labor Default)
        $retenPct = $data['retencion_porcentaje'] 
                    ?? $trabajador->retencion_pactada 
                    ?? $labor->retencion_sugerida 
                    ?? 0;

        // 3. Calculations
        $subtotal = $data['cantidad'] * $precio;
        $retenValor = $subtotal * ($retenPct / 100);
        $neto = $subtotal - $retenValor;

        $actividad = PlanillaActividad::create([
            'planilla_finca_id' => $data['planilla_finca_id'],
            'planilla_trabajador_id' => $data['planilla_trabajador_id'],
            'planilla_labor_id' => $data['planilla_labor_id'],
            'fecha' => $data['fecha'],
            'cantidad' => $data['cantidad'],
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
            'retencion_porcentaje' => $retenPct,
            'retencion_valor' => $retenValor,
            'neto' => $neto,
            'observaciones' => $request->observaciones
        ]);

        return response()->json($actividad->load(['finca', 'trabajador', 'labor']));
    }

    public function deleteActividad($id) {
        PlanillaActividad::destroy($id);
        return response()->json(['message' => 'Actividad eliminada']);
    }

    // --- Expenses ---

    public function getGastos(Request $request) {
        $query = PlanillaGasto::with('finca')->orderBy('fecha', 'desc');
        if ($fincaId = $request->query('planilla_finca_id')) {
            $query->where('planilla_finca_id', $fincaId);
        }
        return response()->json($query->paginate(20));
    }

    public function storeGasto(Request $request) {
        $data = $request->validate([
            'planilla_finca_id' => 'required|exists:planilla_fincas,id',
            'fecha' => 'required|date',
            'concepto' => 'required',
            'beneficiario' => 'nullable',
            'valor' => 'required|numeric',
            'tipo' => 'required|in:gasto,inversion'
        ]);

        return response()->json(PlanillaGasto::create($data));
    }

    // --- Summary / Consolidated ---

    public function getResumen(Request $request) {
        $fincaId = $request->query('planilla_finca_id');
        
        $laboresQuery = PlanillaActividad::select(
            DB::raw('SUM(subtotal) as total_bruto'),
            DB::raw('SUM(retencion_valor) as total_retenciones'),
            DB::raw('SUM(neto) as total_neto')
        );

        $gastosQuery = PlanillaGasto::select(
            DB::raw('SUM(CASE WHEN tipo = "gasto" THEN valor ELSE 0 END) as total_gastos'),
            DB::raw('SUM(CASE WHEN tipo = "inversion" THEN valor ELSE 0 END) as total_inversiones')
        );

        if ($fincaId) {
            $laboresQuery->where('planilla_finca_id', $fincaId);
            $gastosQuery->where('planilla_finca_id', $fincaId);
        }

        $labores = $laboresQuery->first();
        $gastos = $gastosQuery->first();

        return response()->json([
            'labores' => $labores,
            'gastos' => $gastos,
            'gran_total' => ($labores->total_neto ?? 0) + ($gastos->total_gastos ?? 0) + ($gastos->total_inversiones ?? 0)
        ]);
    }
}
