<?php

namespace App\Console\Commands;

use App\Models\ActaComite;
use Illuminate\Console\Command;

class RemoveOrdenDiaItemsScrum198 extends Command
{
    protected $signature = 'app:scrum-198-quitar-items-orden-dia {--dry-run : Solo mostrar qué se quitaría, sin escribir nada}';

    protected $description = 'SCRUM-198: quita retroactivamente los ítems "Presentación de solicitudes de crédito." y "Decisión de solicitudes presentadas." del Orden del día de Actas no registradas (pendiente/borrador). Solo toca ítems sin contenido escrito en Desarrollo — si ya tienen texto, se conservan y se avisa para revisión manual. No toca Actas ya aprobadas.';

    private const TEXTOS_A_QUITAR = [
        'Presentación de solicitudes de crédito.',
        'Decisión de solicitudes presentadas.',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se va a escribir nada en la base de datos.');
        }

        $actas = ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get();

        if ($actas->isEmpty()) {
            $this->info('No hay Actas pendientes/en borrador. Nada que revisar.');
            return self::SUCCESS;
        }

        $modificadas = 0;
        $sinCambios = 0;
        $conservados = 0;

        foreach ($actas as $acta) {
            // Leer crudo (getRawOriginal), NO el accessor: el accessor de
            // ActaComite::ordenDia()/desarrollo() resuelve las URLs de
            // imágenes con el APP_URL vigente al leer (mismo mecanismo que
            // CreditoOrdinario::documentos_raw, ver SCRUM-148) — escribir de
            // vuelta el valor "resuelto" hornearía ese APP_URL en el JSON
            // guardado.
            $ordenDiaRaw = json_decode($acta->getRawOriginal('orden_dia') ?? '[]', true) ?: [];
            $desarrolloRaw = json_decode($acta->getRawOriginal('desarrollo') ?? '{}', true) ?: [];

            $cambios = false;
            $huboConservado = false;

            foreach ($ordenDiaRaw as $idx => $item) {
                $textoPlano = trim(strip_tags($item['texto'] ?? ''));
                if (!in_array($textoPlano, self::TEXTOS_A_QUITAR, true)) {
                    continue;
                }

                $itemId = $item['id'] ?? null;
                $contenidoDesarrollo = trim(strip_tags($desarrolloRaw[$itemId] ?? ''));
                if ($contenidoDesarrollo !== '') {
                    $this->warn("  [conserva] Acta N.° {$acta->numero}: ítem \"{$textoPlano}\" tiene contenido en Desarrollo, no se quita — revisar a mano.");
                    $huboConservado = true;
                    continue;
                }

                unset($ordenDiaRaw[$idx]);
                if ($itemId !== null) {
                    unset($desarrolloRaw[$itemId]);
                }
                $cambios = true;
            }

            if ($huboConservado) {
                $conservados++;
            }

            if (!$cambios) {
                $sinCambios++;
                continue;
            }

            $ordenDiaRaw = array_values($ordenDiaRaw);
            $this->info(sprintf('  [%s] Acta N.° %s: quitando ítem(s) del orden del día.', $dryRun ? 'simulado' : 'modificada', $acta->numero));

            if (!$dryRun) {
                $acta->orden_dia = $ordenDiaRaw;
                $acta->desarrollo = $desarrolloRaw;
                $acta->save();
            }

            $modificadas++;
        }

        $this->newLine();
        $this->info("Resumen: {$modificadas} acta(s) " . ($dryRun ? 'a modificar' : 'modificadas') . ", {$sinCambios} sin cambios, {$conservados} con algún ítem conservado por tener contenido.");

        return self::SUCCESS;
    }
}
