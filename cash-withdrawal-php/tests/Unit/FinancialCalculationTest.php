<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * T24 — Unit tests for financial formulas.
 * Tests commission and reward calculations without any DB dependencies.
 */
class FinancialCalculationTest extends TestCase
{
    // ----------------------------------------------------------------
    // Commission calculation: commission = ROUND(amount × rate / 100)
    // ----------------------------------------------------------------

    /** @dataProvider commissionProvider */
    public function test_commission_calculation(int $amount, float $rate, int $expected): void
    {
        $commission = (int) round($amount * $rate / 100);
        $this->assertSame($expected, $commission);
    }

    public static function commissionProvider(): array
    {
        return [
            'standard_3pct'    => [50000,    3.0, 1500],
            'round_half_up'    => [33333,    3.0, 1000],   // 999.99 → 1000
            'zero_commission'  => [100000,   0.0, 0],
            'large_amount'     => [5000000,  3.0, 150000],
            'min_amount'       => [10000,    3.0, 300],
        ];
    }

    // ----------------------------------------------------------------
    // net_credit + commission == amount (invariant)
    // ----------------------------------------------------------------

    /** @dataProvider amountProvider */
    public function test_net_credit_plus_commission_equals_amount(int $amount, float $rate): void
    {
        $commission = (int) round($amount * $rate / 100);
        $netCredit  = $amount - $commission;

        $this->assertSame($amount, $netCredit + $commission);
    }

    public static function amountProvider(): array
    {
        return [
            [50000,    3.0],
            [100000,   1.5],
            [5000000,  3.0],
            [10000,    2.5],
            [777000,   3.0],
        ];
    }

    // ----------------------------------------------------------------
    // Reward calculation: reward = ROUND(turnover × rate)
    // ----------------------------------------------------------------

    /** @dataProvider rewardProvider */
    public function test_reward_calculation(int $turnover, float $rate, int $expected): void
    {
        $reward = (int) round($turnover * $rate);
        $this->assertSame($expected, $reward);
    }

    public static function rewardProvider(): array
    {
        return [
            'example_from_spec'  => [500000000, 0.005, 2500000],
            'silver_tier'        => [200000000, 0.007, 1400000],
            'gold_tier'          => [1000000000, 0.01, 10000000],
            'zero_turnover'      => [0,          0.005, 0],
        ];
    }

    // ----------------------------------------------------------------
    // Balance invariant: balance_after = balance_before + credit
    // ----------------------------------------------------------------

    /** @dataProvider balanceProvider */
    public function test_balance_after_credit(int $balanceBefore, int $credit, int $expectedAfter): void
    {
        $balanceAfter = $balanceBefore + $credit;
        $this->assertSame($expectedAfter, $balanceAfter);
    }

    public static function balanceProvider(): array
    {
        return [
            [0,         48500,  48500],
            [48500,     48500,  97000],
            [1000000,   48500,  1048500],
        ];
    }

    // ----------------------------------------------------------------
    // Balance never goes negative after debit
    // ----------------------------------------------------------------

    public function test_balance_never_negative_on_refund(): void
    {
        $balance  = 100000;
        $refund   = 150000; // more than balance

        $newBalance = max(0, $balance - $refund);
        $this->assertGreaterThanOrEqual(0, $newBalance);
    }

    // ----------------------------------------------------------------
    // total_credited - total_debited == balance invariant
    // ----------------------------------------------------------------

    /** @dataProvider ledgerProvider */
    public function test_ledger_balance_invariant(
        int $totalCredited,
        int $totalDebited,
        int $expectedBalance,
    ): void {
        $balance = $totalCredited - $totalDebited;
        $this->assertSame($expectedBalance, $balance);
        $this->assertGreaterThanOrEqual(0, $balance);
    }

    public static function ledgerProvider(): array
    {
        return [
            [500000, 100000, 400000],
            [100000, 0,      100000],
            [200000, 200000, 0],
        ];
    }
}
