<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-252: marca cuándo se notificó al Coordinador Comercial que el
     * cliente completó el cargue de todos los documentos requeridos de un
     * DocumentRequest. No reutiliza la columna 'estado' (que en el flujo
     * OCR solo llega a 'completado' cuando los ítems quedan 'aprobado' —
     * criterio distinto al de este ticket, que solo exige 'subido') ni
     * dispara de nuevo el correo en consultas/actualizaciones posteriores
     * que no cambian la completitud (alt. 4.2 del ticket).
     */
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->timestamp('notificado_completado_at')->nullable()->after('preset_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('notificado_completado_at');
        });
    }
};
