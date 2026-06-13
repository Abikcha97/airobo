<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentDeposit extends Model
{
    public $timestamps = false;

    protected $fillable = ['agent_id', 'balance', 'total_credited', 'total_debited'];

    public function movements(): HasMany
    {
        return $this->hasMany(DepositMovement::class);
    }
}
