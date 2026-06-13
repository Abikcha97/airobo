<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private readonly OtpService         $otpService,
        private readonly FinancialService   $financialService,
        private readonly DispenserService   $dispenserService,
    ) {}

    // ----------------------------------------------------------------
    // T05 — Initiate
    // ----------------------------------------------------------------

    /**
     * Initiate a new transaction.
     *
     * @param  array{kiosk_id:int, card_account:string, card_expire:string, service_id:int}  $data
     * @param  int  $agentId
     * @return Transaction
     */
    public function initiate(array $data, int $agentId): Transaction
    {
        // Luhn + format validation
        if (!CardValidator::luhn($data['card_account'])) {
            throw new \DomainException('INVALID_CARD');
        }

        CardValidator::validateExpiry($data['card_expire']);   // throws CARD_EXPIRED or FORMAT error

        $maskedCard   = CardValidator::mask($data['card_account']);
        $agentTxnId   = $this->generateAgentTransactionId();

        $transaction = DB::transaction(function () use ($data, $agentId, $maskedCard, $agentTxnId) {
            $tx = Transaction::create([
                'agent_transaction_id' => $agentTxnId,
                'kiosk_id'             => $data['kiosk_id'],
                'agent_id'             => $agentId,
                'service_id'           => $data['service_id'],
                'card_account'         => $maskedCard,
                'card_expire'          => $data['card_expire'],
                'status'               => 'INITIATED',
            ]);

            TransactionLog::create([
                'transaction_id' => $tx->id,
                'from_status'    => null,
                'to_status'      => 'INITIATED',
                'note'           => 'Transaction initiated',
            ]);

            return $tx;
        });

        return $transaction;
    }

    // ----------------------------------------------------------------
    // T09 — Pay
    // ----------------------------------------------------------------

    /**
     * Transition a transaction to COMPLETED after a successful dispense.
     */
    public function complete(Transaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $tx->update(['status' => 'COMPLETED', 'updated_at' => now()]);

            TransactionLog::create([
                'transaction_id' => $tx->id,
                'from_status'    => 'DISPENSING',
                'to_status'      => 'COMPLETED',
                'note'           => 'Cash dispensed successfully',
            ]);

            // Double-entry financial recording
            $this->financialService->recordCompletion($tx);
        });
    }

    /**
     * Transition a transaction to FAILED.
     */
    public function fail(Transaction $tx, string $reason): void
    {
        $tx->update([
            'status'         => 'FAILED',
            'failure_reason' => $reason,
        ]);

        TransactionLog::create([
            'transaction_id' => $tx->id,
            'from_status'    => $tx->getOriginal('status'),
            'to_status'      => 'FAILED',
            'note'           => $reason,
        ]);
    }

    /**
     * Update transaction status and log the transition.
     */
    public function transition(Transaction $tx, string $toStatus, string $note = ''): void
    {
        $from = $tx->status;
        $tx->update(['status' => $toStatus]);

        TransactionLog::create([
            'transaction_id' => $tx->id,
            'from_status'    => $from,
            'to_status'      => $toStatus,
            'note'           => $note,
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function generateAgentTransactionId(): string
    {
        $date = now()->format('Ymd');
        return 'KSK-' . strtoupper(Str::random(8)) . '-' . $date;
    }
}
