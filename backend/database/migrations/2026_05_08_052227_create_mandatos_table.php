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
        Schema::create('mandatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Datos Mandante
            $table->string('mandante_razon_social');
            $table->string('mandante_tipo_documento');
            $table->string('mandante_numero_documento');
            $table->string('mandante_domicilio');
            $table->string('mandante_direccion');
            $table->string('mandante_telefono');
            $table->string('mandante_rep_legal_nombre');
            $table->string('mandante_rep_legal_tipo_doc');
            $table->string('mandante_rep_legal_num_doc');
            $table->string('mandante_rep_legal_email');

            // Datos Factor
            $table->string('factor_razon_social');
            $table->string('factor_tipo_documento');
            $table->string('factor_numero_documento');
            $table->string('factor_rep_legal_nombre');
            $table->string('factor_rep_legal_tipo_doc');
            $table->string('factor_rep_legal_num_doc');
            $table->string('factor_rep_legal_email');

            $table->string('status')->default('pendiente'); // pendiente, firmado, rechazado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandatos');
    }
};
