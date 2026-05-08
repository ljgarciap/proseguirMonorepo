<?php

namespace App\Services;

use App\Models\SectorMapping;
use Illuminate\Support\Str;

class IntelligentMapper
{
    /**
     * Maps an economic activity to a sector using tiered logic:
     * 1. Exact Match
     * 2. Keyword Heuristics
     * 3. Fallback
     */
    public static function mapActivityToSector(string $activity): string
    {
        $activity = trim($activity);
        if (empty($activity)) {
            return 'SIN ESPECIFICAR';
        }

        // Tier 1: Exact Match in database
        $mapping = SectorMapping::where('actividad_economica', $activity)->first();
        if ($mapping) {
            return $mapping->sector;
        }

        // Tier 2: Keyword Heuristics
        $normalized = Str::lower($activity);
        
        $heuristics = [
            'CONSTRUCTOR' => ['construc', 'vivienda', 'edific', 'obras', 'urbanas'],
            'FINANCIERO' => ['banc', 'fidu', 'finan', 'segur', 'fondos', 'pensiones', 'valores'],
            'TECNOLOGIA' => ['soft', 'tecno', 'sistem', 'digital', 'informatica', 'programacion', 'telecom'],
            'INDUSTRIA' => ['alimentos', 'manufac', 'fabri', 'industria', 'produccion', 'elaboracion'],
            'COMERCIO' => ['comercio', 'venta', 'al por mayor', 'menor', 'almacen', 'tienda'],
            'SALUD' => ['salud', 'medic', 'hospit', 'clinica', 'odontolog', 'farmac'],
            'TRANSPORTE' => ['transp', 'carga', 'pasajer', 'logistica', 'flete'],
            'ACTIVIDADES INMOBILIARIAS' => ['inmobil', 'alquiler', 'bienes raices', 'arrend'],
            'PUBLICIDAD' => ['publicid', 'market', 'propaganda', 'anuncios'],
            'APUESTAS' => ['apuest', 'azar', 'casino', 'bingo', 'loter'],
            'INGENIERIA' => ['ingenieria civil', 'proyectos de ingenieria'],
        ];

        foreach ($heuristics as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalized, $keyword)) {
                    // Level 2 match found - We COULD auto-save this to the DB to promote to Level 1, 
                    // but better to wait for manual confirmation if needed.
                    return $sector;
                }
            }
        }

        // Tier 3: Fallback
        return 'POR CATEGORIZAR';
    }
}
