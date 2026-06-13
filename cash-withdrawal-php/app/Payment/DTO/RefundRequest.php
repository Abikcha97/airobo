<?php

namespace App\Payment\DTO;

readonly class RefundRequest
{
    public function __construct(
        public int    $transactionId,
        public int    $amount,
        public string $reason,
    ) {}
}
