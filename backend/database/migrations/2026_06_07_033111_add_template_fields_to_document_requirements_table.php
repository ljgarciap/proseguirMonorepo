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
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->boolean('tiene_plantilla')->default(false)->after('activo');
            $table->string('plantilla_path')->nullable()->after('tiene_plantilla');
            $table->string('plantilla_nombre')->nullable()->after('plantilla_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropColumn(['tiene_plantilla', 'plantilla_path', 'plantilla_nombre']);
        });
    }
};
