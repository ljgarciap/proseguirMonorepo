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
        Schema::create('contable_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('contable_imports')->nullOnDelete();
            $table->string('factura')->unique();
            $table->string('pedido')->nullable();
            $table->string('cliente')->nullable();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono')->nullable();
            $table->string('nit')->nullable();
            $table->date('fecha')->nullable();
            $table->date('vencimiento')->nullable();
            $table->decimal('vlr_bruto', 15, 2)->nullable();
            $table->decimal('vlr_dcto', 15, 2)->nullable();
            $table->decimal('vlr_iva_5', 15, 2)->nullable();
            $table->decimal('vlr_iva_19', 15, 2)->nullable();
            $table->decimal('vlr_i_consumo', 15, 2)->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contable_facturas');
    }
};
