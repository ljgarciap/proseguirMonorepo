<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-348 (fallo en prod): `garantia` quedó como string() (VARCHAR 255)
     * en la migración original de solicitudes_credito, a diferencia de
     * `destino_recurso`/`mensaje_notificacion` (ya text()) del mismo formulario.
     * Un texto de garantías/avalistas real (>255 caracteres) revienta el
     * INSERT en MySQL modo estricto con 1406 "Data too long" — mismo patrón
     * ya corregido antes en operacion_carteras (ver
     * 2026_03_02_021053_change_garantia_columns_to_text_in_operacion_carteras_table).
     */
    public function up(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->text('garantia')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->string('garantia')->nullable()->change();
        });
    }
};
