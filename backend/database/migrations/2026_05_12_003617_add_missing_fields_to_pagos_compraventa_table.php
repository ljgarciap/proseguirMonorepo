<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_compraventa', function (Blueprint $table) {
            $table->decimal('descuento_mora_causado_np', 18, 2)->nullable()->after('capital_pagado');
            $table->decimal('rec_descuento_mora_np', 18, 2)->nullable()->after('descuento_mora_causado_np');
            $table->decimal('saldo_despues_pago', 18, 2)->nullable()->after('total_pagado');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_compraventa', function (Blueprint $table) {
            $table->dropColumn(['descuento_mora_causado_np', 'rec_descuento_mora_np', 'saldo_despues_pago']);
        });
    }
};
