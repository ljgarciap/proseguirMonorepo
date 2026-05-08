<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cc = \App\Models\DocumentType::where('codigo', 'CC')->first()->id;

        // 1. Superadmin
        User::updateOrCreate(
            ['numero_documento' => '1234'],
            [
                'name' => 'Super Administrador',
                'email' => 'admin@test.com',
                'password' => Hash::make('1234'),
                'tipo_documento_id' => $cc,
                'roles' => ['superadmin']
            ]
        );

        // 2. Cliente
        User::updateOrCreate(
            ['numero_documento' => '2345'],
            [
                'name' => 'Cliente de Prueba',
                'email' => 'cliente@test.com',
                'password' => Hash::make('2345'),
                'tipo_documento_id' => $cc,
                'roles' => ['cliente']
            ]
        );

        // 3. Operativo
        User::updateOrCreate(
            ['numero_documento' => '3456'],
            [
                'name' => 'Operativo de Factoring',
                'email' => 'operativo@test.com',
                'password' => Hash::make('3456'),
                'tipo_documento_id' => $cc,
                'roles' => ['operativo']
            ]
        );

        // 4. Gerente
        User::updateOrCreate(
            ['numero_documento' => '4567'],
            [
                'name' => 'Gerencia Financiera',
                'email' => 'gerente@test.com',
                'password' => Hash::make('4567'),
                'tipo_documento_id' => $cc,
                'roles' => ['gerente']
            ]
        );

        // 5. Multi-role User
        User::updateOrCreate(
            ['numero_documento' => '5678'],
            [
                'name' => 'Usuario Multiperfil',
                'email' => 'multi@test.com',
                'password' => Hash::make('5678'),
                'tipo_documento_id' => $cc,
                'roles' => ['gerente', 'operativo']
            ]
        );
    }
}
