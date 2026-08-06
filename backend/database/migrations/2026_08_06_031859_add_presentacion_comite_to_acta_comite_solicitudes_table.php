<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-183: se elimina el paso "Aprobación de Presentación" (Gerencia)
     * del BPMN — un crédito pasa a comite_evaluacion directo al confirmar el
     * Análisis Financiero. La Presentación para el Comité ya no bloquea esa
     * transición: se adjunta después, directo sobre la solicitud dentro del
     * Acta (una por solicitud, ya que cada crédito puede tener la suya).
     */
    public function up(): void
    {
        Schema::table('acta_comite_solicitudes', function (Blueprint $table) {
            $table->string('presentacion_comite')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('acta_comite_solicitudes', function (Blueprint $table) {
            $table->dropColumn('presentacion_comite');
        });
    }
};
