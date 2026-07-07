<?php

namespace App\Console\Commands;

use App\Models\DocumentArea;
use App\Models\DocumentEnvio;
use App\Models\DocumentEnvioFile;
use App\Models\DocumentEnvioStep;
use App\Models\InternalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyInternalDocuments extends Command
{
    protected $signature = 'app:migrate-legacy-internal-docs {--dry-run : Solo mostrar qué se migraría, sin escribir nada}';

    protected $description = 'Migra los internal_documents pendientes/en curso (SCRUM-94) al modelo nuevo document_envios, para que sigan siendo visibles y procesables en la Bandeja Interna rediseñada. No borra ni modifica los registros originales.';

    private const ESTADOS_A_MIGRAR = ['pendiente', 'visto', 'visto_bueno'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se va a escribir nada en la base de datos.');
        }

        $docs = InternalDocument::whereIn('estado', self::ESTADOS_A_MIGRAR)
            ->orderBy('created_at')
            ->get();

        if ($docs->isEmpty()) {
            $this->info('No hay documentos pendientes/en curso en internal_documents. Nada que migrar.');
            return self::SUCCESS;
        }

        // Agrupar por batch_id; los que no tienen batch_id son su propio envío individual.
        $grupos = $docs->groupBy(fn ($doc) => $doc->batch_id ?? 'single_' . $doc->id);

        $migrados = 0;
        $omitidos = 0;
        $sinArea = 0;

        foreach ($grupos as $legacyBatchKey => $docsDelGrupo) {
            $yaExiste = DocumentEnvio::where('legacy_batch_key', $legacyBatchKey)->exists();
            if ($yaExiste) {
                $this->line("  [omitido] {$legacyBatchKey} ya fue migrado antes.");
                $omitidos++;
                continue;
            }

            $primero = $docsDelGrupo->first();
            $area = DocumentArea::where('codigo', $primero->target_role)->first();

            if (!$area) {
                $this->error("  [sin área] {$legacyBatchKey}: no existe un document_area con codigo='{$primero->target_role}'. Corré primero DocumentAreaSeeder. Se omite este grupo.");
                $sinArea++;
                continue;
            }

            $estadoStep = $primero->estado === 'pendiente' ? 'pendiente' : 'en_proceso';
            $estadoGeneral = $estadoStep;

            $this->info(sprintf(
                '  [%s] "%s" -> área "%s", %d archivo(s), estado %s (internal_documents id: %s)',
                $dryRun ? 'simulado' : 'migrado',
                $primero->titulo,
                $area->nombre,
                $docsDelGrupo->count(),
                $estadoStep,
                $docsDelGrupo->pluck('id')->implode(',')
            ));

            if (!$dryRun) {
                DB::transaction(function () use ($primero, $docsDelGrupo, $area, $estadoStep, $estadoGeneral, $legacyBatchKey) {
                    $envio = DocumentEnvio::create([
                        'sender_id' => $primero->sender_id,
                        'titulo' => $primero->titulo,
                        'categoria_id' => $primero->categoria_id,
                        'prioridad_id' => $primero->prioridad_id,
                        'observaciones' => $primero->mensaje,
                        'estado_general' => $estadoGeneral,
                        'current_step_order' => 1,
                        'legacy_batch_key' => $legacyBatchKey,
                        'created_at' => $primero->created_at,
                        'updated_at' => $primero->updated_at,
                    ]);

                    DocumentEnvioStep::create([
                        'envio_id' => $envio->id,
                        'orden' => 1,
                        'area_id' => $area->id,
                        'estado' => $estadoStep,
                        'fecha_inicio' => $primero->created_at,
                    ]);

                    foreach ($docsDelGrupo as $doc) {
                        DocumentEnvioFile::create([
                            'envio_id' => $envio->id,
                            'path' => $doc->archivo_path,
                            'original_name' => $doc->original_name ?? basename($doc->archivo_path),
                        ]);
                    }
                });
            }

            $migrados++;
        }

        $this->newLine();
        $this->info("Resumen: {$migrados} envío(s) " . ($dryRun ? 'a migrar' : 'migrados') . ", {$omitidos} ya migrados antes, {$sinArea} sin área correspondiente.");

        if ($sinArea > 0) {
            $this->warn('Hay grupos sin área — revisá que DocumentAreaSeeder haya corrido antes de este comando.');
        }

        return self::SUCCESS;
    }
}
