<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispenserInventory extends Model
{
    public $timestamps = false;

    protected $fillable = ['kiosk_id', 'denomination', 'quantity', 'min_threshold'];
}
