<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActaComiteSolicitud extends Model
{
    use HasFactory;

    protected $table = 'acta_comite_solicitudes';

    protected $fillable = [
        'acta_comite_id',
        'credito_ordinario_id',
        'origen',
        'cliente_nombre',
        'cliente_identificacion',
        'tipo_solicitud',
        'monto',
        'amortizacion',
        'plazo_meses',
        'tasa_interes',
        'porcentaje_financiacion',
        'garantias',
        'fuente_pago',
        'estado_decision',
        'monto_decision',
        'vigencia_aprobacion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tasa_interes' => 'decimal:4',
            'porcentaje_financiacion' => 'decimal:2',
            'monto_decision' => 'decimal:2',
        ];
    }

    public function actaComite()
    {
        return $this->belongsTo(ActaComite::class, 'acta_comite_id');
    }

    public function creditoOrdinario()
    {
        return $this->belongsTo(CreditoOrdinario::class, 'credito_ordinario_id');
    }
}
