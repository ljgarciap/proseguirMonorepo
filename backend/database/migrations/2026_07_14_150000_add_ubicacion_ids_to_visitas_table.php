<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-118 (seguimiento 14/07): el campo "Ciudad" de Registro de
     * Visita era texto libre con autocomplete opcional, no un desplegable
     * real contra el catálogo. Se agregan FKs a departamentos/ciudades,
     * igual que se hizo para clientes en la migración
     * add_ubicacion_ids_to_clientes_table — se mantiene la columna
     * 'ciudad' de texto libre (no se borra) para no perder historial.
     */
    public function up(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable()->after('ciudad')->constrained('departamentos')->onDelete('set null');
            $table->foreignId('ciudad_id')->nullable()->after('departamento_id')->constrained('ciudades')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('departamento_id');
            $table->dropConstrainedForeignId('ciudad_id');
        });
    }
};
