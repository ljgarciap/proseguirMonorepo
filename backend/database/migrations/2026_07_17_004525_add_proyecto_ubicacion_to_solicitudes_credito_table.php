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
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            // SCRUM-141: "Información del Proyecto" — solo aplica a Crédito
            // Constructor, igual que 'proyecto' (nombre). Nullable porque las
            // solicitudes Ordinario no lo usan.
            $table->string('proyecto_direccion')->nullable()->after('proyecto');
            $table->foreignId('proyecto_departamento_id')->nullable()->after('proyecto_direccion')
                ->constrained('departamentos')->onDelete('set null');
            $table->foreignId('proyecto_ciudad_id')->nullable()->after('proyecto_departamento_id')
                ->constrained('ciudades')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_ciudad_id');
            $table->dropConstrainedForeignId('proyecto_departamento_id');
            $table->dropColumn('proyecto_direccion');
        });
    }
};
