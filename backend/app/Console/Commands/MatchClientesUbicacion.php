<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Ciudad;
use Illuminate\Console\Command;

class MatchClientesUbicacion extends Command
{
    protected $signature = 'app:match-clientes-ubicacion {--dry-run : Solo mostrar el resultado del match, sin escribir nada}';

    protected $description = 'Reconcilia los campos de texto libre departamento/ciudad de clientes existentes (SCRUM-118) contra el catálogo nuevo de departamentos/ciudades, completando departamento_id/ciudad_id cuando el match es inequívoco. No borra ni modifica los campos de texto originales.';

    /** @var array<string,array<string,int>> normalizado ciudad -> [departamento_id => ciudad_id] */
    private array $indiceCiudades = [];

    /** @var array<string,int> normalizado departamento -> id */
    private array $indiceDepartamentos = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se va a escribir nada en la base de datos.');
        }

        $this->construirIndices();

        $clientes = Cliente::where(function ($q) {
            $q->whereNull('departamento_id')->orWhereNull('ciudad_id');
        })->where(function ($q) {
            $q->whereNotNull('departamento')->orWhereNotNull('ciudad');
        })->get();

        $matched = 0;
        $ambiguos = 0;
        $sinMatch = 0;

        foreach ($clientes as $cliente) {
            $resultado = $this->matchear($cliente->departamento, $cliente->ciudad);

            if ($resultado === null) {
                $sinMatch++;
                $this->line("  [sin match] cliente #{$cliente->id}: dep=[{$cliente->departamento}] ciu=[{$cliente->ciudad}]");
                continue;
            }

            if ($resultado === 'ambiguo') {
                $ambiguos++;
                $this->line("  [ambiguo] cliente #{$cliente->id}: dep=[{$cliente->departamento}] ciu=[{$cliente->ciudad}] — coincide con más de un departamento/ciudad, requiere revisión manual");
                continue;
            }

            [$departamentoId, $ciudadId] = $resultado;
            $this->info("  [match] cliente #{$cliente->id}: dep=[{$cliente->departamento}] ciu=[{$cliente->ciudad}] -> departamento_id={$departamentoId}, ciudad_id={$ciudadId}");

            if (!$dryRun) {
                $cliente->update(['departamento_id' => $departamentoId, 'ciudad_id' => $ciudadId]);
            }

            $matched++;
        }

        $this->newLine();
        $this->info("Resumen: {$matched} " . ($dryRun ? 'a actualizar' : 'actualizados') . ", {$ambiguos} ambiguos (sin tocar), {$sinMatch} sin match (sin tocar).");

        if ($ambiguos > 0 || $sinMatch > 0) {
            $this->warn('Los registros ambiguos o sin match conservan su texto libre original — hay que corregirlos manualmente desde el formulario de Clientes.');
        }

        return self::SUCCESS;
    }

    private function construirIndices(): void
    {
        foreach (Departamento::all() as $departamento) {
            $this->indiceDepartamentos[$this->normalizar($departamento->nombre)] = $departamento->id;
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
    }

    /**
     * @return array{0:int,1:int}|'ambiguo'|null
     */
    private function matchear(?string $departamentoRaw, ?string $ciudadRaw)
    {
        if (!$ciudadRaw) {
            return null;
        }

        // Casos tipo "Bucaramanga - Santander": separar ciudad y pista de departamento.
        $pistaDepartamento = $departamentoRaw;
        $ciudadTexto = $ciudadRaw;
        if (preg_match('/^(.+?)\s*[-\/,]\s*(.+)$/', $ciudadRaw, $m)) {
            $ciudadTexto = $m[1];
            $pistaDepartamento = $pistaDepartamento ?: $m[2];
        }

        $departamentoId = null;
        if ($pistaDepartamento) {
            $departamentoId = $this->indiceDepartamentos[$this->normalizar($pistaDepartamento)] ?? null;
        }

        $clavesCiudad = array_unique([
            $this->normalizar($ciudadTexto),
            $this->normalizarSinEspacios($ciudadTexto),
        ]);

        $candidatos = [];
        foreach ($clavesCiudad as $clave) {
            if (isset($this->indiceCiudades[$clave])) {
                $candidatos = $this->indiceCiudades[$clave];
                break;
            }
        }

        if (empty($candidatos)) {
            return null;
        }

        // Si ya sabemos el departamento, exigimos que la ciudad exista ahí.
        if ($departamentoId !== null) {
            if (isset($candidatos[$departamentoId])) {
                return [$departamentoId, $candidatos[$departamentoId]];
            }
            // La ciudad no pertenece al departamento indicado: datos inconsistentes, no adivinar.
            return 'ambiguo';
        }

        // Sin departamento conocido: solo aceptamos si la ciudad es inequívoca en todo el país.
        if (count($candidatos) === 1) {
            $depId = array_key_first($candidatos);
            return [$depId, $candidatos[$depId]];
        }

        return 'ambiguo';
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
        // "Bogotá D.C." / "BOGOTA DC" -> "BOGOTA", para machear con el uso común sin sufijo.
        $s = preg_replace('/\s*D\.?\s*C\.?$/', '', $s);
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function normalizarSinEspacios(string $s): string
    {
        return str_replace(' ', '', $this->normalizar($s));
    }
}
