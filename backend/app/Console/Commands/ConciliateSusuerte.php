<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConciliationExport;
use App\Services\ConciliationService;

class ConciliateSusuerte extends Command
{
    protected $signature = 'app:conciliate-susuerte';
    protected $description = 'Conciliate Susuerte deposits with bank extract';

    public function handle(ConciliationService $service)
    {
        $this->info("Starting reconciliation process...");

        $xlsxPath = base_path('../insumos/LISTA ABONOS SUSUERTE.xlsx');
        $pdfPath = base_path('../insumos/EXTRACTO BANCARIO.pdf');

        if (!file_exists($xlsxPath) || !file_exists($pdfPath)) {
            $this->error("Missing input files in insumos folder.");
            return 1;
        }

        $results = $service->conciliate($xlsxPath, $pdfPath);

        Excel::store(new ConciliationExport($results), 'CONCILIACION_RESULTADO.xlsx', 'local_insumos');

        $this->info("Reconciliation completed! Result saved to: insumos/CONCILIACION_RESULTADO.xlsx");
        return 0;
    }
}
