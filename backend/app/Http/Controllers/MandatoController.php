<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mandato;

class MandatoController extends Controller
{
    public function index()
    {
        return response()->json(Mandato::where('user_id', auth()->id())->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mandante_razon_social' => 'required|string',
            'mandante_tipo_documento' => 'required|string',
            'mandante_numero_documento' => 'required|string',
            'mandante_domicilio' => 'required|string',
            'mandante_direccion' => 'required|string',
            'mandante_telefono' => 'required|string',
            'mandante_rep_legal_nombre' => 'required|string',
            'mandante_rep_legal_tipo_doc' => 'required|string',
            'mandante_rep_legal_num_doc' => 'required|string',
            'mandante_rep_legal_email' => 'required|email',
            'factor_razon_social' => 'required|string',
            'factor_tipo_documento' => 'required|string',
            'factor_numero_documento' => 'required|string',
            'factor_rep_legal_nombre' => 'required|string',
            'factor_rep_legal_tipo_doc' => 'required|string',
            'factor_rep_legal_num_doc' => 'required|string',
            'factor_rep_legal_email' => 'required|email',
        ]);

        $mandato = Mandato::create(array_merge($validated, [
            'user_id' => auth()->id(),
            'status' => 'pendiente'
        ]));

        return response()->json($mandato, 201);
    }
}
