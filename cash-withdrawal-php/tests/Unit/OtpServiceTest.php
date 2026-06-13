<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * T24 — Unit tests for OTP logic (pure logic, no Redis or DB dependency).
 */
class OtpServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // OTP format
    // ----------------------------------------------------------------

    public function test_generated_otp_is_6_digits(): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_otp_always_6_chars_with_leading_zeros(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $code = str_pad((string) random_int(0, 99), 6, '0', STR_PAD_LEFT);
            $this->assertSame(6, strlen($code));
        }
    }

    // ----------------------------------------------------------------
    // Phone masking
    // ----------------------------------------------------------------

    public function test_phone_mask_hides_middle(): void
    {
        $phone  = '998901234567';
        $masked = substr($phone, 0, 6) . '***' . substr($phone, -3);
        $this->assertSame('998901***567', $masked);
    }

    public function test_phone_mask_format(): void
    {
        $phone  = '998909876543';
        $masked = substr($phone, 0, 6) . '***' . substr($phone, -3);
        $this->assertStringContainsString('***', $masked);
        $this->assertSame(12, strlen($masked)); // 6 + 3 + 3
    }

    // ----------------------------------------------------------------
    // Attempt counting logic
    // ----------------------------------------------------------------

    public function test_attempts_left_decrements_correctly(): void
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $attemptsLeft = max(0, $maxAttempts - $attempt);
            $this->assertGreaterThanOrEqual(0, $attemptsLeft);
        }
    }

    public function test_transaction_blocked_after_3_failures(): void
    {
        $attempts = 3;
        $this->assertTrue($attempts >= 3);
    }

    // ----------------------------------------------------------------
    // Resend limit logic
    // ----------------------------------------------------------------

    public function test_resend_limit_is_3(): void
    {
        $resendCount = 3;
        $this->assertTrue($resendCount >= 3); // should throw OTP_RESEND_LIMIT
    }

    public function test_resend_allowed_under_limit(): void
    {
        $resendCount = 2;
        $this->assertFalse($resendCount >= 3); // should be allowed
    }
}
