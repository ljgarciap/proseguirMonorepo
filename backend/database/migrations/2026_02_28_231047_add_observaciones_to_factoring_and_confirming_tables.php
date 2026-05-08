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
            $table->text('observaciones')->nullable();
        });
        Schema::table('pago_factorings', function (Blueprint $table) {
            $table->text('observaciones')->nullable();
        });
        Schema::table('operacion_confirmings', function (Blueprint $table) {
            $table->text('observaciones')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_factorings', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
        Schema::table('pago_factorings', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
        Schema::table('operacion_confirmings', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};
