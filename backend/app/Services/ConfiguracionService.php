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
}
