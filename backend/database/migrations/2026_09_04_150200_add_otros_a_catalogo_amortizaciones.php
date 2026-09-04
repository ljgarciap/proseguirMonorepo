<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SCRUM-333: agrega la opción "Otros" al catálogo de Amortización — se ve
 * en el select de Solicitud de Crédito y en el Acta de Comité
 * (amortizaciones, tabla paramétrica sin seeder propio hasta ahora, ver
 * ParameterController::MODELOS — sus filas actuales en cada ambiente
 * fueron cargadas a mano desde /parameters, no por seeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('amortizaciones')->insertOrIgnore([
            'codigo' => 'OTROS',
            'nombre' => 'Otros',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $otros = DB::table('amortizaciones')->where('codigo', 'OTROS')->first();
        if (!$otros) {
            return;
        }

        $enUso = DB::table('solicitudes_credito')->where('amortizacion_id', $otros->id)->exists()
            || DB::table('visitas')->where('amortizacion_id', $otros->id)->exists();

        if (!$enUso) {
            DB::table('amortizaciones')->where('id', $otros->id)->delete();
        }
    }
};
