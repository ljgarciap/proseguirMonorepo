<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-224: catálogo de entidades bancarias para el Registro de
     * Transferencia Bancaria (RN-01..12, campo "Entidad bancaria" §5.2) —
     * mismo patrón minimal que 'sectores' (id + nombre único), administrable
     * vía el CRUD genérico de ParameterController/ParametersComponent.
     */
    public function up(): void
    {
        Schema::create('entidades_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidades_bancarias');
    }
};
