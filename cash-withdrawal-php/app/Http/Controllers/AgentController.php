<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentDeposit;
use App\Models\AgentReward;
use App\Models\DepositMovement;
use App\Models\ReconciliationReport;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    // ---------------------------------------------------------------
    // T14 — GET /api/v1/agents/{id}/deposit/balance
    // ---------------------------------------------------------------
    public function depositBalance(Request $request, int $id): JsonResponse
    {
        $this->authorizeAgentAccess($request, $id);

        $deposit = AgentDeposit::where('agent_id', $id)->firstOrFail();

        return response()->json([
            'agent_id'       => $id,
            'balance'        => $deposit->balance,
            'total_credited' => $deposit->total_credited,
            'total_debited'  => $deposit->total_debited,
            'as_of'          => now()->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------
    // T14 — GET /api/v1/agents/{id}/deposit/movements
    // ---------------------------------------------------------------
    public function depositMovements(Request $request, int $id): JsonResponse
    {
        $this->authorizeAgentAccess($request, $id);

        $deposit = AgentDeposit::where('agent_id', $id)->firstOrFail();

        $query = DepositMovement::where('agent_deposit_id', $deposit->id);

        if ($type = $request->query('type')) {
            $query->where('type', strtoupper($type));
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
    // T21 — GET /api/v1/agents/{id}/rewards
    // ---------------------------------------------------------------
    public function rewards(Request $request, int $id): JsonResponse
    {
        $this->authorizeAgentAccess($request, $id);

        $rewards = AgentReward::where('agent_id', $id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json(['agent_id' => $id, 'rewards' => $rewards]);
    }

    // ---------------------------------------------------------------
    // T19 — GET /api/v1/agents/{id}/reconciliation
    // ---------------------------------------------------------------
    public function reconciliation(Request $request, int $id): JsonResponse
    {
        $this->authorizeAgentAccess($request, $id);

        $date   = $request->query('date', now()->subDay()->format('Y-m-d'));
        $report = ReconciliationReport::where('agent_id', $id)
            ->where('report_date', $date)
            ->firstOrFail();

        return response()->json([
            'agent_id'           => $id,
            'date'               => $report->report_date,
            'total_transactions' => $report->total_transactions,
            'success_count'      => $report->success_count,
            'failed_count'       => $report->failed_count,
            'total_amount'       => $report->total_amount,
            'total_commission'   => $report->total_commission,
            'discrepancy_count'  => $report->discrepancy_count,
            'download_pdf'       => "/api/v1/agents/{$id}/reconciliation/download?date={$date}&format=pdf",
            'download_csv'       => "/api/v1/agents/{$id}/reconciliation/download?date={$date}&format=csv",
        ]);
    }

    // ---------------------------------------------------------------
    // T21 — GET /api/v1/agents/{id}/dashboard  (Talab 12 AC 5)
    // ---------------------------------------------------------------
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $this->authorizeAgentAccess($request, $id);

        $agent   = Agent::findOrFail($id);
        $deposit = AgentDeposit::where('agent_id', $id)->first();

        $currentMonth = now();

        $totalAmount = Transaction::where('agent_id', $id)
            ->where('status', 'COMPLETED')
            ->whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->sum('amount');

        $txCount = Transaction::where('agent_id', $id)
            ->where('status', 'COMPLETED')
            ->whereYear('created_at', $currentMonth->year)
            ->whereMonth('created_at', $currentMonth->month)
            ->count();

        $estimatedReward = (int) round($totalAmount * (float) $agent->reward_rate);

        return response()->json([
            'agent_id'         => $id,
            'balance'          => $deposit?->balance ?? 0,
            'current_month'    => [
                'total_transactions' => $txCount,
                'total_amount'       => $totalAmount,
                'estimated_reward'   => $estimatedReward,
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------

    private function authorizeAgentAccess(Request $request, int $agentId): void
    {
        $role    = $request->get('__auth_role');
        $authId  = (int) $request->get('__auth_agent_id');

        if ($role === 'ADMIN') {
            return; // ADMIN can access any agent
        }

        if ($authId !== $agentId) {
            abort(403, 'Bu ma\'lumotlarga kirish huquqi yo\'q');
        }
    }
}
