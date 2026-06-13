<?php

namespace Tests\Unit;

use App\Services\CardValidator;
use PHPUnit\Framework\TestCase;

class CardValidatorTest extends TestCase
{
    // ----------------------------------------------------------------
    // Luhn tests
    // ----------------------------------------------------------------

    public function test_valid_card_passes_luhn(): void
    {
        // A well-known valid Luhn number
        $this->assertTrue(CardValidator::luhn('4532015112830366'));
    }

    public function test_invalid_luhn_fails(): void
    {
        $this->assertFalse(CardValidator::luhn('1234567890123456'));
    }

    public function test_non_16_digit_fails(): void
    {
        $this->assertFalse(CardValidator::luhn('123456789012345'));   // 15 digits
        $this->assertFalse(CardValidator::luhn('12345678901234567')); // 17 digits
    }

    public function test_all_zeros_fails(): void
    {
        $this->assertFalse(CardValidator::luhn('0000000000000000'));
    }

    // ----------------------------------------------------------------
    // Masking tests
    // ----------------------------------------------------------------

    public function test_card_mask_format(): void
    {
        $masked = CardValidator::mask('8600140000005346');
        $this->assertSame('860014******5346', $masked);
    }

    public function test_mask_hides_middle_6_digits(): void
    {
        $masked = CardValidator::mask('1234567890123456');
        $this->assertSame('123456******3456', $masked);
        $this->assertStringContainsString('******', $masked);
    }

    public function test_full_card_never_in_mask(): void
    {
        $full   = '8600140000005346';
        $masked = CardValidator::mask($full);
        $this->assertStringNotContainsString('000000', $masked);
    }

    public function test_mask_throws_on_wrong_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CardValidator::mask('123456789');
    }

    // ----------------------------------------------------------------
    // Expiry tests
    // ----------------------------------------------------------------

    public function test_valid_future_expiry_passes(): void
    {
        // 12/99 is far in the future
        CardValidator::validateExpiry('12/99');
        $this->assertTrue(true); // no exception
    }

    public function test_expired_card_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CARD_EXPIRED');
        CardValidator::validateExpiry('01/20'); // year 2020
    }

    public function test_bad_format_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CardValidator::validateExpiry('13/25'); // month 13 is invalid
    }

    public function test_current_month_not_expired(): void
    {
        $expire = now()->format('m/y');
        CardValidator::validateExpiry($expire); // should not throw
        $this->assertTrue(true);
    }
}
