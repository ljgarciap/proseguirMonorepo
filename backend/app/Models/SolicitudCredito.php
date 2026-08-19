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
        'proyecto_direccion',
        'proyecto_departamento_id',
        'proyecto_ciudad_id',
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

    /**
     * Solicitud de Documentos (DocumentRequest) generada a partir del
     * preset elegido al registrar esta solicitud (SCRUM-146).
     */
    /**
     * SCRUM-229: excluye explícitamente 'garantias'/'pre_comite' — sin este
     * filtro, un crédito cuyo ÚNICO DocumentRequest resultó ser el de
     * garantías (ej. creado directo sin pasar por el registro normal de
     * SolicitudCredito) hacía que este hasOne lo tomara igual (al ser el
     * único candidato) y Etapa 1 terminaba mostrando por error los
     * documentos de garantías. Datos históricos con 'etapa' NULL (previos a
     * esta columna) siguen matcheando — solo se excluyen los tageados
     * explícitamente como de otra etapa.
     */
    public function documentRequest()
    {
        // NOT IN con NULL no matchea en SQL (NULL NOT IN (...) = NULL, no
        // TRUE) — hay que permitir 'etapa' NULL explícitamente o se excluye
        // por error toda la data histórica previa a esta columna.
        return $this->hasOne(DocumentRequest::class, 'solicitud_credito_id')
            ->where(function ($q) {
                $q->whereNull('etapa')->orWhereNotIn('etapa', ['garantias', 'pre_comite']);
            });
    }

    /**
     * Solicitud de Documentos de garantías (SCRUM-193/205), generada cuando
     * el Coordinador Comercial gestiona 'aprobada_garantias' con un preset
     * (GestionCreditoController::crearSolicitudDocumentos()). Distinta de
     * documentRequest() (Etapa 1, SCRUM-146) aunque comparten la misma FK
     * solicitud_credito_id — se distinguen por la columna 'etapa'. La más
     * reciente gana si el preset se reenvió más de una vez (SCRUM-229).
     */
    public function garantiasDocumentRequest()
    {
        return $this->hasOne(DocumentRequest::class, 'solicitud_credito_id')
            ->where('etapa', 'garantias')
            ->latestOfMany('id');
    }

    /**
     * Expediente de Crédito Ordinario (workflow BPMN) originado a partir de
     * esta solicitud, si ya arrancó (SCRUM-120). Nullable: puede no existir
     * todavía si el flujo aún no fue iniciado.
     */
    public function creditoOrdinario()
    {
        return $this->hasOne(CreditoOrdinario::class, 'solicitud_credito_id');
    }

    public function proyectoDepartamento()
    {
        return $this->belongsTo(Departamento::class, 'proyecto_departamento_id');
    }

    public function proyectoCiudad()
    {
        return $this->belongsTo(Ciudad::class, 'proyecto_ciudad_id');
    }
}
