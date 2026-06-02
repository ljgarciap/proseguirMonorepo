<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('internal_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('archivo_path');
        });

        // Backfill existing rows with the original name derived from the stored path
        DB::table('internal_documents')->whereNotNull('archivo_path')->update([
            'original_name' => DB::raw("SUBSTRING_INDEX(archivo_path, '/', -1)")
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_documents', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};
?>
