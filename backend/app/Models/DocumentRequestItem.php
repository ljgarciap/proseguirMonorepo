<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'document_requirement_id',
        'client_upload_id',
        'estado',
        'observaciones',
        'nombre_personalizado',
        'descripcion_personalizada'
    ];

    // SCRUM-339: para que el frontend (etapa1Docs, Mis Cargas) no tenga que
    // repetir el fallback requirement?.nombre ?? nombre_personalizado en
    // cada pantalla — se serializa siempre junto al item.
    protected $appends = ['nombre_mostrado', 'descripcion_mostrada'];

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function requirement()
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }

    public function upload()
    {
        return $this->belongsTo(ClientUpload::class, 'client_upload_id');
    }

    /**
     * SCRUM-339: documento ad-hoc agregado por el Director de Crédito desde
     * "Solicitud de Documentos" — sin fila en el catálogo compartido
     * document_requirements (decisión de Luis: aislado a esta solicitud).
     */
    public function getNombreMostradoAttribute(): string
    {
        return $this->requirement?->nombre ?? $this->nombre_personalizado ?? 'Documento requerido';
    }

    public function getDescripcionMostradaAttribute(): ?string
    {
        return $this->requirement?->descripcion ?? $this->descripcion_personalizada;
    }
}
