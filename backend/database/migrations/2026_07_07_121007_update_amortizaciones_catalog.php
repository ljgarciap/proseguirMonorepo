<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SCRUM-119: reemplazar las opciones de "Amortización" en Solicitud de
     * Crédito. Se renombran en el lugar (mismo id) las 4 que ya tienen
     * solicitudes/visitas reales para no romper esas referencias; solo se
     * elimina "Semestral" porque no tiene ningún uso.
     */
    private array $renombres = [
        'DIARIO' => ['codigo' => 'INTERES_MENSUAL_CAPITAL_MENSUAL', 'nombre' => 'Interés mensual capital mensual'],
        'QUINCENAL' => ['codigo' => 'INTERES_MENSUAL_CAPITAL_TRIMESTRAL', 'nombre' => 'Interés mensual y capital trimestral'],
        'MENSUAL' => ['codigo' => 'CUOTA_FIJA_MENSUAL', 'nombre' => 'Cuota fija mensual'],
        'TRIMESTRAL' => ['codigo' => 'INTERES_CAPITAL_TRIMESTRAL', 'nombre' => 'Interés y capital trimestral'],
    ];

    private array $original = [
        'INTERES_MENSUAL_CAPITAL_MENSUAL' => ['codigo' => 'DIARIO', 'nombre' => 'Diario'],
        'INTERES_MENSUAL_CAPITAL_TRIMESTRAL' => ['codigo' => 'QUINCENAL', 'nombre' => 'Quincenal'],
        'CUOTA_FIJA_MENSUAL' => ['codigo' => 'MENSUAL', 'nombre' => 'Mensual'],
        'INTERES_CAPITAL_TRIMESTRAL' => ['codigo' => 'TRIMESTRAL', 'nombre' => 'Trimestral'],
    ];

    public function up(): void
    {
        foreach ($this->renombres as $codigoViejo => $nuevo) {
            DB::table('amortizaciones')->where('codigo', $codigoViejo)->update($nuevo);
        }

        // "Semestral" se elimina del catálogo. Solo se borra si nadie la
        // referencia todavía, para no romper solicitudes/visitas existentes
        // en un ambiente donde sí tenga uso real (a diferencia de test).
        $semestral = DB::table('amortizaciones')->where('codigo', 'SEMESTRAL')->first();
        if ($semestral) {
            $enUso = DB::table('solicitudes_credito')->where('amortizacion_id', $semestral->id)->exists()
                || DB::table('visitas')->where('amortizacion_id', $semestral->id)->exists();

            if (!$enUso) {
                DB::table('amortizaciones')->where('id', $semestral->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations. No revive el registro "Semestral" si fue
     * eliminado — si hace falta, hay que recrearlo manualmente.
     */
    public function down(): void
    {
        foreach ($this->original as $codigoNuevo => $viejo) {
            DB::table('amortizaciones')->where('codigo', $codigoNuevo)->update($viejo);
        }
    }
};
