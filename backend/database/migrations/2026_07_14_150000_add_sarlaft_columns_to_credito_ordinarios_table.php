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
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->string('sarlaft_concepto')->nullable()->after('documentos');
            $table->text('sarlaft_observaciones')->nullable()->after('sarlaft_concepto');
            $table->foreignId('sarlaft_diligenciado_por_id')->nullable()->after('sarlaft_observaciones')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('sarlaft_diligenciado_at')->nullable()->after('sarlaft_diligenciado_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credito_ordinarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sarlaft_diligenciado_por_id');
            $table->dropColumn(['sarlaft_concepto', 'sarlaft_observaciones', 'sarlaft_diligenciado_at']);
        });
    }
};
