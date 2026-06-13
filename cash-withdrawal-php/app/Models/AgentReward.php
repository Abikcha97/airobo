<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentReward extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id', 'year', 'month', 'total_turnover',
        'reward_amount', 'reward_rate', 'status', 'calculated_at', 'credited_at',
    ];
}
