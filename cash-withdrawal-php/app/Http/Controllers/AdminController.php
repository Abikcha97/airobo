<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentDeposit;
use App\Models\Kiosk;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ---------------------------------------------------------------
    // T22 — GET /api/v1/admin/transactions
    // ---------------------------------------------------------------
    public function transactions(Request $request): JsonResponse
    {
        $query = Transaction::query();

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }
        if ($kioskId = $request->query('kiosk_id')) {
            $query->where('kiosk_id', $kioskId);
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

        $perPage = min((int) $request->query('per_page', 50), 100);

        return response()->json(
            $query->orderByDesc('created_at')->paginate($perPage)
        );
    }

    // ---------------------------------------------------------------
    // T22 — GET /api/v1/admin/kiosks
    // ---------------------------------------------------------------
    public function kiosks(Request $request): JsonResponse
    {
        $kiosks = Kiosk::with(['agent:id,name', 'dispenserInventory'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($kiosks);
    }

    // ---------------------------------------------------------------
    // T22 — GET /api/v1/admin/agents
    // ---------------------------------------------------------------
    public function agents(Request $request): JsonResponse
    {
        $agents = Agent::where('is_active', true)
            ->with('deposit:agent_id,balance,total_credited,total_debited')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($agents);
    }

    // ---------------------------------------------------------------
    // T19 — GET /api/v1/admin/reports/summary
    // ---------------------------------------------------------------
    public function reportsSummary(Request $request): JsonResponse
    {
        $period = $request->query('period', 'daily');
        $date   = $request->query('date', now()->format('Y-m-d'));

        [$from, $to] = $this->periodRange($period, $date);

        $totals = Transaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                SUM(commission) as total_commission,
                SUM(CASE WHEN status = \'COMPLETED\' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = \'FAILED\' THEN 1 ELSE 0 END) as failed_count
            ')
            ->first();

        $byKiosk = Transaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('kiosk_id, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('kiosk_id')
            ->get();

        $byAgent = Transaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('agent_id')
            ->get();

        return response()->json([
            'period'             => $period,
            'from'               => $from,
            'to'                 => $to,
            'totals'             => $totals,
            'by_kiosk'           => $byKiosk,
            'by_agent'           => $byAgent,
        ]);
    }

    private function periodRange(string $period, string $date): array
    {
        $dt = new \DateTimeImmutable($date);

        return match ($period) {
            'weekly'  => [
                $dt->modify('monday this week')->format('Y-m-d 00:00:00'),
                $dt->modify('sunday this week')->format('Y-m-d 23:59:59'),
            ],
            'monthly' => [
                $dt->format('Y-m-01 00:00:00'),
                $dt->format('Y-m-t 23:59:59'),
            ],
            default   => [
                $dt->format('Y-m-d 00:00:00'),
                $dt->format('Y-m-d 23:59:59'),
            ],
        };
    }
}
