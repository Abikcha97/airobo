<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Redis;

class OtpService
{
    private const OTP_TTL          = 180;    // seconds
    private const MAX_ATTEMPTS     = 3;
    private const MAX_RESEND_COUNT = 3;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Generate a 6-digit OTP, store in Redis, and send via SMS.
     *
     * @return array{phone_masked: string}
     * @throws \DomainException
     */
    public function generateAndSend(Transaction $tx): array
    {
        if ($tx->otp_resend_count >= self::MAX_RESEND_COUNT) {
            throw new \DomainException('OTP_RESEND_LIMIT');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key  = 'otp:' . $tx->id;

        Redis::setex($key, self::OTP_TTL, json_encode([
            'code'     => $code,
            'attempts' => 0,
        ]));

        // Send SMS
        try {
            $this->notificationService->sendOtp($tx->requestor_phone, $code);
        } catch (\Throwable) {
            Redis::del($key);
            throw new \DomainException('SMS_ERROR');
        }

        $tx->increment('otp_resend_count');
        $tx->update([
            'status'         => 'OTP_SENT',
            'otp_expires_at' => now()->addSeconds(self::OTP_TTL),
        ]);

        return [
            'phone_masked' => $this->maskPhone($tx->requestor_phone ?? ''),
        ];
    }

    /**
     * Verify submitted OTP against Redis-stored code.
     *
     * @throws \DomainException  INVALID_OTP | OTP_EXPIRED | TRANSACTION_BLOCKED
     */
    public function verify(Transaction $tx, string $submittedCode): void
    {
        $key  = 'otp:' . $tx->id;
        $data = Redis::get($key);

        if (!$data) {
            throw new \DomainException('OTP_EXPIRED');
        }

        $otp = json_decode($data, true);

        // Constant-time comparison
        if (!hash_equals($otp['code'], $submittedCode)) {
            $otp['attempts']++;
            Redis::setex($key, self::OTP_TTL, json_encode($otp));

            $tx->increment('otp_attempts');

            if ($otp['attempts'] >= self::MAX_ATTEMPTS) {
                Redis::del($key);
                $tx->update(['status' => 'FAILED', 'failure_reason' => 'OTP_MAX_ATTEMPTS']);
                throw new \DomainException('TRANSACTION_BLOCKED');
            }

            throw new \DomainException('INVALID_OTP');
        }

        // Success — delete OTP from Redis
        Redis::del($key);
        $tx->update(['status' => 'OTP_VERIFIED']);
    }

    /**
     * Mask phone number: 998901234567 → 998909***900
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 9) {
            return $phone;
        }
        return substr($phone, 0, 6) . '***' . substr($phone, -3);
    }
}
