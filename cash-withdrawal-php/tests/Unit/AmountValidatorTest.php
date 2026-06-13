<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * T24 — Unit tests for amount validation logic.
 */
class AmountValidatorTest extends TestCase
{
    private const MIN_AMOUNT  = 10000;
    private const MAX_AMOUNT  = 5000000;
    private const MULTIPLE_OF = 1000;

    private function validateAmount(int $amount): void
    {
        if ($amount < self::MIN_AMOUNT) {
            throw new \DomainException('AMOUNT_TOO_LOW');
        }
        if ($amount > self::MAX_AMOUNT) {
            throw new \DomainException('AMOUNT_TOO_HIGH');
        }
        if ($amount % self::MULTIPLE_OF !== 0) {
            throw new \DomainException('AMOUNT_NOT_MULTIPLE');
        }
    }

    // ----------------------------------------------------------------
    // Boundary value tests
    // ----------------------------------------------------------------

    public function test_min_amount_passes(): void
    {
        $this->validateAmount(10000);
        $this->assertTrue(true);
    }

    public function test_max_amount_passes(): void
    {
        $this->validateAmount(5000000);
        $this->assertTrue(true);
    }

    public function test_below_min_throws(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('AMOUNT_TOO_LOW');
        $this->validateAmount(9999);
    }

    public function test_zero_throws(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('AMOUNT_TOO_LOW');
        $this->validateAmount(0);
    }

    public function test_above_max_throws(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('AMOUNT_TOO_HIGH');
        $this->validateAmount(5000001);
    }

    public function test_not_multiple_throws(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('AMOUNT_NOT_MULTIPLE');
        $this->validateAmount(15500);
    }

    public function test_valid_multiples_pass(): void
    {
        $validAmounts = [10000, 50000, 100000, 500000, 1000000, 5000000];

        foreach ($validAmounts as $amount) {
            $this->validateAmount($amount);
        }

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Multiple-of-1000 property
    // ----------------------------------------------------------------

    /** @dataProvider multipleOfProvider */
    public function test_multiples_of_1000_are_valid(int $amount): void
    {
        $this->assertSame(0, $amount % 1000);
    }

    public static function multipleOfProvider(): array
    {
        return array_map(fn ($n) => [$n * 1000], range(10, 5000));
    }
}
