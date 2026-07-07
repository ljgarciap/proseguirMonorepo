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
        Schema::table('document_envios', function (Blueprint $table) {
            // Marca los envíos creados por la migración de datos desde internal_documents
            // (SCRUM-94). Null para los envíos creados normalmente por el flujo nuevo.
            // Sirve para hacer el comando de migración idempotente y trazable.
            $table->string('legacy_batch_key')->nullable()->unique()->after('current_step_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_envios', function (Blueprint $table) {
            $table->dropColumn('legacy_batch_key');
        });
    }
};
