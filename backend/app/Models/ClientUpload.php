<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientUpload extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'original_name',
        'status',
        'observations',
        'validated_by',
        'approved_by'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
