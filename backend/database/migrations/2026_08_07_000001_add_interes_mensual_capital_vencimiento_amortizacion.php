<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SCRUM-188: agrega una nueva opción al catálogo de "Amortización"
     * (tabla `amortizaciones`, sin lógica de cálculo asociada al `codigo` —
     * es un catálogo puro que alimenta los dropdowns de Solicitud de
     * Crédito y Visitas).
     */
    public function up(): void
    {
        if (!DB::table('amortizaciones')->where('codigo', 'INTERES_MENSUAL_CAPITAL_VENCIMIENTO')->exists()) {
            DB::table('amortizaciones')->insert([
                'nombre' => 'Interés mensual capital al vencimiento',
                'codigo' => 'INTERES_MENSUAL_CAPITAL_VENCIMIENTO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('amortizaciones')->where('codigo', 'INTERES_MENSUAL_CAPITAL_VENCIMIENTO')->delete();
    }
};
