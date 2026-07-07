<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEnvioFile extends Model
{
    protected $fillable = ['envio_id', 'path', 'original_name'];

    public function envio()
    {
        return $this->belongsTo(DocumentEnvio::class, 'envio_id');
    }
}
