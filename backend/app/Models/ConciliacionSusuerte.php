<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConciliacionSusuerte extends Model
{
    use HasFactory;

    protected $table = 'conciliaciones_susuerte';

    protected $fillable = [
        'user_id',
        'conciliated_at',
        'total_amount',
        'matched_count',
        'generated_gastos',
        'details',
    ];

    protected $casts = [
        'conciliated_at' => 'datetime',
        'details' => 'array',
        'total_amount' => 'float',
    ];
}
