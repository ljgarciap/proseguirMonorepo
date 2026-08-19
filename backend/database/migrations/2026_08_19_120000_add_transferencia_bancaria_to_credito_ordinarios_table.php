<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-224: Registro de Transferencia Bancaria. 'transferencia_bancaria'
     * guarda el snapshot completo (datos bancarios + registro de la
     * transacción + rutas de soportes), mismo criterio que
     * 'documentos_desembolso' (SCRUM-215). 'numero_transaccion_bancaria' se
     * duplica como columna plana (no solo dentro del JSON) para poder
     * exigir unicidad real en BD (RN-08: "no puede estar asociado a otra
     * solicitud de crédito") sin depender de un índice sobre JSON.
     */
    public function up(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->json('transferencia_bancaria')->nullable()->after('documentos_desembolso');
            $table->string('numero_transaccion_bancaria')->nullable()->unique()->after('transferencia_bancaria');
        });
    }

    public function down(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->dropUnique(['numero_transaccion_bancaria']);
            $table->dropColumn(['transferencia_bancaria', 'numero_transaccion_bancaria']);
        });
    }
};
