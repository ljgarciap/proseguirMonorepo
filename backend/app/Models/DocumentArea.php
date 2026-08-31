<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentArea extends Model
{
    protected $fillable = ['nombre', 'codigo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(DocumentEnvioStep::class, 'area_id');
    }

    /**
     * SCRUM-311 (§7.1-7.4, campo "rol"): resuelve un nombre legible para un
     * código de rol/área. Cubre roles del módulo sin fila en document_areas
     * (ej. 'superadmin', 'coordinador_comercial', que sí pueden originar un
     * envío pese a no ser áreas de ruta) con un fallback capitalizado.
     */
    public static function etiqueta(?string $codigo): string
    {
        if (!$codigo) {
            return '—';
        }

        return static::where('codigo', $codigo)->value('nombre')
            ?: ucfirst(str_replace('_', ' ', $codigo));
    }
}
