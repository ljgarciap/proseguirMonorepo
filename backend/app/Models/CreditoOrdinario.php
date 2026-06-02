<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditoOrdinario extends Model
{
    use HasFactory;

    protected $table = 'credito_ordinarios';

    protected $fillable = [
        'numero_solicitud',
        'cliente_id',
        'monto',
        'plazo_meses',
        'estado',
        'documentos',
        'historial_estados'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documentos' => 'array',
            'historial_estados' => 'array',
            'monto' => 'decimal:2',
            'plazo_meses' => 'integer'
        ];
    }

    /**
     * Relación con el Cliente (User)
     */
    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }
}
