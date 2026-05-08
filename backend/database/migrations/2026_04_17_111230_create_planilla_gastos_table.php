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
        Schema::create('planilla_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_finca_id')->constrained('planilla_fincas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('concepto');
            $table->string('beneficiario')->nullable();
            $table->decimal('valor', 15, 2);
            $table->enum('tipo', ['gasto', 'inversion'])->default('gasto');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planilla_gastos');
    }
};
