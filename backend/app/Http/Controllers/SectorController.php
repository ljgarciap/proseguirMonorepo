<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SectorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sectores",
     *     summary="Listar todos los sectores económicos para mapeo",
     *     tags={"Metadata"},
     *     @OA\Response(response=200, description="Lista de sectores")
     * )
     */
    public function __invoke(Request $request)
    {
        return \App\Models\Sector::orderBy('nombre')->get();
    }
}
