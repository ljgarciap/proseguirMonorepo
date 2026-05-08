<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContableAuxiliar extends Model
{
    protected $fillable = [
        'import_batch_id', 'source_factura_id', 'source_banco_id', 'unique_hash', 'fecha', 'comprobante', 'tercero', 'documento', 
        'detalle', 'centro_costos', 'nit', 'base_local', 'debito_local', 'credito_local', 'saldo_local'
    ];

    public function importBatch() { return $this->belongsTo(ContableImport::class, 'import_batch_id'); }
}
