<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'historial_estados',
        'sarlaft_concepto',
        'sarlaft_observaciones',
        'sarlaft_diligenciado_por_id',
        'sarlaft_diligenciado_at',
        'solicitud_gestionada',
        'fecha_gestion',
        'resultado_origen',
        'gestion_detalle',
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
            'solicitud_credito_id' => 'integer',
            'sarlaft_diligenciado_por_id' => 'integer',
            'sarlaft_diligenciado_at' => 'datetime',
            'solicitud_gestionada' => 'boolean',
            'fecha_gestion' => 'datetime',
            'gestion_detalle' => 'array',
        ];
    }

    /**
     * Accessor legacy (get{Column}Attribute): tiene prioridad sobre el cast
     * 'array' de $casts, así que decodificamos el JSON acá nosotros mismos.
     * Los uploads guardan solo la ruta relativa dentro del disco 'public'
     * (ver CreditoOrdinarioController/ListasRestrictivasSarlaftController);
     * la URL absoluta se resuelve recién acá, con el APP_URL vigente en el
     * momento de la lectura — así un documento subido en un ambiente no
     * queda con el dominio de otro horneado en BD (SCRUM-148: el fallback
     * de TEST.env a PROD.env en el deploy de test horneaba URLs con el
     * dominio de producción para archivos que solo existían en test).
     */
    public function getDocumentosAttribute($value)
    {
        $documentos = $value !== null ? json_decode($value, true) : null;

        return is_array($documentos)
            ? array_map([$this, 'resolveDocumentoValue'], $documentos)
            : $documentos;
    }

    private function resolveDocumentoValue($valor)
    {
        if (is_array($valor)) {
            return array_map([$this, 'resolveDocumentoValue'], $valor);
        }

        return $valor ? self::resolveStorageUrl($valor) : $valor;
    }

    /**
     * Variante "cruda" de $credito->documentos (sin resolver a URL) para los
     * controladores que hacen lectura-modificación-guardado del JSON
     * completo (transition(), guardarCamposYArchivo()): si esos flujos
     * releyeran la versión ya resuelta a URL absoluta, cada transición
     * volvería a hornear el APP_URL vigente en TODOS los campos del
     * documento, no solo en el que se está subiendo — reintroduciendo el
     * bug de SCRUM-148 en el siguiente deploy o cambio de ambiente.
     */
    public function getDocumentosRawAttribute(): ?array
    {
        $value = $this->attributes['documentos'] ?? null;

        return $value !== null ? json_decode($value, true) : null;
    }

    /**
     * Acepta tanto una ruta relativa nueva ('credito_documentos/13/x.pdf')
     * como una URL absoluta legacy horneada con un APP_URL previo, y
     * devuelve siempre la URL pública resuelta con el disco 'public'
     * actual.
     */
    public static function resolveStorageUrl(string $valor): string
    {
        if (str_starts_with($valor, 'http://') || str_starts_with($valor, 'https://')) {
            $marcador = '/storage/';
            $pos = strpos($valor, $marcador);
            if ($pos === false) {
                return $valor;
            }
            $valor = rawurldecode(substr($valor, $pos + strlen($marcador)));
        }

        return Storage::disk('public')->url($valor);
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

    public function analisisFinanciero()
    {
        return $this->hasOne(AnalisisFinanciero::class, 'credito_ordinario_id');
    }

    /**
     * Usuario (Oficial de Cumplimiento) que diligenció el concepto de
     * Listas Restrictivas y SARLAFT (SCRUM-128).
     */
    public function sarlaftDiligenciadoPor()
    {
        return $this->belongsTo(User::class, 'sarlaft_diligenciado_por_id');
    }

    /**
     * Solicitudes de Acta de Comité en las que apareció este crédito
     * (SCRUM-169/178). Puede haber más de una si el crédito volvió a
     * evaluarse en una acta posterior — Gestión de Créditos usa la más
     * reciente con acta ya registrada para resolver fecha/observaciones
     * de la decisión del Comité.
     */
    public function actaComiteSolicitudes()
    {
        return $this->hasMany(ActaComiteSolicitud::class, 'credito_ordinario_id');
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
            'sintesis_oficial_cumplimiento' => null,
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
