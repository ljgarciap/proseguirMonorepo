<?php

namespace App\Http\Controllers;

use App\Models\Destinatario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DestinatarioController extends Controller
{
    /**
     * Display a listing of the resource.
     * Ordered alphabetically ascending by 'nombre'.
     */
    public function index()
    {
        $destinatarios = Destinatario::orderBy('nombre', 'asc')->get();
        return response()->json($destinatarios);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('destinatarios', 'email')
            ],
            'activo' => 'nullable|boolean'
        ]);

        $destinatario = Destinatario::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'activo' => $request->has('activo') ? (bool)$request->activo : true
        ]);

        return response()->json($destinatario, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Destinatario $destinatario)
    {
        return response()->json($destinatario);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Destinatario $destinatario)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('destinatarios', 'email')->ignore($destinatario->id)
            ],
            'activo' => 'required|boolean'
        ]);

        $destinatario->update([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'activo' => (bool)$request->activo
        ]);

        return response()->json($destinatario);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Destinatario $destinatario)
    {
        $destinatario->delete();
        return response()->json(null, 204);
    }
}
