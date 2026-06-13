<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['transaction_id', 'from_status', 'to_status', 'note', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
