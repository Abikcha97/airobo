<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private const MAX_RETRY = 2;

    private string $gatewayUrl;
    private string $token;

    public function __construct()
    {
        $this->gatewayUrl = config('services.sms.gateway_url', 'https://notify.eskiz.uz/api');
        $this->token      = config('services.sms.gateway_token', '');
    }

    // ----------------------------------------------------------------
    // T06 — OTP SMS
    // ----------------------------------------------------------------

    /**
     * @throws \DomainException on all retries exhausted
     */
    public function sendOtp(string $phone, string $code): void
    {
        $message = "Tasdiqlash kodi: {$code}. Kod 3 daqiqa amal qiladi.";
        $this->sendSms($phone, $message);
    }

    // ----------------------------------------------------------------
    // T17 — Low Dispenser Alert
    // ----------------------------------------------------------------

    public function sendLowDispenserAlert(int $kioskId, int $denomination, int $quantity): void
    {
        // Notify admin and agent
        $msg = "{$kioskId} raqamli kioskda {$denomination} so'mlik banknotalar kam: {$quantity} ta qoldi.";
        $this->notifyAdmins($msg);

        Log::warning('LOW_DISPENSER_ALERT', [
            'kiosk_id'    => $kioskId,
            'denomination' => $denomination,
            'quantity'     => $quantity,
        ]);
    }

    // ----------------------------------------------------------------
    // T13 — DISPENSE_FAILED Alert
    // ----------------------------------------------------------------

    public function sendDispenseFailed(Transaction $tx): void
    {
        $msg = "DISPENSE_FAILED: Tranzaksiya #{$tx->id} - Kiosk #{$tx->kiosk_id} - Summa: {$tx->amount} so'm. Agent debitlanadi.";
        $this->notifyAdmins($msg);
        Log::critical('DISPENSE_FAILED', ['transaction_id' => $tx->id, 'kiosk_id' => $tx->kiosk_id]);
    }

    // ----------------------------------------------------------------
    // T13 — DISPENSE_TIMEOUT Alert
    // ----------------------------------------------------------------

    public function sendDispenserTimeout(Transaction $tx): void
    {
        $msg = "DISPENSE_TIMEOUT: Tranzaksiya #{$tx->id} - Kiosk #{$tx->kiosk_id}. Manual intervensiya talab qilinadi.";
        $this->notifyAdmins($msg);
        Log::error('DISPENSE_TIMEOUT', ['transaction_id' => $tx->id]);
    }

    // ----------------------------------------------------------------
    // T20 — Monthly Reward Notification
    // ----------------------------------------------------------------

    public function sendMonthlyRewardCredited(int $agentId, int $rewardAmount, int $year, int $month): void
    {
        $msg = "Siz uchun {$year} yil {$month} oy uchun mukofot pul hisoblandi: {$rewardAmount} so'm. Depozitingizga yozildi.";
        $this->notifyAgent($agentId, $msg);
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    private function sendSms(string $phone, string $message): void
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRY; $attempt++) {
            try {
                $response = Http::withToken($this->token)
                    ->post($this->gatewayUrl . '/message/sms/send', [
                        'mobile_phone' => $phone,
                        'message'      => $message,
                        'from'         => 'CashKiosk',
                    ]);

                if ($response->successful()) {
                    return;
                }

                Log::warning('SMS send failed', [
                    'attempt'  => $attempt + 1,
                    'phone'    => $phone,
                    'response' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('SMS exception', ['attempt' => $attempt + 1, 'error' => $e->getMessage()]);
            }
        }

        throw new \DomainException('SMS_ERROR');
    }

    private function notifyAdmins(string $message): void
    {
        // In a real implementation, query admin users and send them SMS/push
        Log::info('ADMIN_NOTIFICATION', ['message' => $message]);
    }

    private function notifyAgent(int $agentId, string $message): void
    {
        // In a real implementation, look up agent phone and send SMS
        Log::info('AGENT_NOTIFICATION', ['agent_id' => $agentId, 'message' => $message]);
    }
}
