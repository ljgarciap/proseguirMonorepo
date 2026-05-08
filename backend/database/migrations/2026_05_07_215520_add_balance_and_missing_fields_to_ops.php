<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operacion_factorings', function (Blueprint $table) {
            if (!Schema::hasColumn('operacion_factorings', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 15, 2)->nullable()->after('valor_reserva');
            }
        });

        Schema::table('compraventas', function (Blueprint $table) {
            if (!Schema::hasColumn('compraventas', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 15, 2)->nullable()->after('valor');
            }
        });

        // Ensure fecha_vencimiento_capital exists in operacion_carteras (just in case)
        Schema::table('operacion_carteras', function (Blueprint $table) {
            if (!Schema::hasColumn('operacion_carteras', 'fecha_vencimiento_capital')) {
                $table->string('fecha_vencimiento_capital')->nullable()->after('estado_capital');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_factorings', function (Blueprint $table) {
            $table->dropColumn('saldo_pendiente');
        });

        Schema::table('compraventas', function (Blueprint $table) {
            $table->dropColumn('saldo_pendiente');
        });
    }
};
