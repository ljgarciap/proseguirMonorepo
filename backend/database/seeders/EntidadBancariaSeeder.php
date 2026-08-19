<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * SCRUM-224: catálogo inicial de entidades bancarias para el Registro de
 * Transferencia Bancaria (§5.2, campo "Entidad bancaria"). Ampliable por
 * superadmin vía /parametros sin deploy.
 */
class EntidadBancariaSeeder extends Seeder
{
    public function run(): void
    {
        $entidades = [
            'Bancolombia',
            'Banco de Bogotá',
            'Davivienda',
            'BBVA Colombia',
            'Banco de Occidente',
            'Banco Popular',
            'Banco Caja Social',
            'Banco AV Villas',
            'Banco Agrario de Colombia',
            'Scotiabank Colpatria',
            'Banco GNB Sudameris',
            'Banco Falabella',
            'Banco Pichincha',
            'Nequi',
            'Daviplata',
        ];

        foreach ($entidades as $nombre) {
            \App\Models\EntidadBancaria::updateOrCreate(['nombre' => $nombre]);
        }
    }
}
