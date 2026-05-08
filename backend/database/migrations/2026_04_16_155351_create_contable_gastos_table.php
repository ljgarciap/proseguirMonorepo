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
        Schema::create('contable_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('contable_imports')->nullOnDelete();
            $table->string('unique_hash')->unique();
            $table->date('fecha')->nullable();
            $table->string('comprobante_contable')->nullable();
            $table->string('no_factura')->nullable();
            $table->string('nit')->nullable();
            $table->string('tercero')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('cta_contable')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contable_gastos');
    }
};
