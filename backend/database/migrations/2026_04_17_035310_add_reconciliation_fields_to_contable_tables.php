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
        Schema::table('contable_facturas', function (Blueprint $table) {
            $table->string('status')->default('pendiente')->after('total')->index();
            $table->unsignedBigInteger('reconciled_id')->nullable()->after('status');
        });

        Schema::table('contable_bancos', function (Blueprint $table) {
            $table->string('status')->default('pendiente')->after('valor')->index();
            $table->unsignedBigInteger('reconciled_id')->nullable()->after('status');
        });

        Schema::table('contable_auxiliars', function (Blueprint $table) {
            $table->unsignedBigInteger('source_factura_id')->nullable()->after('import_batch_id');
            $table->unsignedBigInteger('source_banco_id')->nullable()->after('source_factura_id');
        });

        Schema::table('contable_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('source_banco_id')->nullable()->after('import_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contable_facturas', function (Blueprint $table) {
            $table->dropColumn(['status', 'reconciled_id']);
        });

        Schema::table('contable_bancos', function (Blueprint $table) {
            $table->dropColumn(['status', 'reconciled_id']);
        });

        Schema::table('contable_auxiliars', function (Blueprint $table) {
            $table->dropColumn(['source_factura_id', 'source_banco_id']);
        });

        Schema::table('contable_gastos', function (Blueprint $table) {
            $table->dropColumn(['source_banco_id']);
        });
    }
};
