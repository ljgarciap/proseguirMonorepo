<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ContableImport;
use App\Imports\FacturaImport;
use App\Imports\AuxiliarImport;
use App\Imports\BancoImport;
use App\Imports\GastoImport;

class ContableImportController extends Controller
{
    public function upload(Request $request, $type)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('contable_imports', $filename);

        $importBatch = ContableImport::create([
            'type' => $type,
            'filename' => $filename,
            'records_processed' => 0
        ]);

        try {
            switch ($type) {
                case 'facturas':
                    Excel::import(new FacturaImport($importBatch->id), $path);
                    break;
                case 'auxiliar':
                    Excel::import(new AuxiliarImport($importBatch->id), $path);
                    break;
                case 'bancos':
                    Excel::import(new BancoImport($importBatch->id), $path);
                    break;
                case 'gastos':
                    Excel::import(new GastoImport($importBatch->id), $path);
                    break;
                default:
                    return response()->json(['message' => 'Tipo de archivo no soportado'], 400);
            }

            return response()->json([
                'message' => 'Archivo importado exitosamente',
                'batch_id' => $importBatch->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error durante la importación',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
