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

class PaynetAdapter implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $merchantId;
    private string $secretKey;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl    = config('payment.paynet.base_url');
        $this->merchantId = config('payment.paynet.merchant_id');
        $this->secretKey  = config('payment.paynet.secret_key');
        $this->timeout    = config('payment.paynet.timeout', 30);
    }

    public function check(CheckRequest $request): CheckResponse
    {
        if (config('payment.sandbox_mode')) {
            return new CheckResponse(true, random_int(1000000000, 9999999999));
        }

        // PAYNET uses different field naming — map internal DTO to PAYNET format
        $payload = [
            'merchant_id'  => $this->merchantId,
            'amount'       => $request->amount,
            'serviceId'    => $request->serviceId,
            'extId'        => $request->agentTransactionId,
            'card_expire'  => $request->cardExpire,
            'phone'        => $request->requestorPhone,
            'account'      => $request->account,
            'signature'    => $this->sign($request->agentTransactionId . $request->amount),
        ];

        $response = $this->withRetry(fn () => $this->post('/check', $payload));

        Log::info('PAYNET check', ['request' => $payload, 'response' => $response]);

        if (($response['status'] ?? '') !== 'ok') {
            return new CheckResponse(
                false,
                null,
                $response['error_code'] ?? 'PROVIDER_ERROR',
                $response['message'] ?? ''
            );
        }

        return new CheckResponse(true, (int) $response['paynet_id']);
    }

    public function pay(PayRequest $request): PayResponse
    {
        if (config('payment.sandbox_mode')) {
            return new PayResponse(true, random_int(1000000000, 9999999999));
        }

        $payload = [
            'merchant_id'  => $this->merchantId,
            'paynet_id'    => $request->checkTransactionId,
            'currency'     => $request->currencyId,
            'sms_code'     => $request->smsCode,
            'signature'    => $this->sign($request->checkTransactionId . $request->smsCode),
        ];

        $response = $this->withRetry(fn () => $this->post('/pay', $payload));

        Log::info('PAYNET pay', ['request' => $payload, 'response' => $response]);

        if (($response['status'] ?? '') !== 'ok') {
            return new PayResponse(
                false,
                null,
                $response['error_code'] ?? 'PROVIDER_ERROR',
                $response['message'] ?? ''
            );
        }

        return new PayResponse(true, (int) $response['transaction_id']);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            'merchant_id'    => $this->merchantId,
            'transaction_id' => $request->transactionId,
            'amount'         => $request->amount,
            'reason'         => $request->reason,
            'signature'      => $this->sign($request->transactionId . $request->amount),
        ];

        $response = $this->withRetry(fn () => $this->post('/refund', $payload));

        Log::info('PAYNET refund', ['request' => $payload, 'response' => $response]);

        if (($response['status'] ?? '') !== 'ok') {
            return new RefundResponse(false, '', $response['error_code'] ?? 'PROVIDER_ERROR');
        }

        return new RefundResponse(true, (string) $response['refund_id']);
    }

    public function getStatus(string $transactionId): TransactionStatusResponse
    {
        $response = $this->post('/status', [
            'merchant_id'    => $this->merchantId,
            'transaction_id' => $transactionId,
        ]);

        $statusMap = [
            'PAID'    => 'COMPLETED',
            'FAILED'  => 'FAILED',
            'PENDING' => 'PENDING',
        ];

        return new TransactionStatusResponse(
            $statusMap[$response['state'] ?? ''] ?? 'PENDING',
            isset($response['transaction_id']) ? (int) $response['transaction_id'] : null,
        );
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secretKey);
    }

    private function post(string $endpoint, array $payload): array
    {
        $response = Http::withHeaders([
                'X-Merchant-Id' => $this->merchantId,
                'Content-Type'  => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . $endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('PROVIDER_HTTP_ERROR: ' . $response->status());
        }

        return $response->json() ?? [];
    }

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
