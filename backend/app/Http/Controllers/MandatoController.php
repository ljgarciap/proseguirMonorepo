<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mandato;

class MandatoController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roles = $user->roles ?? [];
        $isAdminOrOp = in_array('superadmin', $roles) || in_array('operativo', $roles);

        if ($isAdminOrOp) {
            return response()->json(Mandato::with('user')->orderBy('created_at', 'desc')->get());
        }

        return response()->json(Mandato::where('user_id', $user->id)->orderBy('created_at', 'desc')->get());
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pendiente,firmado,rechazado',
            'observaciones' => 'nullable|string'
        ]);

        $mandato = Mandato::findOrFail($id);
        $mandato->update([
            'status' => $request->status,
            'observaciones' => $request->observaciones
        ]);

        return response()->json($mandato);
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
