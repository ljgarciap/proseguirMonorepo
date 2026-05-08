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
        Schema::create('compraventas', function (Blueprint $table) {
            $table->id();
            $table->string('vendedor')->nullable();
            $table->string('nit_vendedor')->nullable();
            $table->string('comprador')->nullable();
            $table->string('nit_comprador')->nullable();
            $table->string('factor')->nullable();
            $table->string('nit_factor')->nullable();
            $table->string('nro_factura')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('fecha_vencimiento')->nullable();
            $table->string('banco')->nullable();
            $table->string('cuenta_nro')->nullable();
            $table->foreignId('client_upload_id')->nullable()->constrained('client_uploads')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compraventas');
    }
};
