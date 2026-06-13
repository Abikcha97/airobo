<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_id', 'report_date', 'total_transactions', 'total_amount',
        'total_commission', 'success_count', 'failed_count', 'discrepancy_count',
        'discrepancies', 'file_path_pdf', 'file_path_csv', 'generated_at',
    ];

    protected $casts = ['discrepancies' => 'array'];
}
