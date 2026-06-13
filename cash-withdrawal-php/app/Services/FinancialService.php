<?php

namespace App\Services;

use App\Models\AgentDeposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Payment\DTO\RefundRequest;
use App\Payment\PaymentProviderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialService
{
    public function __construct(
        private readonly PaymentProviderFactory $factory,
    ) {}

    // ----------------------------------------------------------------
    // T14 — Double-Entry Financial Recording
    // ----------------------------------------------------------------

    /**
     * Record financial movements for a COMPLETED transaction.
     * All writes are wrapped in the caller's DB transaction.
     *
     * Formula:
     *   commission = ROUND(amount × commission_rate / 100)
     *   net_credit = amount - commission
     *
     * @throws \Throwable  Rolls back if any step fails
     */
    public function recordCompletion(Transaction $tx): void
    {
        $commissionRate = (float) config('payment.commission_rate', 3.0);
        $commission     = (int) round($tx->amount * $commissionRate / 100);
        $netCredit      = $tx->amount - $commission;

        $deposit = AgentDeposit::where('agent_id', $tx->agent_id)->lockForUpdate()->first();

        if (!$deposit) {
            $deposit = AgentDeposit::create([
                'agent_id'       => $tx->agent_id,
                'balance'        => 0,
                'total_credited' => 0,
                'total_debited'  => 0,
            ]);
        }

        $balanceBefore = $deposit->balance;
        $balanceAfter  = $balanceBefore + $netCredit;

        // 1. CREDIT deposit movement
        DepositMovement::create([
            'agent_deposit_id' => $deposit->id,
            'transaction_id'   => $tx->id,
            'type'             => 'CREDIT',
            'amount'           => $netCredit,
            'balance_before'   => $balanceBefore,
            'balance_after'    => $balanceAfter,
            'description'      => "Net credit for transaction #{$tx->id}",
        ]);

        // 2. Update agent deposit balance
        $deposit->update([
            'balance'        => $balanceAfter,
            'total_credited' => $deposit->total_credited + $netCredit,
        ]);

        // 3. Update transaction commission
        $tx->update(['commission' => $commission]);
    }

    // ----------------------------------------------------------------
    // T15 — Process Refund
    // ----------------------------------------------------------------

    /**
     * Execute a refund: call the payment provider and debit the agent deposit.
     *
     * @return string  refund_transaction_id
     * @throws \DomainException
     */
    public function processRefund(Transaction $tx, string $reason, string $initiatedBy): string
    {
        return DB::transaction(function () use ($tx, $reason, $initiatedBy) {
            $provider = $this->factory->make();

            $refundReq = new RefundRequest(
                transactionId: $tx->id,
                amount:        $tx->amount,
                reason:        $reason,
            );

            $refundResp = $provider->refund($refundReq);

            TransactionLog::create([
                'transaction_id' => $tx->id,
                'from_status'    => $tx->status,
                'to_status'      => $refundResp->success ? 'REFUNDED' : 'FAILED',
                'note'           => "Refund initiated by {$initiatedBy}: {$reason}",
                'metadata'       => json_encode((array) $refundResp),
            ]);

            if (!$refundResp->success) {
                throw new \DomainException('REFUND_PROVIDER_FAILED: ' . $refundResp->errorCode);
            }

            // Debit agent deposit
            $deposit = AgentDeposit::where('agent_id', $tx->agent_id)->lockForUpdate()->first();

            if ($deposit) {
                $balanceBefore = $deposit->balance;
                $netDebit      = $tx->amount - $tx->commission; // refund the net amount
                $balanceAfter  = max(0, $balanceBefore - $netDebit);

                DepositMovement::create([
                    'agent_deposit_id' => $deposit->id,
                    'transaction_id'   => $tx->id,
                    'type'             => 'DEBIT',
                    'amount'           => $netDebit,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'description'      => "Refund for transaction #{$tx->id}",
                ]);

                $deposit->update([
                    'balance'       => $balanceAfter,
                    'total_debited' => $deposit->total_debited + $netDebit,
                ]);
            }

            $refundTxnId = 'REF-' . $tx->id . '-' . now()->format('Ymd');
            $tx->update(['status' => 'REFUNDED']);

            return $refundTxnId;
        });
    }

    // ----------------------------------------------------------------
    // T20 — Monthly Reward
    // ----------------------------------------------------------------

    /**
     * Calculate and credit monthly reward for a single agent.
     *
     * @param  \App\Models\Agent  $agent
     * @param  int  $year
     * @param  int  $month
     */
    public function creditMonthlyReward($agent, int $year, int $month): void
    {
        DB::transaction(function () use ($agent, $year, $month) {
            $turnover = Transaction::where('agent_id', $agent->id)
                ->where('status', 'COMPLETED')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('amount');

            $reward = (int) round($turnover * (float) $agent->reward_rate);

            if ($reward <= 0) {
                return;
            }

            $deposit = AgentDeposit::where('agent_id', $agent->id)->lockForUpdate()->first();

            if (!$deposit) {
                return;
            }

            $balanceBefore = $deposit->balance;
            $balanceAfter  = $balanceBefore + $reward;

            DepositMovement::create([
                'agent_deposit_id' => $deposit->id,
                'transaction_id'   => null,
                'type'             => 'CREDIT',
                'amount'           => $reward,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'description'      => "Monthly reward {$year}-{$month}",
            ]);

            $deposit->update([
                'balance'        => $balanceAfter,
                'total_credited' => $deposit->total_credited + $reward,
            ]);

            \App\Models\AgentReward::create([
                'agent_id'        => $agent->id,
                'year'            => $year,
                'month'           => $month,
                'total_turnover'  => $turnover,
                'reward_amount'   => $reward,
                'reward_rate'     => $agent->reward_rate,
                'status'          => 'CREDITED',
                'credited_at'     => now(),
            ]);
        });
    }
}
