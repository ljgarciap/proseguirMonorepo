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
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->text('garantia_detalle')->nullable()->change();
            $table->text('tipo_garantia')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_carteras', function (Blueprint $table) {
            $table->string('garantia_detalle')->nullable()->change();
            $table->string('tipo_garantia')->nullable()->change();
        });
    }
};
