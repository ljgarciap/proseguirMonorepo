<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentArea;

class DocumentAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['nombre' => 'Contabilidad', 'codigo' => 'contable'],
            ['nombre' => 'Gerencia', 'codigo' => 'gerente'],
            ['nombre' => 'Área Administrativa', 'codigo' => 'operativo'],
        ];

        foreach ($areas as $area) {
            // firstOrCreate: no toca 'nombre' ni 'activo' si el área ya existe —
            // este seeder corre en cada deploy y un superadmin puede haber
            // desactivado o renombrado un área manualmente desde el catálogo.
            DocumentArea::firstOrCreate(
                ['codigo' => $area['codigo']],
                ['nombre' => $area['nombre'], 'activo' => true]
            );
        }
    }
}
