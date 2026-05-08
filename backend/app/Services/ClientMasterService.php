<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\SystemLog;

class ClientMasterService
{
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
            return $clientByNit->identificacion;
        }

        // 3. Search by name (Fallback)
        $clientByName = Cliente::where('nombre', $nombre)->first();
        if ($clientByName) {
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
            if ($retry) return $retry->identificacion;
            throw $e;
        }

        return $nitBase;
    }
}
