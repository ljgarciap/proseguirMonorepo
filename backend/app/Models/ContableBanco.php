<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContableBanco extends Model
{
    protected $fillable = [
        'import_batch_id', 'unique_hash', 'fecha', 'descripcion', 'sucursal', 'dcto', 'valor',
        'status', 'reconciled_id'
    ];

    public function importBatch() { return $this->belongsTo(ContableImport::class, 'import_batch_id'); }

    public function reconciledRecord()
    {
        return $this->belongsTo(ContableFactura::class, 'reconciled_id');
    }
}
