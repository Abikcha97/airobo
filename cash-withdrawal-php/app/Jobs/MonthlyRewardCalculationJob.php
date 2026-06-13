<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\FinancialService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * T20 — Monthly Reward Calculation Job
 * Scheduled: 23:59 on the last day of each month (UTC+5)
 */
class MonthlyRewardCalculationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $year,
        private readonly int $month,
    ) {}

    public function handle(FinancialService $financialService, NotificationService $notificationService): void
    {
        $agents = Agent::where('is_active', true)->get();

        foreach ($agents as $agent) {
            try {
                $financialService->creditMonthlyReward($agent, $this->year, $this->month);

                $reward = \App\Models\AgentReward::where([
                    'agent_id' => $agent->id,
                    'year'     => $this->year,
                    'month'    => $this->month,
                ])->first();

                if ($reward && $reward->reward_amount > 0) {
                    $notificationService->sendMonthlyRewardCredited(
                        $agent->id,
                        $reward->reward_amount,
                        $this->year,
                        $this->month,
                    );
                }

                Log::info('Monthly reward credited', [
                    'agent_id' => $agent->id,
                    'year'     => $this->year,
                    'month'    => $this->month,
                ]);
            } catch (\Throwable $e) {
                Log::error('Monthly reward failed for agent', [
                    'agent_id' => $agent->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
