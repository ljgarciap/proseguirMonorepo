<?php

namespace App\Console\Commands\Concerns;

trait NormalizaTextoUbicacion
{
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
