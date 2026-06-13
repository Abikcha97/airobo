<?php

namespace App\Services;

use App\Models\DispenserInventory;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Payment\DTO\CheckRequest;
use App\Payment\DTO\PayRequest;
use App\Payment\PaymentProviderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispenserService
{
    private const MIN_AMOUNT  = 10000;
    private const MAX_AMOUNT  = 5000000;
    private const MULTIPLE_OF = 1000;

    public function __construct(
        private readonly PaymentProviderFactory $factory,
        private readonly NotificationService    $notificationService,
    ) {}

    // ----------------------------------------------------------------
    // T08 — Check Request
    // ----------------------------------------------------------------

    /**
     * Validate amount, check dispenser inventory, then call Payment Provider Check.
     *
     * @return array{check_transaction_id: int, available_denominations: int[]}
     * @throws \DomainException
     */
    public function check(Transaction $tx, int $amount): array
    {
        $this->validateAmount($amount);

        $denominations = $this->getAvailableDenominations($tx->kiosk_id, $amount);

        if (empty($denominations)) {
            throw new \DomainException('INSUFFICIENT_DISPENSER');
        }

        $tx->update([
            'amount' => $amount,
            'status' => 'CHECK_PENDING',
        ]);

        TransactionLog::create([
            'transaction_id' => $tx->id,
            'from_status'    => 'OTP_VERIFIED',
            'to_status'      => 'CHECK_PENDING',
            'note'           => "Check request for amount {$amount}",
        ]);

        $provider = $this->factory->make();

        $checkReq = new CheckRequest(
            amount:               $amount,
            serviceId:            $tx->service_id,
            agentTransactionId:   $tx->agent_transaction_id,
            cardExpire:           $tx->card_expire,
            requestorPhone:       $tx->requestor_phone ?? '',
            account:              $tx->card_account,
        );

        $checkResp = $provider->check($checkReq);

        // Log provider call
        TransactionLog::create([
            'transaction_id' => $tx->id,
            'from_status'    => 'CHECK_PENDING',
            'to_status'      => $checkResp->success ? 'PAY_PENDING' : 'CHECK_FAILED',
            'note'           => 'Provider check response',
            'metadata'       => json_encode(['response' => (array) $checkResp]),
        ]);

        if (!$checkResp->success) {
            $errorCode = $checkResp->errorCode;
            $status    = $errorCode === 'INSUFFICIENT_BALANCE' ? 'CHECK_FAILED' : 'CHECK_FAILED';
            $tx->update(['status' => $status, 'failure_reason' => $errorCode]);

            if ($errorCode === 'INSUFFICIENT_BALANCE') {
                throw new \DomainException('INSUFFICIENT_CARD_BALANCE');
            }

            throw new \DomainException('CHECK_FAILED');
        }

        $tx->update([
            'check_transaction_id' => $checkResp->checkTransactionId,
            'status'               => 'PAY_PENDING',
        ]);

        return [
            'check_transaction_id'    => $checkResp->checkTransactionId,
            'available_denominations' => array_keys($denominations),
        ];
    }

    // ----------------------------------------------------------------
    // T09 — Pay + Dispense
    // ----------------------------------------------------------------

    /**
     * Send Pay Request to provider, then dispense cash.
     *
     * @return array{provider_transaction_id: int, amount_dispensed: int}
     * @throws \DomainException
     */
    public function pay(Transaction $tx, string $smsCode): array
    {
        $provider = $this->factory->make();

        $payReq = new PayRequest(
            transactionId:      $tx->id,
            currencyId:         $tx->currency_id ?? 0,
            checkTransactionId: $tx->check_transaction_id,
            smsCode:            $smsCode,
        );

        $payResp = $provider->pay($payReq);

        TransactionLog::create([
            'transaction_id' => $tx->id,
            'from_status'    => 'PAY_PENDING',
            'to_status'      => $payResp->success ? 'DISPENSING' : 'FAILED',
            'note'           => 'Provider pay response',
            'metadata'       => json_encode(['response' => (array) $payResp]),
        ]);

        if (!$payResp->success) {
            $tx->update([
                'status'         => 'FAILED',
                'failure_reason' => $payResp->errorCode,
            ]);
            throw new \DomainException($payResp->errorCode === 'DECLINED' ? 'PAYMENT_DECLINED' : 'PAYMENT_DECLINED');
        }

        $tx->update([
            'provider_transaction_id' => $payResp->providerTransactionId,
            'status'                  => 'DISPENSING',
        ]);

        // Dispense cash via TCP
        $this->dispense($tx);

        return [
            'provider_transaction_id' => $payResp->providerTransactionId,
            'amount_dispensed'        => $tx->amount,
        ];
    }

    // ----------------------------------------------------------------
    // T13 — Dispenser TCP Client
    // ----------------------------------------------------------------

    /**
     * Send dispense command to the kiosk dispenser via TCP.
     * On success: transition to COMPLETED.
     * On error: transition to DISPENSE_FAILED and trigger auto-refund.
     * On timeout: transition to DISPENSE_TIMEOUT.
     */
    private function dispense(Transaction $tx): void
    {
        $host    = config('dispenser.tcp_host', '127.0.0.1');
        $port    = (int) config('dispenser.tcp_port', 9000);
        $timeout = (int) config('dispenser.timeout', 60);

        try {
            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                10
            );

            if (!$socket) {
                throw new \RuntimeException("Cannot connect to dispenser: {$errstr}");
            }

            stream_set_timeout($socket, $timeout);

            $command = json_encode([
                'action'         => 'DISPENSE',
                'transaction_id' => $tx->id,
                'amount'         => $tx->amount,
            ]) . "\n";

            fwrite($socket, $command);

            $response = fgets($socket, 1024);
            fclose($socket);

            $result = json_decode($response, true);

            if (($result['status'] ?? '') === 'SUCCESS') {
                // Decrement inventory atomically
                $this->decrementInventory($tx->kiosk_id, $tx->amount);

                // Transition to COMPLETED
                $tx->update(['status' => 'COMPLETED']);

                TransactionLog::create([
                    'transaction_id' => $tx->id,
                    'from_status'    => 'DISPENSING',
                    'to_status'      => 'COMPLETED',
                    'note'           => 'Cash dispensed successfully',
                    'metadata'       => json_encode($result),
                ]);

                // Check low inventory alert
                $this->checkLowInventoryAlert($tx->kiosk_id);
                return;
            }

            throw new \RuntimeException('Dispenser returned failure: ' . json_encode($result));
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed')) {
                $tx->update([
                    'status'         => 'DISPENSE_TIMEOUT',
                    'failure_reason' => 'DISPENSE_TIMEOUT',
                ]);

                TransactionLog::create([
                    'transaction_id' => $tx->id,
                    'from_status'    => 'DISPENSING',
                    'to_status'      => 'DISPENSE_TIMEOUT',
                    'note'           => 'Dispenser did not respond within 60s',
                ]);

                $this->notificationService->sendDispenserTimeout($tx);
                throw new \DomainException('DISPENSER_ERROR');
            }

            $tx->update([
                'status'         => 'DISPENSE_FAILED',
                'failure_reason' => 'DISPENSE_FAILED',
            ]);

            TransactionLog::create([
                'transaction_id' => $tx->id,
                'from_status'    => 'DISPENSING',
                'to_status'      => 'DISPENSE_FAILED',
                'note'           => $e->getMessage(),
            ]);

            $this->notificationService->sendDispenseFailed($tx);

            // Trigger auto-refund job
            dispatch(new \App\Jobs\ProcessRefundJob($tx->id, 'AUTO_REFUND_DISPENSE_FAILED'));

            throw new \DomainException('DISPENSER_ERROR');
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function validateAmount(int $amount): void
    {
        if ($amount < self::MIN_AMOUNT) {
            throw new \DomainException('AMOUNT_TOO_LOW');
        }
        if ($amount > self::MAX_AMOUNT) {
            throw new \DomainException('AMOUNT_TOO_HIGH');
        }
        if ($amount % self::MULTIPLE_OF !== 0) {
            throw new \DomainException('AMOUNT_NOT_MULTIPLE');
        }
    }

    /**
     * Return available denominations sorted descending that can cover $amount.
     * Returns empty array if coverage is impossible.
     *
     * @return array<int, int>  denomination => quantity_to_use
     */
    public function getAvailableDenominations(int $kioskId, int $amount): array
    {
        $inventory = DispenserInventory::where('kiosk_id', $kioskId)
            ->where('quantity', '>', 0)
            ->orderByDesc('denomination')
            ->get();

        $remaining = $amount;
        $used      = [];

        foreach ($inventory as $row) {
            if ($remaining <= 0) {
                break;
            }

            $needed = (int) floor($remaining / $row->denomination);
            $use    = min($needed, $row->quantity);

            if ($use > 0) {
                $used[$row->denomination] = $use;
                $remaining -= $use * $row->denomination;
            }
        }

        return $remaining === 0 ? $used : [];
    }

    private function decrementInventory(int $kioskId, int $amount): void
    {
        $denominations = $this->getAvailableDenominations($kioskId, $amount);

        DB::transaction(function () use ($kioskId, $denominations) {
            foreach ($denominations as $denomination => $quantity) {
                DispenserInventory::where('kiosk_id', $kioskId)
                    ->where('denomination', $denomination)
                    ->decrement('quantity', $quantity);
            }
        });
    }

    private function checkLowInventoryAlert(int $kioskId): void
    {
        $lowItems = DispenserInventory::where('kiosk_id', $kioskId)
            ->whereColumn('quantity', '<', 'min_threshold')
            ->get();

        foreach ($lowItems as $item) {
            $this->notificationService->sendLowDispenserAlert($kioskId, $item->denomination, $item->quantity);

            if ($item->quantity === 0) {
                // Set kiosk to MAINTENANCE
                \App\Models\Kiosk::where('id', $kioskId)->update(['status' => 'MAINTENANCE']);
            }
        }
    }
}
