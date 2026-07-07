<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Ciudad;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    public function departamentos()
    {
        return response()->json(
            Departamento::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function ciudades(Request $request)
    {
        $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
        ]);

        return response()->json(
            Ciudad::where('departamento_id', $request->departamento_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
        );
    }

    /**
     * Autocomplete de ciudades sin filtrar por departamento (ej. campo
     * "ciudad" de Visitas, que no tiene un departamento asociado).
     */
    public function buscarCiudades(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        return response()->json(
            Ciudad::where('nombre', 'like', '%' . $request->q . '%')
                ->orderBy('nombre')
                ->limit(20)
                ->get(['id', 'nombre', 'departamento_id'])
        );
    }
}
