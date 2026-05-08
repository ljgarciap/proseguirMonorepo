<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContableFactura extends Model
{
    protected $fillable = [
        'import_batch_id', 'factura', 'pedido', 'cliente', 'nombre', 'email', 'direccion', 
        'ciudad', 'telefono', 'nit', 'fecha', 'vencimiento', 'vlr_bruto', 'vlr_dcto', 
        'vlr_iva_5', 'vlr_iva_19', 'vlr_i_consumo', 'total', 'observaciones',
        'status', 'reconciled_id'
    ];

    public function importBatch() { return $this->belongsTo(ContableImport::class, 'import_batch_id'); }

    public function reconciledRecord()
    {
        return $this->belongsTo(ContableBanco::class, 'reconciled_id');
    }
}
