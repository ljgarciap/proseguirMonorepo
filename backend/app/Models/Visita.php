<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $table = 'visitas';

    protected $fillable = [
        'fecha',
        'ciudad',
        'departamento_id',
        'ciudad_id',
        'cliente_id',
        'asistentes',
        'observaciones',
        'requiere_credito',
        
        // Credit fields
        'tipo_credito_id',
        'monto_solicitado',
        'plazo',
        'amortizacion_id',
        'destino_recurso',
        'garantia',
        'fuente_pago'
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'requiere_credito' => 'boolean',
        'monto_solicitado' => 'decimal:2',
        'plazo' => 'integer'
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function departamentoUbicacion()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function ciudadUbicacion()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    public function tipoCredito()
    {
        return $this->belongsTo(TipoCredito::class, 'tipo_credito_id');
    }

    public function amortizacion()
    {
        return $this->belongsTo(Amortizacion::class, 'amortizacion_id');
    }
}
