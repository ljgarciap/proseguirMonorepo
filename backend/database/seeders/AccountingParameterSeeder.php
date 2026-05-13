<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountingParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Categorías
        $categories = [
            'Extractos Bancarios',
            'Facturas de Gasto',
            'Planilla de Nómina',
            'Impuestos',
            'Reportes Gerenciales',
            'Otros'
        ];

        foreach ($categories as $cat) {
            \App\Models\AccountingCategory::updateOrCreate(['nombre' => $cat]);
        }

        // Prioridades
        $priorities = [
            ['nombre' => 'Baja', 'color' => '#10B981', 'horas_vencimiento' => 72],
            ['nombre' => 'Media', 'color' => '#F59E0B', 'horas_vencimiento' => 24],
            ['nombre' => 'Alta', 'color' => '#EF4444', 'horas_vencimiento' => 8],
            ['nombre' => 'Crítica', 'color' => '#7C3AED', 'horas_vencimiento' => 2]
        ];

        foreach ($priorities as $pri) {
            \App\Models\AccountingPriority::updateOrCreate(['nombre' => $pri['nombre']], $pri);
        }
    }
}
