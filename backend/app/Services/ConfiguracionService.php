<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;

class ConfiguracionService
{
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'cfg_';

    public static function get(string $clave, mixed $default = null): mixed
    {
        try {
            return Cache::remember(self::CACHE_PREFIX . $clave, self::CACHE_TTL, function () use ($clave, $default) {
                $config = Configuracion::where('clave', $clave)->first();
                return $config?->valor ?? $default;
            });
        } catch (\Exception $e) {
            return $default;
        }
    }

    public static function set(string $clave, mixed $valor, int $userId): void
    {
        Configuracion::where('clave', $clave)->update([
            'valor' => $valor,
            'updated_by' => $userId,
        ]);

        Cache::forget(self::CACHE_PREFIX . $clave);
    }

    public static function flush(): void
    {
        $claves = Configuracion::pluck('clave');
        foreach ($claves as $clave) {
            Cache::forget(self::CACHE_PREFIX . $clave);
        }
    }

    /**
     * URL del botón de acceso al sistema usada en los correos transaccionales
     * (mismo criterio ya usado por GestionCreditoController::urlIngresoSistema()
     * para SCRUM-211/215/219/224 — centralizado acá para que SCRUM-244/252 no
     * lo vuelvan a duplicar). Base configurable vía la tabla paramétrica
     * 'configuraciones' (clave URL_BASE_SISTEMA_GESTION_LIQUIDEZ), con
     * fallback a FRONTEND_URL/APP_URL solo si no hay valor cargado.
     */
    public static function urlIngresoSistema(string $returnPath = '/login'): string
    {
        $base = rtrim((string) self::get(
            'URL_BASE_SISTEMA_GESTION_LIQUIDEZ',
            env('FRONTEND_URL', config('app.url'))
        ), '/');

        if (str_starts_with($returnPath, '/login')) {
            return $base . $returnPath;
        }

        return $base . '/login?returnTo=' . urlencode($returnPath);
    }
}
