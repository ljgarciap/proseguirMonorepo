<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContableGasto extends Model
{
    protected $fillable = [
        'import_batch_id', 'source_banco_id', 'unique_hash', 'fecha', 'comprobante_contable', 'no_factura', 'nit', 
        'tercero', 'concepto', 'valor', 'cta_contable', 'observaciones'
    ];

    public function importBatch() { return $this->belongsTo(ContableImport::class, 'import_batch_id'); }
}
