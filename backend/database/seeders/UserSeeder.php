<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cc = \App\Models\DocumentType::where('codigo', 'CC')->first()->id;

        // 1. Superadmin (with all system roles for test/audit purposes)
        User::updateOrCreate(
            ['numero_documento' => '1234'],
            [
                'name' => 'Super Administrador',
                'email' => 'admin@test.com',
                'password' => '1234', // Model cast handles the hashing automatically
                'tipo_documento_id' => $cc,
                'roles' => ['operativo', 'gerente', 'superadmin', 'cliente', 'contable', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria']
            ]
        );

        // 2. Cliente
        User::updateOrCreate(
            ['numero_documento' => '2345'],
            [
                'name' => 'Cliente de Prueba',
                'email' => 'cliente@test.com',
                'password' => '2345',
                'tipo_documento_id' => $cc,
                'roles' => ['cliente']
            ]
        );

        // 3. Operativo (Dirección Administrativa)
        User::updateOrCreate(
            ['numero_documento' => '3456'],
            [
                'name' => 'Operativo de Factoring',
                'email' => 'operativo@test.com',
                'password' => '3456',
                'tipo_documento_id' => $cc,
                'roles' => ['operativo']
            ]
        );

        // 4. Gerente (Gerencia)
        User::updateOrCreate(
            ['numero_documento' => '4567'],
            [
                'name' => 'Gerencia Financiera',
                'email' => 'gerente@test.com',
                'password' => '4567',
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
                'password' => '5678',
                'tipo_documento_id' => $cc,
                'roles' => ['gerente', 'operativo']
            ]
        );

        // 6. Contable
        User::updateOrCreate(
            ['numero_documento' => '6789'],
            [
                'name' => 'Contador General',
                'email' => 'contable@test.com',
                'password' => '6789',
                'tipo_documento_id' => $cc,
                'roles' => ['contable']
            ]
        );

        // 7. Coordinador Comercial
        User::updateOrCreate(
            ['numero_documento' => '7890'],
            [
                'name' => 'Coordinador Comercial de Prueba',
                'email' => 'comercial@test.com',
                'password' => '7890',
                'tipo_documento_id' => $cc,
                'roles' => ['coordinador_comercial']
            ]
        );

        // 8. Oficial de Cumplimiento
        User::updateOrCreate(
            ['numero_documento' => '8901'],
            [
                'name' => 'Oficial de Cumplimiento de Prueba',
                'email' => 'cumplimiento@test.com',
                'password' => '8901',
                'tipo_documento_id' => $cc,
                'roles' => ['oficial_cumplimiento']
            ]
        );

        // 9. Comité de Crédito
        User::updateOrCreate(
            ['numero_documento' => '9012'],
            [
                'name' => 'Comité de Crédito de Prueba',
                'email' => 'comite@test.com',
                'password' => '9012',
                'tipo_documento_id' => $cc,
                'roles' => ['comite_credito']
            ]
        );

        // 10. Tesorería
        User::updateOrCreate(
            ['numero_documento' => '0123'],
            [
                'name' => 'Tesorería de Prueba',
                'email' => 'tesoreria@test.com',
                'password' => '0123',
                'tipo_documento_id' => $cc,
                'roles' => ['tesoreria']
            ]
        );

        // 11. Ingeniero (SCRUM-120: Informe Técnico Constructor)
        User::updateOrCreate(
            ['numero_documento' => '1123'],
            [
                'name' => 'Ingeniero de Proyectos de Prueba',
                'email' => 'ingeniero@test.com',
                'password' => '1123',
                'tipo_documento_id' => $cc,
                'roles' => ['ingeniero']
            ]
        );
    }
}
