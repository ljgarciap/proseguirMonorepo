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
        'presentacion_comite',
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

    /**
     * SCRUM-183: guarda ruta relativa del disco 'public' (no URL absoluta),
     * mismo patrón que CreditoOrdinario::getDocumentosAttribute() — resuelve
     * la URL con el APP_URL vigente al leer, no al guardar (evita el bug de
     * SCRUM-148 si el APP_URL cambia entre el momento en que se adjuntó el
     * archivo y el momento en que se lee).
     */
    public function getPresentacionComiteAttribute($value): ?string
    {
        return $value ? CreditoOrdinario::resolveStorageUrl($value) : $value;
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
