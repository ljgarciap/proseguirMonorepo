<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_financieros', function (Blueprint $table) {
            $table->json('archivos_adjuntos')->nullable()->after('soporte_complementario');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_financieros', function (Blueprint $table) {
            $table->dropColumn('archivos_adjuntos');
        });
    }
};
