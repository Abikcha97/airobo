<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\ReconciliationReport;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T18 — Daily Reconciliation Job
 * Scheduled: 23:59 UTC+5 every day
 */
class DailyReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $date, // Y-m-d
    ) {}

    public function handle(): void
    {
        $agents = Agent::where('is_active', true)->get();

        foreach ($agents as $agent) {
            try {
                $this->processAgent($agent, $this->date);
            } catch (\Throwable $e) {
                Log::error('Reconciliation failed for agent', [
                    'agent_id' => $agent->id,
                    'date'     => $this->date,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    private function processAgent(Agent $agent, string $date): void
    {
        $transactions = Transaction::where('agent_id', $agent->id)
            ->whereDate('created_at', $date)
            ->get();

        $totalAmount     = 0;
        $totalCommission = 0;
        $successCount    = 0;
        $failedCount     = 0;

        foreach ($transactions as $tx) {
            if ($tx->status === 'COMPLETED' || $tx->status === 'REFUNDED') {
                $totalAmount     += $tx->amount;
                $totalCommission += $tx->commission;
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        // TODO: Compare with Payment Provider report for discrepancies
        $discrepancies = [];

        ReconciliationReport::updateOrCreate(
            ['agent_id' => $agent->id, 'report_date' => $date],
            [
                'total_transactions' => $transactions->count(),
                'total_amount'       => $totalAmount,
                'total_commission'   => $totalCommission,
                'success_count'      => $successCount,
                'failed_count'       => $failedCount,
                'discrepancy_count'  => count($discrepancies),
                'discrepancies'      => json_encode($discrepancies),
                'file_path_pdf'      => $this->generatePdf($agent->id, $date),
                'file_path_csv'      => $this->generateCsv($agent->id, $date, $transactions),
            ]
        );
    }

    private function generatePdf(int $agentId, string $date): string
    {
        // Placeholder — in real implementation use TCPDF or similar
        return "storage/reconciliation/agent_{$agentId}_{$date}.pdf";
    }

    private function generateCsv(int $agentId, string $date, $transactions): string
    {
        $path = "storage/reconciliation/agent_{$agentId}_{$date}.csv";
        // In a real implementation write CSV to storage
        return $path;
    }
}
