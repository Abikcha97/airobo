<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kiosk extends Model
{
    protected $fillable = [
        'agent_id', 'serial_number', 'location_name', 'address',
        'status', 'dispenser_model', 'last_seen_at',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function dispenserInventory(): HasMany
    {
        return $this->hasMany(DispenserInventory::class);
    }
}
