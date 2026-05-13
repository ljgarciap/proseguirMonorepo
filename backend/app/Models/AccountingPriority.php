<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPriority extends Model
{
    protected $fillable = ['nombre', 'color', 'horas_vencimiento'];
}
