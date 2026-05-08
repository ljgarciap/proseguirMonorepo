<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'nombre',
        'codigo'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
