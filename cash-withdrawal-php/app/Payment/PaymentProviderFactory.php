<?php

namespace App\Payment;

use App\Payment\Adapters\OsonAdapter;
use App\Payment\Adapters\PaynetAdapter;
use App\Payment\Contracts\PaymentProviderInterface;

class PaymentProviderFactory
{
    public function make(): PaymentProviderInterface
    {
        return match (strtolower(config('payment.provider', 'oson'))) {
            'paynet' => new PaynetAdapter(),
            default  => new OsonAdapter(),
        };
    }
}
