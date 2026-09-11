<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SCRUM-345 (rebote): anexo genérico asociado a un CreditoOrdinario — sin
 * preset ni clave predefinida, cargado internamente por el Director de
 * Crédito. Ver CreditoOrdinarioAnexoController.
 */
class CreditoOrdinarioAnexo extends Model
{
    protected $table = 'credito_ordinario_anexos';

    protected $fillable = [
        'credito_ordinario_id',
        'nombre_original',
        'path',
        'mime',
        'tamano',
        'subido_por_user_id',
        'subido_por_rol',
    ];

    protected $appends = ['url'];

    public function creditoOrdinario()
    {
        return $this->belongsTo(CreditoOrdinario::class);
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? CreditoOrdinario::resolveStorageUrl($this->path) : null;
    }
}
