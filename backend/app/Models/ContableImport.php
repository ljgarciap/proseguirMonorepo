<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContableImport extends Model
{
    protected $fillable = ['type', 'filename', 'records_processed'];

    public function facturas() { return $this->hasMany(ContableFactura::class, 'import_batch_id'); }
    public function auxiliars() { return $this->hasMany(ContableAuxiliar::class, 'import_batch_id'); }
    public function bancos() { return $this->hasMany(ContableBanco::class, 'import_batch_id'); }
    public function gastos() { return $this->hasMany(ContableGasto::class, 'import_batch_id'); }
}
