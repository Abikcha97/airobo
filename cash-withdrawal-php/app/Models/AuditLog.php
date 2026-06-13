<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'old_value', 'new_value', 'ip_address',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];
}
