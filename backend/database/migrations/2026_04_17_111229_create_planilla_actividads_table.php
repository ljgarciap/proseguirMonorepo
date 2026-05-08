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
        Schema::create('planilla_actividads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_finca_id')->constrained('planilla_fincas')->cascadeOnDelete();
            $table->foreignId('planilla_trabajador_id')->constrained('planilla_trabajadors')->cascadeOnDelete();
            $table->foreignId('planilla_labor_id')->constrained('planilla_labors')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('retencion_porcentaje', 5, 2)->default(0);
            $table->decimal('retencion_valor', 15, 2)->default(0);
            $table->decimal('neto', 15, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planilla_actividads');
    }
};
