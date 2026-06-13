<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_deposit_id', 'transaction_id', 'type',
        'amount', 'balance_before', 'balance_after', 'description',
    ];
}
