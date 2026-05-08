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
        Schema::create('contable_bancos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('contable_imports')->nullOnDelete();
            $table->string('unique_hash')->unique();
            $table->string('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('sucursal')->nullable();
            $table->decimal('dcto', 15, 2)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contable_bancos');
    }
};
