<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEnvio extends Model
{
    protected $fillable = [
        'sender_id', 'titulo', 'categoria_id', 'prioridad_id',
        'observaciones', 'estado_general', 'current_step_order',
        'legacy_batch_key', 'created_at', 'updated_at',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function category()
    {
        return $this->belongsTo(AccountingCategory::class, 'categoria_id');
    }

    public function priority()
    {
        return $this->belongsTo(AccountingPriority::class, 'prioridad_id');
    }

    public function files()
    {
        return $this->hasMany(DocumentEnvioFile::class, 'envio_id');
    }

    public function steps()
    {
        return $this->hasMany(DocumentEnvioStep::class, 'envio_id')->orderBy('orden');
    }

    public function currentStep()
    {
        return $this->steps()->where('orden', $this->current_step_order)->first();
    }
}
