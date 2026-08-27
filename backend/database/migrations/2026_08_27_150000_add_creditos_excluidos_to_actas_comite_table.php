<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-279 — al eliminar de "Presentación de solicitudes" una
     * ActaComiteSolicitud vinculada a un CreditoOrdinario real que sigue en
     * comite_evaluacion (origen 'sistema' o 'manual_existente'), borrar solo
     * la fila no alcanza: sincronizarSolicitudesElegibles() la vuelve a
     * traer en la próxima carga porque el crédito real sigue siendo
     * elegible. `creditos_excluidos` guarda esos IDs para que el auto-sync
     * los ignore — decisión explícita de Luis (2026-08-27): el crédito
     * sigue disponible para una futura acta, no cambia de estado, solo se
     * excluye de ESTA acta puntual.
     */
    public function up(): void
    {
        Schema::table('actas_comite', function (Blueprint $table) {
            $table->json('creditos_excluidos')->nullable()->after('desarrollo');
        });
    }

    public function down(): void
    {
        Schema::table('actas_comite', function (Blueprint $table) {
            $table->dropColumn('creditos_excluidos');
        });
    }
};
