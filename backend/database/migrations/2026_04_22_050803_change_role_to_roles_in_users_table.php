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
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role');
        });
        
        // Migrate existing role to roles array
        $users = \DB::table('users')->get();
        foreach ($users as $user) {
            \DB::table('users')->where('id', $user->id)->update([
                'roles' => json_encode([$user->role])
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('cliente')->after('roles');
        });

        // Migrate back
        $users = \DB::table('users')->get();
        foreach ($users as $user) {
            $roles = json_decode($user->roles, true);
            \DB::table('users')->where('id', $user->id)->update([
                'role' => $roles[0] ?? 'cliente'
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
