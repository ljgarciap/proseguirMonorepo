<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\ConfiguracionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configs = Configuracion::with('updatedBy:id,name')
            ->orderBy('grupo')
            ->orderBy('descripcion')
            ->get()
            ->map(function ($cfg) {
                return [
                    'id'          => $cfg->id,
                    'clave'       => $cfg->clave,
                    'valor'       => $cfg->es_secreto ? null : $cfg->valor,
                    'descripcion' => $cfg->descripcion,
                    'grupo'       => $cfg->grupo,
                    'es_secreto'  => $cfg->es_secreto,
                    'tiene_valor' => !empty($cfg->valor),
                    'updated_by'  => $cfg->updatedBy?->name,
                    'updated_at'  => $cfg->updated_at,
                ];
            });

        return response()->json($configs);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'valor' => 'nullable|string|max:2000',
        ]);

        $config = Configuracion::findOrFail($id);

        ConfiguracionService::set($config->clave, $request->valor, Auth::id());

        return response()->json([
            'message'    => 'Configuración actualizada correctamente.',
            'clave'      => $config->clave,
            'tiene_valor' => !empty($request->valor),
            'updated_by' => Auth::user()->name,
            'updated_at' => now(),
        ]);
    }
}
