<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-229: distinguir de qué etapa del BPMN viene un DocumentRequest
     * (hoy indistinguible entre el de Etapa 1 / SCRUM-146 y el de garantías
     * post-comité / SCRUM-193-205, ambos contra el mismo solicitud_credito_id)
     * y conservar de qué preset salió, para poder mostrarlo en Crédito
     * Ordinario tal como lo pide el Coordinador Comercial al solicitarlo.
     */
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('etapa')->nullable()->after('estado');
            $table->foreignId('preset_id')->nullable()->after('etapa')->constrained('document_presets')->nullOnDelete();
            $table->string('preset_nombre')->nullable()->after('preset_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preset_id');
            $table->dropColumn(['etapa', 'preset_nombre']);
        });
    }
};
