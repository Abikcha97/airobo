<?php

namespace App\Payment\DTO;

readonly class PayRequest
{
    public function __construct(
        public int    $transactionId,
        public int    $currencyId,
        public int    $checkTransactionId,
        public string $smsCode,
    ) {}
}
