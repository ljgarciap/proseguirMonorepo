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
        Schema::create('contable_auxiliars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('contable_imports')->nullOnDelete();
            $table->string('unique_hash')->unique();
            $table->date('fecha')->nullable();
            $table->string('comprobante')->nullable();
            $table->string('tercero')->nullable();
            $table->string('documento')->nullable();
            $table->string('detalle')->nullable();
            $table->string('centro_costos')->nullable();
            $table->string('nit')->nullable();
            $table->decimal('base_local', 15, 2)->nullable();
            $table->decimal('debito_local', 15, 2)->nullable();
            $table->decimal('credito_local', 15, 2)->nullable();
            $table->decimal('saldo_local', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contable_auxiliars');
    }
};
