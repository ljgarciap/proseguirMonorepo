<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudCredito extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_credito';

    protected $fillable = [
        'visita_id',
        'cliente_id',
        'usuario_registra_id',
        'tipo_credito_id',
        'proyecto',
        'monto_solicitado',
        'plazo_meses',
        'amortizacion_id',
        'destino_recurso',
        'garantia',
        'fuente_pago',
        'correo_notificacion',
        'asunto_notificacion',
        'mensaje_notificacion',
        'document_preset_id',
    ];

    protected $casts = [
        'monto_solicitado' => 'decimal:2',
        'plazo_meses' => 'integer',
        'visita_id' => 'integer',
        'cliente_id' => 'integer',
        'usuario_registra_id' => 'integer',
        'tipo_credito_id' => 'integer',
        'amortizacion_id' => 'integer',
        'document_preset_id' => 'integer',
    ];

    public function visita()
    {
        return $this->belongsTo(Visita::class, 'visita_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(User::class, 'usuario_registra_id');
    }

    public function tipoCredito()
    {
        return $this->belongsTo(TipoCredito::class, 'tipo_credito_id');
    }

    public function amortizacion()
    {
        return $this->belongsTo(Amortizacion::class, 'amortizacion_id');
    }

    public function preset()
    {
        return $this->belongsTo(DocumentPreset::class, 'document_preset_id');
    }
}
