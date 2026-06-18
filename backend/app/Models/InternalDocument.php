<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_action_at' => 'datetime',
    ];

    protected $appends = ['last_modified'];

    public function getLastModifiedAttribute()
    {
        return $this->last_action_at ?? $this->updated_at ?? $this->created_at;
    }

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
}
