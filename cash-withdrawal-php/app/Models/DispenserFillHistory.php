<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispenserFillHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['kiosk_id', 'denomination', 'quantity_added', 'filled_by', 'filled_at', 'notes'];
}
