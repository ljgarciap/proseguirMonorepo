<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\SystemLog;

class ClientMasterService
{
    protected static array $resolvedClients = [];

    /**
     * Master a client's data.
     * Returns the master identification for the operation.
     */
    public static function masterClient($nombre, $identificacion, $extraData = [])
    {
        if (empty($nombre) || empty($identificacion)) {
            return $identificacion;
        }

        // 1. Normalize data
        $nombre = trim($nombre);
        $nitBase = preg_replace('/[^0-9]/', '', $identificacion);

        $cacheKey = $nombre . '|' . $nitBase;
        if (isset(self::$resolvedClients[$cacheKey])) {
            return self::$resolvedClients[$cacheKey];
        }
        
        // 2. SEARCH BY NIT FIRST (Unique Identifier)
        // This avoids 1062 Duplicate Entry errors
        $clientByNit = Cliente::where('identificacion', $nitBase)->first();
        if ($clientByNit) {
            // If the name is different, we log it but proceed with the Master NIT
            if (strcasecmp($clientByNit->nombre, $nombre) !== 0) {
                SystemLog::create([
                    'categoria' => 'validation',
                    'action' => 'Name Variation',
                    'message' => "Client with NIT '{$nitBase}' known as '{$clientByNit->nombre}' received variant '{$nombre}'. Using Master record.",
                    'records_processed' => 0
                ]);
            }
            self::$resolvedClients[$cacheKey] = $clientByNit->identificacion;
            return $clientByNit->identificacion;
        }

        // 3. Search by name (Fallback)
        $clientByName = Cliente::where('nombre', $nombre)->first();
        if ($clientByName) {
            self::$resolvedClients[$cacheKey] = $clientByName->identificacion;
            return $clientByName->identificacion;
        }

        // 4. Completely new client
        try {
            Cliente::create([
                'nombre' => $nombre,
                'identificacion' => $nitBase,
                'ciudad' => $extraData['ciudad'] ?? null,
                'sector_economico' => $extraData['sector_economico'] ?? null,
                'actividad_economica' => $extraData['actividad_economica'] ?? null,
                'is_verified' => true,
                'verification_method' => 'consensus'
            ]);
        } catch (\Exception $e) {
            // Final fallback: if creation fails due to race condition or hidden constraint
            $retry = Cliente::where('identificacion', $nitBase)->first();
            if ($retry) {
                self::$resolvedClients[$cacheKey] = $retry->identificacion;
                return $retry->identificacion;
            }
            throw $e;
        }

        self::$resolvedClients[$cacheKey] = $nitBase;
        return $nitBase;
    }
}
