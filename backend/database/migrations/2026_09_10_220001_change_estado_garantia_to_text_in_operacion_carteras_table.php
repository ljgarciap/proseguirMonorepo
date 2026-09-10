<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría post-SCRUM-348: 'garantia_detalle' y 'tipo_garantia' de esta
     * misma tabla ya se pasaron a text() en
     * 2026_03_02_021053_change_garantia_columns_to_text_in_operacion_carteras_table
     * — 'estado_garantia' quedó afuera de ese fix pese a tener el mismo
     * origen (extracción libre por OCR/LLM vía ProcessUploadJob, prompt sin
     * formato fijo: "Estado de la garantía principal", ver
     * OcrPersistenceService::persist()) y el mismo riesgo real de longitud
     * no acotada.
     */
    public function up(): void
    {
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->text('estado_garantia')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->string('estado_garantia')->nullable()->change();
        });
    }
};
