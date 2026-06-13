<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'agent_transaction_id', 'provider_transaction_id', 'check_transaction_id',
        'kiosk_id', 'agent_id', 'service_id', 'card_account', 'card_expire',
        'requestor_phone', 'amount', 'commission', 'currency_id', 'status',
        'provider_name', 'provider_check_response', 'provider_pay_response',
        'failure_reason', 'otp_attempts', 'otp_resend_count', 'otp_expires_at',
    ];

    protected $casts = [
        'provider_check_response' => 'array',
        'provider_pay_response'   => 'array',
        'otp_expires_at'          => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(TransactionLog::class);
    }
}
