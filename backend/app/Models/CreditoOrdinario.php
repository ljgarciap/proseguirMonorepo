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
        'solicitud_credito_id',
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
            'plazo_meses' => 'integer',
            'solicitud_credito_id' => 'integer'
        ];
    }

    /**
     * Relación con el Cliente (User)
     */
    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    /**
     * Relación con la Solicitud de Crédito que originó este expediente BPMN.
     * Nullable: expedientes creados antes de SCRUM-120 no la tienen.
     */
    public function solicitudCredito()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_credito_id');
    }

    public function informeTecnico()
    {
        return $this->hasOne(InformeTecnico::class, 'credito_ordinario_id');
    }

    public static function iniciar(
        int $clienteId,
        float $monto,
        int $plazoMeses,
        string $usuario,
        string $rol,
        string $comentario,
        ?int $solicitudCreditoId = null,
        ?string $estadoInicial = null,
        ?array $documentosIniciales = null
    ): self {
        $numeroSolicitud = 'CO-' . date('Y') . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)) . rand(10, 99);
        $estadoInicial = $estadoInicial ?? 'revision_documental';

        return self::create([
            'numero_solicitud' => $numeroSolicitud,
            'cliente_id'       => $clienteId,
            'solicitud_credito_id' => $solicitudCreditoId,
            'monto'            => $monto,
            'plazo_meses'      => $plazoMeses,
            'estado'           => $estadoInicial,
            'documentos'       => $documentosIniciales ?? self::documentosIniciales(),
            'historial_estados' => [[
                'fecha'          => now()->toIso8601String(),
                'usuario'        => $usuario,
                'rol'            => $rol,
                'estado_anterior' => 'ninguno',
                'estado_nuevo'   => $estadoInicial,
                'comentario'     => $comentario,
            ]],
        ]);
    }

    public static function documentosIniciales(): array
    {
        return [
            'formulario_solicitud'         => null,
            'documentos_identidad'         => null,
            'estados_financieros'          => null,
            'certificados_laborales'       => null,
            'sarlft_sintesis'              => null,
            'sarlft_datacredito'           => null,
            'analisis_financiero'          => null,
            'presentacion_comite'          => null,
            'acta_comite_firmada'          => null,
            'pagare_borrador'              => null,
            'carta_instrucciones_borrador' => null,
            'contrato_borrador'            => null,
            'garantias_firmadas'           => null,
            'registro_cyf'                 => null,
            'desembolso_egreso'            => null,
            'comprobante_transferencia'    => null,
        ];
    }
}
