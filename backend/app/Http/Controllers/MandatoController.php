<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mandato;
use App\Exports\MandatoExport;
use Maatwebsite\Excel\Facades\Excel;

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

        $factor = \App\Models\DatosFactor::first();
        if ($factor) {
            $validated['factor_razon_social'] = $factor->razon_social;
            $validated['factor_tipo_documento'] = $factor->tipo_documento;
            $validated['factor_numero_documento'] = $factor->numero_documento;
            $validated['factor_rep_legal_nombre'] = $factor->rep_legal_nombre;
            $validated['factor_rep_legal_tipo_doc'] = $factor->rep_legal_tipo_doc;
            $validated['factor_rep_legal_num_doc'] = $factor->rep_legal_num_doc;
            $validated['factor_rep_legal_email'] = $factor->rep_legal_email;
        }

        $mandato = Mandato::create(array_merge($validated, [
            'user_id' => auth()->id(),
            'status' => 'pendiente'
        ]));

        return response()->json($mandato, 201);
    }

    public function update(Request $request, $id)
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
            'status' => 'nullable|string|in:pendiente,firmado,rechazado',
            'observaciones' => 'nullable|string'
        ]);

        $mandato = Mandato::findOrFail($id);
        $mandato->update($validated);

        return response()->json($mandato);
    }

    public function export($id)
    {
        $user = auth()->user();
        $roles = $user->roles ?? [];
        $isAdminOrOpOrStaff = in_array('superadmin', $roles) || in_array('operativo', $roles) || in_array('gerente', $roles) || in_array('contable', $roles);

        $mandato = Mandato::findOrFail($id);
        if (!$isAdminOrOpOrStaff && $mandato->user_id !== $user->id) {
            abort(403, 'No autorizado.');
        }

        $filename = "mandato_" . $mandato->mandante_numero_documento . "_" . now()->format('Y-m-d') . ".xlsx";
        return Excel::download(new MandatoExport($mandato), $filename);
    }

    public function destroy($id)
    {
        $mandato = Mandato::findOrFail($id);
        $mandato->delete();

        return response()->json(['message' => 'Mandato eliminado correctamente']);
    }
}
