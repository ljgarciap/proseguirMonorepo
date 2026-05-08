<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC'],
            ['nombre' => 'Pasaporte', 'codigo' => 'PAS'],
            ['nombre' => 'Cédula de Extranjería', 'codigo' => 'CE'],
            ['nombre' => 'Tarjeta de Identidad', 'codigo' => 'TI'],
            ['nombre' => 'PEP', 'codigo' => 'PEP'],
        ];

        foreach ($types as $type) {
            \App\Models\DocumentType::updateOrCreate(['codigo' => $type['codigo']], $type);
        }
    }
}
