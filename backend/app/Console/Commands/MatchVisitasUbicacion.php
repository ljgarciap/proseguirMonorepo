<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\NormalizaTextoUbicacion;
use App\Models\Ciudad;
use App\Models\Visita;
use Illuminate\Console\Command;

class MatchVisitasUbicacion extends Command
{
    use NormalizaTextoUbicacion;

    protected $signature = 'app:match-visitas-ubicacion {--dry-run : Solo mostrar el resultado del match, sin escribir nada}';

    protected $description = 'Reconcilia el campo de texto libre ciudad de visitas existentes (SCRUM-118, seguimiento 14/07) contra el catálogo de ciudades, completando departamento_id/ciudad_id cuando el match es inequívoco. Visita no tiene un campo departamento de texto libre, así que solo se aceptan ciudades cuyo nombre no sea ambiguo en todo el país. No borra ni modifica el campo ciudad original.';

    /** @var array<string,array<string,int>> normalizado ciudad -> [departamento_id => ciudad_id] */
    private array $indiceCiudades = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se va a escribir nada en la base de datos.');
        }

        foreach (Ciudad::all() as $ciudad) {
            $claves = array_unique([
                $this->normalizar($ciudad->nombre),
                $this->normalizarSinEspacios($ciudad->nombre),
            ]);

            foreach ($claves as $clave) {
                $this->indiceCiudades[$clave][$ciudad->departamento_id] = $ciudad->id;
            }
        }

        $visitas = Visita::where(function ($q) {
            $q->whereNull('departamento_id')->orWhereNull('ciudad_id');
        })->whereNotNull('ciudad')->where('ciudad', '!=', '')->get();

        $matched = 0;
        $ambiguos = 0;
        $sinMatch = 0;

        foreach ($visitas as $visita) {
            $claves = array_unique([
                $this->normalizar($visita->ciudad),
                $this->normalizarSinEspacios($visita->ciudad),
            ]);

            $candidatos = [];
            foreach ($claves as $clave) {
                if (isset($this->indiceCiudades[$clave])) {
                    $candidatos = $this->indiceCiudades[$clave];
                    break;
                }
            }

            if (empty($candidatos)) {
                $sinMatch++;
                $this->line("  [sin match] visita #{$visita->id}: ciu=[{$visita->ciudad}]");
                continue;
            }

            if (count($candidatos) > 1) {
                $ambiguos++;
                $this->line("  [ambiguo] visita #{$visita->id}: ciu=[{$visita->ciudad}] — existe en más de un departamento, requiere revisión manual");
                continue;
            }

            $departamentoId = array_key_first($candidatos);
            $ciudadId = $candidatos[$departamentoId];
            $this->info("  [match] visita #{$visita->id}: ciu=[{$visita->ciudad}] -> departamento_id={$departamentoId}, ciudad_id={$ciudadId}");

            if (!$dryRun) {
                $visita->update(['departamento_id' => $departamentoId, 'ciudad_id' => $ciudadId]);
            }

            $matched++;
        }

        $this->newLine();
        $this->info("Resumen: {$matched} " . ($dryRun ? 'a actualizar' : 'actualizados') . ", {$ambiguos} ambiguos (sin tocar), {$sinMatch} sin match (sin tocar).");

        if ($ambiguos > 0 || $sinMatch > 0) {
            $this->warn('Los registros ambiguos o sin match conservan su texto libre original — hay que corregirlos manualmente desde el formulario de Visitas.');
        }

        return self::SUCCESS;
    }
}
