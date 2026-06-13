<?php

namespace App\Payment\DTO;

readonly class CheckResponse
{
    public function __construct(
        public bool   $success,
        public ?int   $checkTransactionId,
        public string $errorCode   = '',
        public string $errorMessage = '',
    ) {}
}
