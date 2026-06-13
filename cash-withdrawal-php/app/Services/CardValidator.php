<?php

namespace App\Services;

class CardValidator
{
    /**
     * Validate card number using Luhn algorithm.
     */
    public static function luhn(string $cardNumber): bool
    {
        $number = preg_replace('/\D/', '', $cardNumber);

        if (strlen($number) !== 16) {
            return false;
        }

        $sum     = 0;
        $isEven  = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($isEven) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum    += $digit;
            $isEven  = !$isEven;
        }

        return $sum % 10 === 0;
    }

    /**
     * Mask card number: 8600140000005346 → 860014******5346
     * First 6 digits open, middle 6 masked with *, last 4 open.
     */
    public static function mask(string $cardNumber): string
    {
        $number = preg_replace('/\D/', '', $cardNumber);

        if (strlen($number) !== 16) {
            throw new \InvalidArgumentException('Card number must be 16 digits');
        }

        return substr($number, 0, 6) . '******' . substr($number, -4);
    }

    /**
     * Validate card expiry in MM/YY format and not in the past.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateExpiry(string $expire): void
    {
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expire)) {
            throw new \InvalidArgumentException('CARD_EXPIRE_FORMAT');
        }

        [$month, $year] = explode('/', $expire);
        $fullYear = 2000 + (int) $year;
        $now      = new \DateTimeImmutable('now');
        $expDate  = \DateTimeImmutable::createFromFormat('Y-m-d', "{$fullYear}-{$month}-01")
                        ->modify('last day of this month')
                        ->setTime(23, 59, 59);

        if ($now > $expDate) {
            throw new \InvalidArgumentException('CARD_EXPIRED');
        }
    }
}
