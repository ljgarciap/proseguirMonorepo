<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\Visita;
use App\Models\SolicitudCredito;
use Illuminate\Support\Facades\Schema;

class ColombiaGeografiaSeeder extends Seeder
{
    public function run(): void
    {
        $this->fusionarDuplicados();

        $path = __DIR__ . '/data/colombia_departamentos_ciudades.json';
        $data = json_decode(file_get_contents($path), true);

        $indiceDepartamentos = [];
        foreach (Departamento::all() as $departamento) {
            $indiceDepartamentos[$this->normalizar($departamento->nombre)] = $departamento;
        }

        foreach ($data as $item) {
            $clave = $this->normalizar($item['departamento']);
            $departamento = $indiceDepartamentos[$clave] ?? null;

            if (!$departamento) {
                $departamento = Departamento::create(['nombre' => $item['departamento']]);
                $indiceDepartamentos[$clave] = $departamento;
            }

            foreach ($item['ciudades'] as $ciudadNombre) {
                Ciudad::firstOrCreate([
                    'departamento_id' => $departamento->id,
                    'nombre' => $ciudadNombre,
                ]);
            }
        }
    }

    /**
     * SCRUM-118 (seguimiento 14/07): en el entorno de test aparecieron
     * "Caldas" y "caldas" como dos departamentos distintos — la colación de
     * esa base específica hace que `firstOrCreate(['nombre' => ...])` no
     * detecte la coincidencia por mayúsculas/minúsculas. Se corre en cada
     * deploy (idempotente, no-op si no hay duplicados): agrupa por nombre
     * normalizado, conserva la fila más antigua (id más bajo) y reasigna
     * las referencias de las demás antes de borrarlas. Luego hace lo mismo
     * a nivel de ciudad, por si la fusión de departamentos dejó ciudades
     * duplicadas dentro de un mismo departamento.
     *
     * SCRUM-151 (auditoría de datos 2026-07-23): sumado el mismo tratamiento
     * para SolicitudCredito.proyecto_departamento_id/proyecto_ciudad_id
     * (columnas de "Información del Proyecto" de Crédito Constructor,
     * agregadas el 07-17, después de que este método se escribiera el
     * 07-14). Sin esto, el FK `onDelete('set null')` dejaba esas columnas
     * en NULL silenciosamente al fusionar un duplicado — se perdía la
     * ubicación del proyecto de la solicitud sin ningún error visible.
     */
    private function fusionarDuplicados(): void
    {
        $gruposDepartamento = Departamento::orderBy('id')->get()->groupBy(fn ($d) => $this->normalizar($d->nombre));

        foreach ($gruposDepartamento as $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            $canonico = $grupo->first();
            foreach ($grupo->slice(1) as $duplicado) {
                Ciudad::where('departamento_id', $duplicado->id)->update(['departamento_id' => $canonico->id]);
                Cliente::where('departamento_id', $duplicado->id)->update(['departamento_id' => $canonico->id]);
                if (Schema::hasColumn('visitas', 'departamento_id')) {
                    Visita::where('departamento_id', $duplicado->id)->update(['departamento_id' => $canonico->id]);
                }
                if (Schema::hasColumn('solicitudes_credito', 'proyecto_departamento_id')) {
                    SolicitudCredito::where('proyecto_departamento_id', $duplicado->id)->update(['proyecto_departamento_id' => $canonico->id]);
                }
                $duplicado->delete();
            }
        }

        $gruposCiudad = Ciudad::orderBy('id')->get()->groupBy(fn ($c) => $c->departamento_id . '|' . $this->normalizar($c->nombre));

        foreach ($gruposCiudad as $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            $canonico = $grupo->first();
            foreach ($grupo->slice(1) as $duplicado) {
                Cliente::where('ciudad_id', $duplicado->id)->update(['ciudad_id' => $canonico->id]);
                if (Schema::hasColumn('visitas', 'ciudad_id')) {
                    Visita::where('ciudad_id', $duplicado->id)->update(['ciudad_id' => $canonico->id]);
                }
                if (Schema::hasColumn('solicitudes_credito', 'proyecto_ciudad_id')) {
                    SolicitudCredito::where('proyecto_ciudad_id', $duplicado->id)->update(['proyecto_ciudad_id' => $canonico->id]);
                }
                $duplicado->delete();
            }
        }
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        return strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
    }
}
