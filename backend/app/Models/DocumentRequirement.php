<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'tiene_plantilla',
        'plantilla_path',
        'plantilla_nombre'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiene_plantilla' => 'boolean'
    ];

    public function presets()
    {
        return $this->belongsToMany(DocumentPreset::class, 'document_preset_requirement', 'requirement_id', 'preset_id');
    }
}
