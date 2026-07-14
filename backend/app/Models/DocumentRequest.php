<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'creado_por',
        'solicitud_credito_id',
        'estado'
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function solicitudCredito()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_credito_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function items()
    {
        return $this->hasMany(DocumentRequestItem::class);
    }
}
