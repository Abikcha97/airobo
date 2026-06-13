<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'username', 'password_hash', 'role', 'agent_id',
        'is_active', 'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = ['locked_until' => 'datetime'];
}
