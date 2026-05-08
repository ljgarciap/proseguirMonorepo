<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectores = [
            'CONSTRUCTOR',
            'FINANCIERO',
            'INDUSTRIA',
            'INGENIERIA',
            'PUBLICIDAD',
            'TRANSPORTE',
            'COMERCIO',
            'SALUD',
            'ACTIVIDADES INMOBILIARIAS',
            'TECNOLOGIA',
            'APUESTAS',
            'OTROS'
        ];

        foreach ($sectores as $nombre) {
            \App\Models\Sector::updateOrCreate(['nombre' => $nombre]);
        }
    }
}
