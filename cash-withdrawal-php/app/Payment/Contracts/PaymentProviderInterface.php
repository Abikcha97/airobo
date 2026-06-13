<?php

namespace App\Payment\Contracts;

use App\Payment\DTO\CheckRequest;
use App\Payment\DTO\CheckResponse;
use App\Payment\DTO\PayRequest;
use App\Payment\DTO\PayResponse;
use App\Payment\DTO\RefundRequest;
use App\Payment\DTO\RefundResponse;
use App\Payment\DTO\TransactionStatusResponse;

interface PaymentProviderInterface
{
    public function check(CheckRequest $request): CheckResponse;

    public function pay(PayRequest $request): PayResponse;

    public function refund(RefundRequest $request): RefundResponse;

    public function getStatus(string $transactionId): TransactionStatusResponse;
}
