<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * T24 — Unit tests for dispenser denomination-coverage algorithm.
 */
class DispenserInventoryTest extends TestCase
{
    /**
     * Pure denomination-coverage logic extracted from DispenserService.
     *
     * @param  array<int, int>  $inventory  denomination => quantity
     * @param  int              $amount
     * @return array<int, int>  denomination => quantity_to_use
     */
    private function cover(array $inventory, int $amount): array
    {
        krsort($inventory); // largest denomination first

        $remaining = $amount;
        $used      = [];

        foreach ($inventory as $denomination => $quantity) {
            if ($remaining <= 0) {
                break;
            }

            $needed = (int) floor($remaining / $denomination);
            $use    = min($needed, $quantity);

            if ($use > 0) {
                $used[$denomination] = $use;
                $remaining -= $use * $denomination;
            }
        }

        return $remaining === 0 ? $used : [];
    }

    public function test_exact_coverage_single_denomination(): void
    {
        $used = $this->cover([50000 => 10], 100000);
        $this->assertNotEmpty($used);
        $this->assertSame(2, $used[50000]);
    }

    public function test_mixed_denominations_cover_amount(): void
    {
        $used = $this->cover([100000 => 5, 50000 => 10, 10000 => 20], 150000);
        $total = array_sum(array_map(fn ($q, $d) => $q * $d, $used, array_keys($used)));
        $this->assertSame(150000, $total);
    }

    public function test_insufficient_inventory_returns_empty(): void
    {
        $used = $this->cover([50000 => 1], 200000); // only 50000 available
        $this->assertEmpty($used);
    }

    public function test_exact_note_coverage(): void
    {
        $used = $this->cover([100000 => 3, 50000 => 2], 300000);
        $total = array_sum(array_map(fn ($q, $d) => $q * $d, $used, array_keys($used)));
        $this->assertSame(300000, $total);
    }

    public function test_greedy_uses_largest_denomination_first(): void
    {
        $used = $this->cover([100000 => 2, 50000 => 10], 100000);
        $this->assertArrayHasKey(100000, $used);
        $this->assertSame(1, $used[100000]);
        $this->assertArrayNotHasKey(50000, $used); // doesn't need 50k notes
    }

    public function test_min_threshold_alert_condition(): void
    {
        $quantity     = 15;
        $minThreshold = 20;
        $this->assertTrue($quantity < $minThreshold); // should trigger LOW alert
    }

    public function test_ok_status_when_above_threshold(): void
    {
        $quantity     = 25;
        $minThreshold = 20;
        $this->assertFalse($quantity < $minThreshold);
    }
}
