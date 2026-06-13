<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\DispenserService;
use App\Services\FinancialService;
use App\Services\OtpService;
use App\Services\PaymentProviderFactory;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService     $transactionService,
        private readonly OtpService             $otpService,
        private readonly PaymentProviderFactory $providerFactory,
        private readonly DispenserService       $dispenserService,
        private readonly FinancialService       $financialService,
    ) {}

    // ---------------------------------------------------------------
    // T05 — POST /api/v1/transactions/initiate
    // ---------------------------------------------------------------
    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kiosk_id'     => 'required|integer|exists:kiosks,id',
            'card_account' => 'required|string|size:16|regex:/^\d{16}$/',
            'card_expire'  => 'required|string|regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
            'service_id'   => 'required|integer',
        ]);

        try {
            $tx = $this->transactionService->initiate(
                $data,
                $request->get('__auth_agent_id')
            );
        } catch (\DomainException $e) {
            $errorMap = [
                'INVALID_CARD'          => ['code' => 'INVALID_CARD',   'msg' => 'Karta raqami noto\'g\'ri formatda',    'status' => 422],
                'CARD_EXPIRED'          => ['code' => 'CARD_EXPIRED',   'msg' => 'Kartaning amal qilish muddati tugagan', 'status' => 422],
                'CARD_EXPIRE_FORMAT'    => ['code' => 'INVALID_CARD',   'msg' => 'Karta muddati formati noto\'g\'ri',     'status' => 422],
            ];

            $err = $errorMap[$e->getMessage()] ?? ['code' => 'VALIDATION_ERROR', 'msg' => $e->getMessage(), 'status' => 422];

            return response()->json([
                'error'   => $err['code'],
                'message' => $err['msg'],
            ], $err['status']);
        }

        return response()->json([
            'transaction_id'       => $tx->id,
            'agent_transaction_id' => $tx->agent_transaction_id,
            'status'               => $tx->status,
            'masked_card'          => $tx->card_account,
        ]);
    }

    // ---------------------------------------------------------------
    // T06 — POST /api/v1/transactions/{id}/send-otp
    // ---------------------------------------------------------------
    public function sendOtp(Request $request, int $id): JsonResponse
    {
        $tx = Transaction::findOrFail($id);

        if ($tx->status !== 'INITIATED') {
            return response()->json([
                'error'   => 'INVALID_STATUS',
                'message' => "Tranzaksiya holati 'INITIATED' bo'lishi kerak",
            ], 409);
        }

        try {
            $result = $this->otpService->generateAndSend($tx);
        } catch (\DomainException $e) {
            return match ($e->getMessage()) {
                'OTP_RESEND_LIMIT' => response()->json([
                    'error'   => 'OTP_RESEND_LIMIT',
                    'message' => 'OTP qayta yuborish limiti oshib ketdi',
                ], 429),
                'SMS_ERROR' => response()->json([
                    'error'   => 'SMS_ERROR',
                    'message' => 'SMS yuborishda xatolik yuz berdi, qayta urinib ko\'ring',
                ], 503),
                default => response()->json(['error' => $e->getMessage()], 400),
            };
        }

        return response()->json([
            'status'      => 'OTP_SENT',
            'phone_masked' => $result['phone_masked'],
            'expires_in'  => 180,
        ]);
    }

    // ---------------------------------------------------------------
    // T07 — POST /api/v1/transactions/{id}/verify-otp
    // ---------------------------------------------------------------
    public function verifyOtp(Request $request, int $id): JsonResponse
    {
        $request->validate(['otp_code' => 'required|string|size:6']);

        $tx = Transaction::findOrFail($id);

        try {
            $this->otpService->verify($tx, $request->otp_code);
        } catch (\DomainException $e) {
            return match ($e->getMessage()) {
                'INVALID_OTP' => response()->json([
                    'error'        => 'INVALID_OTP',
                    'message'      => 'OTP kod noto\'g\'ri',
                    'attempts_left' => max(0, 3 - $tx->fresh()->otp_attempts),
                ], 400),
                'OTP_EXPIRED' => response()->json([
                    'error'   => 'OTP_EXPIRED',
                    'message' => 'OTP kodning muddati tugadi, qayta urinib ko\'ring',
                ], 400),
                'TRANSACTION_BLOCKED' => response()->json([
                    'error'       => 'TRANSACTION_BLOCKED',
                    'message'     => 'Ko\'p urinish: tranzaksiya bekor qilindi',
                    'retry_after' => 900,
                ], 423),
                default => response()->json(['error' => $e->getMessage()], 400),
            };
        }

        return response()->json(['status' => 'OTP_VERIFIED']);
    }

    // ---------------------------------------------------------------
    // T08 — POST /api/v1/transactions/{id}/check
    // ---------------------------------------------------------------
    public function check(Request $request, int $id): JsonResponse
    {
        $request->validate(['amount' => 'required|integer']);

        $tx = Transaction::findOrFail($id);

        if ($tx->status !== 'OTP_VERIFIED') {
            return response()->json(['error' => 'INVALID_STATUS'], 409);
        }

        try {
            $result = $this->dispenserService->check($tx, (int) $request->amount);
        } catch (\DomainException $e) {
            $map = [
                'AMOUNT_TOO_LOW'      => [422, 'Minimal yechish summasi 10,000 so\'m'],
                'AMOUNT_TOO_HIGH'     => [422, 'Maksimal yechish summasi 5,000,000 so\'m'],
                'AMOUNT_NOT_MULTIPLE' => [422, 'Summa 1,000 so\'m karralilarida bo\'lishi kerak'],
                'INSUFFICIENT_DISPENSER' => [422, 'Kiosk kassasida yetarli naqd pul mavjud emas'],
                'INSUFFICIENT_CARD_BALANCE' => [402, 'Kartada yetarli mablag\' mavjud emas'],
                'PROVIDER_TIMEOUT'    => [504, 'Payment Provider javob bermadi'],
            ];
            [$httpCode, $msg] = $map[$e->getMessage()] ?? [422, $e->getMessage()];
            return response()->json(['error' => $e->getMessage(), 'message' => $msg], $httpCode);
        }

        return response()->json([
            'status'                  => 'CHECK_PENDING',
            'check_transaction_id'    => $result['check_transaction_id'],
            'available_denominations' => $result['available_denominations'],
        ]);
    }

    // ---------------------------------------------------------------
    // T09 — POST /api/v1/transactions/{id}/pay
    // ---------------------------------------------------------------
    public function pay(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'sms_code'             => 'required|string',
            'check_transaction_id' => 'required|integer',
        ]);

        $tx = Transaction::findOrFail($id);

        if ($tx->status !== 'PAY_PENDING') {
            return response()->json(['error' => 'INVALID_STATUS'], 409);
        }

        try {
            $result = $this->dispenserService->pay($tx, $request->sms_code);
        } catch (\DomainException $e) {
            $map = [
                'PAYMENT_DECLINED'  => [402, 'To\'lov amalga oshmadi, kassirga murojaat qiling'],
                'PROVIDER_TIMEOUT'  => [504, 'Payment Provider javob bermadi'],
                'DISPENSER_ERROR'   => [503, 'Dispenser texnik xatoligi yuz berdi'],
            ];
            [$httpCode, $msg] = $map[$e->getMessage()] ?? [500, $e->getMessage()];
            $extra = $e->getMessage() === 'DISPENSER_ERROR'
                ? ['incident_code' => 'DSP-ERR-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT)]
                : [];
            return response()->json(array_merge(['error' => $e->getMessage(), 'message' => $msg], $extra), $httpCode);
        }

        return response()->json([
            'status'                  => 'COMPLETED',
            'provider_transaction_id' => $result['provider_transaction_id'],
            'amount_dispensed'        => $result['amount_dispensed'],
            'receipt_number'          => 'RCP-' . now()->format('Y') . '-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    // ---------------------------------------------------------------
    // T10 — GET /api/v1/transactions/{id}/status
    // ---------------------------------------------------------------
    public function status(int $id): JsonResponse
    {
        $tx = Transaction::findOrFail($id);

        return response()->json([
            'transaction_id'       => $tx->id,
            'agent_transaction_id' => $tx->agent_transaction_id,
            'status'               => $tx->status,
            'amount'               => $tx->amount,
            'commission'           => $tx->commission,
            'created_at'           => $tx->created_at,
            'updated_at'           => $tx->updated_at,
        ]);
    }

    // ---------------------------------------------------------------
    // T10 — GET /api/v1/transactions (search)
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query();

        if ($agentTxnId = $request->query('agent_transaction_id')) {
            $query->where('agent_transaction_id', $agentTxnId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }
        if ($kioskId = $request->query('kiosk_id')) {
            $query->where('kiosk_id', $kioskId);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $result  = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($result);
    }

    // ---------------------------------------------------------------
    // T15 — POST /api/v1/transactions/{id}/refund (ADMIN only)
    // ---------------------------------------------------------------
    public function refund(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason'       => 'required|string|max:500',
            'initiated_by' => 'required|string',
        ]);

        $tx = Transaction::findOrFail($id);

        if ($tx->status === 'REFUNDED') {
            return response()->json([
                'error'   => 'ALREADY_REFUNDED',
                'message' => 'Ushbu tranzaksiya allaqachon qaytarilgan',
            ], 409);
        }

        if (!in_array($tx->status, ['COMPLETED', 'DISPENSE_FAILED'], true)) {
            return response()->json([
                'error'   => 'INVALID_STATUS',
                'message' => 'Refund faqat COMPLETED yoki DISPENSE_FAILED tranzaksiyalar uchun',
            ], 409);
        }

        try {
            $refundTxnId = $this->financialService->processRefund(
                $tx,
                $request->reason,
                $request->initiated_by
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'status'               => 'REFUNDED',
            'refund_amount'        => $tx->amount,
            'refund_transaction_id' => $refundTxnId,
        ]);
    }
}
