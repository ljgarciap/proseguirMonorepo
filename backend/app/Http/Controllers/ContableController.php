<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContableController extends Controller
{
    // Retrieve consolidated list
    public function getFacturas(Request $request)
    {
        $query = \App\Models\ContableFactura::with(['importBatch', 'reconciledRecord']);
        
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('factura', 'like', "%{$search}%")
                  ->orWhere('cliente', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate(15));
    }

    public function getBancos(Request $request)
    {
        $query = \App\Models\ContableBanco::with(['importBatch', 'reconciledRecord']);
        
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                  ->orWhere('sucursal', 'like', "%{$search}%")
                  ->orWhere('fecha', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');

        // Validation: Ensure the column exists in the table
        if (!\Schema::hasColumn('contable_bancos', $sortBy)) {
            $sortBy = 'id';
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate(15));
    }

    public function getAuxiliares(Request $request)
    {
        $query = \App\Models\ContableAuxiliar::with('importBatch');
        
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('comprobante', 'like', "%{$search}%")
                  ->orWhere('documento', 'like', "%{$search}%")
                  ->orWhere('tercero', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('detalle', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');

        if (!\Schema::hasColumn('contable_auxiliars', $sortBy)) {
            $sortBy = 'id';
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate(15));
    }

    public function getGastos(Request $request)
    {
        $query = \App\Models\ContableGasto::with('importBatch');
        
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('proveedor', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('concepto', 'like', "%{$search}%")
                  ->orWhere('documento', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->query('sortBy', 'id');
        $sortDir = $request->query('sortDir', 'desc');

        if (!\Schema::hasColumn('contable_gastos', $sortBy)) {
            $sortBy = 'id';
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate(15));
    }

    // List historical batches
    public function getImports()
    {
        return response()->json(\App\Models\ContableImport::orderBy('id', 'desc')->paginate(15));
    }

    public function clearAll()
    {
        // Order is important due to foreign keys, or just disable constraints
        \Schema::disableForeignKeyConstraints();
        
        \App\Models\ContableFactura::truncate();
        \App\Models\ContableBanco::truncate();
        \App\Models\ContableAuxiliar::truncate();
        \App\Models\ContableGasto::truncate();
        \App\Models\ContableImport::truncate();

        \Schema::enableForeignKeyConstraints();

        return response()->json(['message' => 'Módulo contable reseteado exitosamente']);
    }
}
