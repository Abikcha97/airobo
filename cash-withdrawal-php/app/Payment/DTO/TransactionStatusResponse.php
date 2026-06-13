<?php

namespace App\Payment\DTO;

readonly class TransactionStatusResponse
{
    public function __construct(
        public string $status,             // COMPLETED|FAILED|PENDING
        public ?int   $providerTransactionId,
        public string $errorCode   = '',
        public string $errorMessage = '',
    ) {}
}
