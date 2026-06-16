<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DatosFactor;

class DatosFactorController extends Controller
{
    public function show()
    {
        $datos = DatosFactor::first();
        if (!$datos) {
            // Fallback in case table is empty
            $datos = DatosFactor::create([
                'razon_social' => 'PROSEGUIR SOLUCIONES DE LIQUIDEZ SAS',
                'tipo_documento' => 'NIT',
                'numero_documento' => '900354306-2',
                'rep_legal_nombre' => 'PAULA TATIANA HOYOS GIRALDO',
                'rep_legal_tipo_doc' => 'CC',
                'rep_legal_num_doc' => '30402881',
                'rep_legal_email' => 'gerencia@proseguirliquidez.com',
            ]);
        }
        return response()->json($datos);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'razon_social' => 'required|string',
            'tipo_documento' => 'required|string',
            'numero_documento' => 'required|string',
            'rep_legal_nombre' => 'required|string',
            'rep_legal_tipo_doc' => 'required|string',
            'rep_legal_num_doc' => 'required|string',
            'rep_legal_email' => 'required|email',
        ]);

        $datos = DatosFactor::first();
        if (!$datos) {
            $datos = DatosFactor::create($validated);
        } else {
            $datos->update($validated);
        }

        return response()->json($datos);
    }
}
