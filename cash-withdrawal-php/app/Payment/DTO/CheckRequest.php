<?php

namespace App\Payment\DTO;

readonly class CheckRequest
{
    public function __construct(
        public int    $amount,
        public int    $serviceId,
        public string $agentTransactionId,
        public string $cardExpire,
        public string $requestorPhone,
        public string $account,         // masked card number
    ) {}
}
