<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    public function requirements()
    {
        return $this->belongsToMany(DocumentRequirement::class, 'document_preset_requirement', 'preset_id', 'requirement_id');
    }
}
