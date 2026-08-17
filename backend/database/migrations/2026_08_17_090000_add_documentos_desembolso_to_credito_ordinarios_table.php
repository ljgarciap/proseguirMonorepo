<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            // SCRUM-215: snapshot del registro de Operación de Desembolso en
            // CYF hecho por Operativo — preset usado, observaciones y el
            // detalle de cada documento cargado (requirement_id, nombre,
            // ruta relativa, nombre original). Un solo JSON en vez de
            // reusar DocumentRequest/DocumentRequestItem porque acá el
            // propio Operativo adjunta los archivos directamente (no hay
            // solicitud al cliente que esperar).
            $table->json('documentos_desembolso')->nullable()->after('radicado_cyf');
        });
    }

    public function down(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->dropColumn('documentos_desembolso');
        });
    }
};
