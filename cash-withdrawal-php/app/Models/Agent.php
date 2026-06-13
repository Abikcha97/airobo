<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'phone', 'email', 'inn',
        'tier', 'reward_rate', 'is_active',
    ];

    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(AgentDeposit::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(AgentReward::class);
    }
}
