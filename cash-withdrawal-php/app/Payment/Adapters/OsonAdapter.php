<?php

namespace App\Payment\Adapters;

use App\Payment\Contracts\PaymentProviderInterface;
use App\Payment\DTO\CheckRequest;
use App\Payment\DTO\CheckResponse;
use App\Payment\DTO\PayRequest;
use App\Payment\DTO\PayResponse;
use App\Payment\DTO\RefundRequest;
use App\Payment\DTO\RefundResponse;
use App\Payment\DTO\TransactionStatusResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsonAdapter implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = config('payment.oson.base_url');
        $this->apiKey  = config('payment.oson.api_key');
        $this->timeout = config('payment.oson.timeout', 30);
    }

    public function check(CheckRequest $request): CheckResponse
    {
        if (config('payment.sandbox_mode')) {
            return new CheckResponse(true, random_int(1000000000, 9999999999));
        }

        $payload = [
            'amount'               => $request->amount,
            'service_id'           => $request->serviceId,
            'agent_transaction_id' => $request->agentTransactionId,
            'params'               => [
                'card_expire'    => $request->cardExpire,
                'requestorPhone' => $request->requestorPhone,
            ],
            'account' => $request->account,
        ];

        $response = $this->withRetry(fn () => $this->post('/check', $payload));

        Log::info('OSON check', ['request' => $payload, 'response' => $response]);

        if (isset($response['error'])) {
            return new CheckResponse(
                false,
                null,
                $response['error']['code'] ?? 'PROVIDER_ERROR',
                $response['error']['message'] ?? ''
            );
        }

        return new CheckResponse(
            true,
            (int) $response['check_transaction_id'],
        );
    }

    public function pay(PayRequest $request): PayResponse
    {
        if (config('payment.sandbox_mode')) {
            return new PayResponse(true, random_int(1000000000, 9999999999));
        }

        $payload = [
            'transaction_id'      => $request->transactionId,
            'currencyId'          => $request->currencyId,
            'checkTransactionId'  => $request->checkTransactionId,
            'params'              => ['sms_code' => $request->smsCode],
        ];

        $response = $this->withRetry(fn () => $this->post('/pay', $payload));

        Log::info('OSON pay', ['request' => $payload, 'response' => $response]);

        if (isset($response['error'])) {
            return new PayResponse(
                false,
                null,
                $response['error']['code'] ?? 'PROVIDER_ERROR',
                $response['error']['message'] ?? ''
            );
        }

        return new PayResponse(
            true,
            (int) $response['transaction_id'],
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            'transaction_id' => $request->transactionId,
            'amount'         => $request->amount,
            'reason'         => $request->reason,
        ];

        $response = $this->withRetry(fn () => $this->post('/refund', $payload));

        Log::info('OSON refund', ['request' => $payload, 'response' => $response]);

        if (isset($response['error'])) {
            return new RefundResponse(false, '', $response['error']['code'] ?? 'PROVIDER_ERROR');
        }

        return new RefundResponse(true, (string) $response['refund_transaction_id']);
    }

    public function getStatus(string $transactionId): TransactionStatusResponse
    {
        $response = $this->post('/status', ['transaction_id' => $transactionId]);

        $statusMap = [
            'SUCCESS' => 'COMPLETED',
            'FAILED'  => 'FAILED',
            'PENDING' => 'PENDING',
        ];

        return new TransactionStatusResponse(
            $statusMap[$response['status'] ?? ''] ?? 'PENDING',
            isset($response['transaction_id']) ? (int) $response['transaction_id'] : null,
        );
    }

    // ----------------------------------------------------------------
    // Internal HTTP helpers
    // ----------------------------------------------------------------

    private function post(string $endpoint, array $payload): array
    {
        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . $endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('PROVIDER_HTTP_ERROR: ' . $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Execute callback with exponential backoff retry.
     * Delays: 0s, 1s, 2s, 4s (3 retries)
     */
    private function withRetry(callable $fn): array
    {
        $delays  = [0, 1000, 2000, 4000];
        $attempt = 0;

        while (true) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                if ($attempt >= count($delays) - 1) {
                    throw new \DomainException('PROVIDER_TIMEOUT');
                }

                usleep($delays[$attempt] * 1000);
                $attempt++;
            }
        }
    }
}
