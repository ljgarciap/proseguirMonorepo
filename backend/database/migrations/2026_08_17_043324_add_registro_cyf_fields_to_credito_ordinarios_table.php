<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de Crédito en CYF (SCRUM-193): campos capturados por el
 * Coordinador Comercial en Gestión de Créditos, separados del campo legacy
 * `documentos['registro_cyf']` (que sigue usando el flujo de Crédito
 * Ordinario / aprobación de Gerencia sin tocarlo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->date('fecha_registro_cyf')->nullable()->after('gestion_detalle');
            $table->string('radicado_cyf')->nullable()->after('fecha_registro_cyf');
        });
    }

    public function down(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->dropColumn(['fecha_registro_cyf', 'radicado_cyf']);
        });
    }
};
