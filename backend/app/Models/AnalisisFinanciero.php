<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalisisFinanciero extends Model
{
    use HasFactory;

    protected $table = 'analisis_financieros';

    protected $fillable = [
        'credito_ordinario_id',
        'estado',
        'anio_inicial',
        'cantidad_anios',
        'activo',
        'pasivo',
        'patrimonio',
        'utilidad_neta',
        'ori',
        'cartera',
        'observaciones',
        'soporte_complementario',
        'diligenciado_por_id',
        'diligenciado_por_at',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'array',
            'pasivo' => 'array',
            'patrimonio' => 'array',
            'utilidad_neta' => 'array',
            'ori' => 'array',
            'cartera' => 'array',
            'diligenciado_por_at' => 'datetime',
        ];
    }

    public function creditoOrdinario()
    {
        return $this->belongsTo(CreditoOrdinario::class, 'credito_ordinario_id');
    }

    public function diligenciadoPor()
    {
        return $this->belongsTo(User::class, 'diligenciado_por_id');
    }
}
