<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;
use App\Models\Ciudad;

class ColombiaGeografiaSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__ . '/data/colombia_departamentos_ciudades.json';
        $data = json_decode(file_get_contents($path), true);

        foreach ($data as $item) {
            $departamento = Departamento::firstOrCreate(['nombre' => $item['departamento']]);

            foreach ($item['ciudades'] as $ciudadNombre) {
                Ciudad::firstOrCreate([
                    'departamento_id' => $departamento->id,
                    'nombre' => $ciudadNombre,
                ]);
            }
        }
    }
}
