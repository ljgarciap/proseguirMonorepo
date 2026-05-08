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
        Schema::create('planilla_labors', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('unidad')->default('Jornal');
            $table->decimal('precio_sugerido', 15, 2)->nullable();
            $table->decimal('retencion_sugerida', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planilla_labors');
    }
};
