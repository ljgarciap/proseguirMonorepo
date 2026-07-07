<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SCRUM-118: agrega FKs a departamentos/ciudades. Se mantienen las
     * columnas de texto libre 'departamento'/'ciudad' (no se borran) para
     * no perder el valor histórico mientras se reconcilian con el catálogo
     * nuevo vía el comando app:match-clientes-ubicacion.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable()->after('departamento')->constrained('departamentos')->onDelete('set null');
            $table->foreignId('ciudad_id')->nullable()->after('ciudad')->constrained('ciudades')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('departamento_id');
            $table->dropConstrainedForeignId('ciudad_id');
        });
    }
};
