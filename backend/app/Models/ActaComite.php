<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActaComite extends Model
{
    use HasFactory;

    protected $table = 'actas_comite';

    protected $fillable = [
        'numero',
        'estado',
        'fecha_reunion',
        'lugar',
        'hora_inicio',
        'hora_finalizacion',
        'asistentes',
        'orden_dia',
        'orden_dia_aprobado',
        'desarrollo',
        'observaciones_generales',
        'firmantes',
        'elaborada_por_id',
        'registrada_por_id',
        'registrada_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_reunion' => 'date',
            'asistentes' => 'array',
            'orden_dia_aprobado' => 'boolean',
            'firmantes' => 'array',
            'registrada_at' => 'datetime',
        ];
    }

    /**
     * 'lugar', 'orden_dia[].texto', 'desarrollo{}' y 'observaciones_generales'
     * son campos rich text que admiten imágenes insertadas por el editor
     * (ngx-quill). Las imágenes se guardan como ruta RELATIVA (nunca URL
     * absoluta horneada al momento de subir) y se resuelven acá con el
     * APP_URL vigente en el momento de la lectura — mismo criterio que
     * CreditoOrdinario::getDocumentosAttribute() (ver SCRUM-148: hornear el
     * APP_URL en el momento equivocado deja URLs rotas al cambiar de
     * ambiente).
     */
    protected function lugar(): Attribute
    {
        return Attribute::make(get: fn ($value) => static::resolveImagenesEnHtml($value));
    }

    protected function observacionesGenerales(): Attribute
    {
        return Attribute::make(get: fn ($value) => static::resolveImagenesEnHtml($value));
    }

    protected function ordenDia(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $items = $value !== null ? json_decode($value, true) : null;
                if (!is_array($items)) {
                    return $items;
                }
                foreach ($items as &$item) {
                    if (isset($item['texto'])) {
                        $item['texto'] = static::resolveImagenesEnHtml($item['texto']);
                    }
                }
                return $items;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    protected function desarrollo(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $items = $value !== null ? json_decode($value, true) : null;
                if (!is_array($items)) {
                    return $items;
                }
                foreach ($items as $key => $html) {
                    $items[$key] = static::resolveImagenesEnHtml($html);
                }
                return $items;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    private static function resolveImagenesEnHtml(?string $html): ?string
    {
        if (!$html) {
            return $html;
        }

        return preg_replace_callback('/(<img[^>]+src=")([^"]+)(")/i', function ($m) {
            return $m[1] . CreditoOrdinario::resolveStorageUrl($m[2]) . $m[3];
        }, $html);
    }

    public function solicitudes()
    {
        return $this->hasMany(ActaComiteSolicitud::class, 'acta_comite_id');
    }

    public function elaboradaPor()
    {
        return $this->belongsTo(User::class, 'elaborada_por_id');
    }

    public function registradaPor()
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }
}
