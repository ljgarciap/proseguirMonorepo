<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-329: la "Unidad" del Análisis Financiero era un texto fijo
 * deshabilitado ("Millones de COP") sin ningún efecto real — los montos
 * siempre se capturaban y guardaban en COP reales completos. Se agrega
 * esta columna para que el selector (ver frontend) sea funcional de
 * verdad: solo cambia cómo se CAPTURAN/MUESTRAN los números (factor de
 * escala aplicado en AnalisisFinancieroDetalleComponent::campoInput()/
 * setCampoInput()) — lo que se guarda y se calcula en el backend sigue
 * siendo COP reales siempre, sin importar la unidad elegida (por eso la
 * tolerancia de SCRUM-329 se parametrizó en COP reales, no por unidad).
 *
 * Default 'MILLONES' — mismo valor que el texto fijo que reemplaza, así
 * que las 2 filas de análisis ya sembradas hoy (datos de demo/test, sin
 * uso real en producción todavía) no cambian de comportamiento hasta que
 * alguien elija explícitamente "Miles de COP".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_financieros', function (Blueprint $table) {
            $table->string('unidad', 20)->default('MILLONES')->after('cantidad_anios');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_financieros', function (Blueprint $table) {
            $table->dropColumn('unidad');
        });
    }
};
