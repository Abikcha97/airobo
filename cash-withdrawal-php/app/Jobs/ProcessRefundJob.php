<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\FinancialService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int    $transactionId,
        private readonly string $reason,
    ) {}

    public function handle(FinancialService $financialService, NotificationService $notificationService): void
    {
        $tx = Transaction::find($this->transactionId);

        if (!$tx) {
            Log::error('ProcessRefundJob: transaction not found', ['id' => $this->transactionId]);
            return;
        }

        if ($tx->status === 'REFUNDED') {
            return; // already refunded
        }

        try {
            $refundTxnId = $financialService->processRefund($tx, $this->reason, 'SYSTEM_AUTO_REFUND');

            $notificationService->sendDispenseFailed($tx);

            Log::info('Auto-refund completed', [
                'transaction_id' => $this->transactionId,
                'refund_txn_id'  => $refundTxnId,
            ]);
        } catch (\Throwable $e) {
            Log::critical('Auto-refund FAILED — manual intervention required', [
                'transaction_id' => $this->transactionId,
                'error'          => $e->getMessage(),
            ]);

            // Re-throw to allow queue retry
            throw $e;
        }
    }
}
