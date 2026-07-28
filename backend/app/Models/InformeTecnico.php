<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InformeTecnico extends Model
{
    use HasFactory;

    protected $table = 'informes_tecnicos';

    protected $fillable = [
        'credito_ordinario_id',
        'estado',
        'ventas_totales_proyecto',
        'costos',
        'invertido',
        'observaciones_ingeniero',
        'diligenciado_por_ingeniero_id',
        'diligenciado_por_ingeniero_at',
        'credito_solicitado',
        'saldos_por_recaudar_contraentrega',
        'analisis_financiacion',
        'coberturas',
        'observaciones_coordinador',
        'diligenciado_por_coordinador_id',
        'diligenciado_por_coordinador_at',
    ];

    protected function casts(): array
    {
        return [
            'ventas_totales_proyecto' => 'array',
            'costos' => 'array',
            'invertido' => 'array',
            'credito_solicitado' => 'array',
            'saldos_por_recaudar_contraentrega' => 'array',
            'analisis_financiacion' => 'array',
            'coberturas' => 'array',
            'diligenciado_por_ingeniero_at' => 'datetime',
            'diligenciado_por_coordinador_at' => 'datetime',
        ];
    }

    public function creditoOrdinario()
    {
        return $this->belongsTo(CreditoOrdinario::class, 'credito_ordinario_id');
    }

    public function ingeniero()
    {
        return $this->belongsTo(User::class, 'diligenciado_por_ingeniero_id');
    }

    public function coordinador()
    {
        return $this->belongsTo(User::class, 'diligenciado_por_coordinador_id');
    }
}
