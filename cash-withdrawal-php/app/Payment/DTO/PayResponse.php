<?php

namespace App\Payment\DTO;

readonly class PayResponse
{
    public function __construct(
        public bool   $success,
        public ?int   $providerTransactionId,
        public string $errorCode   = '',
        public string $errorMessage = '',
    ) {}
}
