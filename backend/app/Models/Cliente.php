<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'nombre',
        'identificacion',
        'ciudad',
        'sector_economico',
        'actividad_economica',
        'is_verified',
        'verification_method',

        // New fields
        'tipo_persona_id',
        'tipo_documento_id',
        'numero_documento',
        
        // Persona Natural
        'nombres',
        'primer_apellido',
        'segundo_apellido',
        'correo_electronico',
        'ocupacion',
        'telefono',
        'pais',
        'departamento',
        'direccion',

        // Persona Jurídica
        'nombre_razon_social',
        'tipo_empresa',
        'correo_electronico_empresarial',
        'pagina_web',

        // Representante Legal
        'rep_tipo_documento_id',
        'rep_numero_documento',
        'rep_nombres',
        'rep_primer_apellido',
        'rep_segundo_apellido',
        'rep_cargo',
        'rep_correo_electronico',
        'rep_telefono',

        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'is_verified' => 'boolean'
    ];

    public function tipoPersona()
    {
        return $this->belongsTo(TipoPersona::class, 'tipo_persona_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'tipo_documento_id');
    }

    public function repDocumentType()
    {
        return $this->belongsTo(DocumentType::class, 'rep_tipo_documento_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($cliente) {
            // Concatenate name for NATURAL or use razon social for JURIDICA
            $tipoPersona = $cliente->tipoPersona;
            if (!$tipoPersona && $cliente->tipo_persona_id) {
                $tipoPersona = TipoPersona::find($cliente->tipo_persona_id);
            }

            $codigo = $tipoPersona ? strtoupper($tipoPersona->codigo) : '';

            if ($codigo === 'NATURAL') {
                $parts = array_filter([$cliente->nombres, $cliente->primer_apellido, $cliente->segundo_apellido]);
                $cliente->nombre = implode(' ', $parts);
            } elseif ($codigo === 'JURIDICA') {
                $cliente->nombre = $cliente->nombre_razon_social;
            }

            if ($cliente->numero_documento) {
                $cliente->identificacion = $cliente->numero_documento;
            }
        });
    }
}
