<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ConciliationService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConciliationExport;
use Illuminate\Support\Facades\Storage;

class ConciliationController extends Controller
{
    protected $service;

    public function __construct(ConciliationService $service)
    {
        $this->service = $service;
    }

    public function conciliate(Request $request)
    {
        $request->validate([
            'xlsx_file' => 'required|file',
            'pdf_file' => 'required|file',
        ]);

        $xlsxFile = $request->file('xlsx_file');
        $pdfFile = $request->file('pdf_file');

        $xlsxPath = $xlsxFile->store('temp');
        $pdfPath = $pdfFile->store('temp');

        $fullXlsxPath = storage_path('app/private/' . $xlsxPath);
        $fullPdfPath = storage_path('app/private/' . $pdfPath);

        try {
            $results = $this->service->conciliate($fullXlsxPath, $fullPdfPath);

            $fileName = 'conciliacion_' . now()->format('Ymd_His') . '.xlsx';
            Excel::store(new ConciliationExport($results), $fileName, 'public');

            // Clean up temp files
            Storage::delete([$xlsxPath, $pdfPath]);

            return response()->json([
                'message' => 'Conciliación completada con éxito',
                'results' => $results,
                'download_url' => asset('storage/' . $fileName)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
