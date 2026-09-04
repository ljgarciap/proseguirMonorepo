<?php

use App\Models\Configuracion;
use Illuminate\Database\Migrations\Migration;

/**
 * SCRUM-329: ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM traía "5" desde
 * ConfiguracionSeeder (SCRUM-155), descrita como "millones de COP" pero
 * comparada en el controller contra diferencia_contable en COP reales
 * (ver docblock de AnalisisFinancieroCalculoService::calcularResumen()) —
 * la tolerancia real vigente era $5 COP, no $5.000.000. Se corrige a los
 * $100.000 COP pedidos, pero SOLO si nadie la cambió manualmente desde
 * /configuraciones (mismo criterio que ConfiguracionSeeder: 'valor' nunca
 * se pisa en un deploy si un superadmin ya lo tocó).
 */
return new class extends Migration
{
    public function up(): void
    {
        Configuracion::where('clave', 'ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM')
            ->where('valor', '5')
            ->update(['valor' => '100000']);
    }

    public function down(): void
    {
        Configuracion::where('clave', 'ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM')
            ->where('valor', '100000')
            ->update(['valor' => '5']);
    }
};
