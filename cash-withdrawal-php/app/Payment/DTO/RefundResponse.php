<?php

namespace App\Payment\DTO;

readonly class RefundResponse
{
    public function __construct(
        public bool   $success,
        public string $refundTransactionId = '',
        public string $errorCode          = '',
        public string $errorMessage       = '',
    ) {}
}
