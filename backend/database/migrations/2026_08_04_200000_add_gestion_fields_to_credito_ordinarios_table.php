<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión de Créditos (SCRUM-178): trazabilidad de la gestión que hace el
 * Coordinador Comercial sobre resultados de SARLAFT desfavorable y
 * decisiones del Comité de Crédito. Ver docs/architecture/scrum-178-
 * gestion-creditos-diseno.md §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->boolean('solicitud_gestionada')->default(false)->after('sarlaft_diligenciado_at');
            $table->timestamp('fecha_gestion')->nullable()->after('solicitud_gestionada');
            // sarlaft | comite_aprobado | comite_rechazado | comite_pendiente — validado en app
            $table->string('resultado_origen')->nullable()->after('fecha_gestion');
            $table->json('gestion_detalle')->nullable()->after('resultado_origen');
        });
    }

    public function down(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->dropColumn(['solicitud_gestionada', 'fecha_gestion', 'resultado_origen', 'gestion_detalle']);
        });
    }
};
