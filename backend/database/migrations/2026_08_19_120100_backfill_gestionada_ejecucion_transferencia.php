<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SCRUM-224: antes de esta migración, 'ejecucion_transferencia' no era
     * una tarjeta de Gestión de Créditos, así que desembolsoAprobacion()
     * dejaba `solicitud_gestionada = true` al entrar ahí (ver docblock de
     * GestionCreditoController::desembolsoAprobacion(), ahora corregido a
     * `false`). Cualquier crédito que YA esté en 'ejecucion_transferencia'
     * en este momento (aprobado antes de este cambio) quedaría invisible
     * para siempre en la nueva tarjeta de Tesorería si no se corrige acá —
     * el conteo de tarjetas.php filtra por `solicitud_gestionada = false`.
     */
    public function up(): void
    {
        DB::table('credito_ordinarios')
            ->where('estado', 'ejecucion_transferencia')
            ->update(['solicitud_gestionada' => false, 'fecha_gestion' => null]);
    }

    public function down(): void
    {
        // No reversible de forma segura: no hay registro de qué filas se
        // tocaron ni de su fecha_gestion original.
    }
};
