<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SCRUM-245 — Registro append-only de una firma electrónica. La tabla tiene
 * triggers MySQL que rechazan UPDATE/DELETE (ver migración) — este modelo
 * refuerza lo mismo a nivel de aplicación: sin updated_at, sin exponer
 * update()/delete() en ningún controller. Crear la fila es la única
 * operación soportada; se hace siempre a través de
 * FirmaElectronicaService::firmar(), nunca con FirmaElectronica::create()
 * suelto en un controller.
 */
class FirmaElectronica extends Model
{
    const UPDATED_AT = null;

    protected $table = 'firmas_electronicas';

    protected $fillable = [
        'firmable_type',
        'firmable_id',
        'usuario_id',
        'nombre_firmante',
        'numero_documento_firmante',
        'rol_firmante',
        'metodo_validacion',
        'direccion_ip',
        'user_agent',
        'documento_path',
        'documento_hash_sha256',
        'hash_algoritmo',
    ];

    /**
     * URL absoluta del PDF firmado, resuelta con el APP_URL vigente al
     * leer — mismo criterio que CreditoOrdinario::getDocumentosAttribute()
     * (SCRUM-148: nunca hornear el APP_URL en el momento de guardar).
     */
    protected function documentoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->documento_path
                ? CreditoOrdinario::resolveStorageUrl($this->documento_path)
                : null,
        );
    }

    public function firmable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
