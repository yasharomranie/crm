<?php

namespace App\Jobs;

use App\Domain\TripPlanner\TripPlannerService;
use App\Models\TripPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds one customer's pending trip plan off the web request (the AI call
 * can take a minute). A failure is recorded on the plan, so the customer
 * sees why instead of waiting forever, and is not retried: a retry would
 * spend another AI call on the same request.
 */
class BuildTripPlanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public int $planId) {}

    public function handle(TripPlannerService $planner): void
    {
        $plan = TripPlan::find($this->planId);

        if (! $plan) {
            return;
        }

        try {
            $planner->build($plan);
        } catch (Throwable $exception) {
            $this->recordFailure($planner, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->recordFailure(app(TripPlannerService::class), $exception);
    }

    private function recordFailure(TripPlannerService $planner, Throwable $exception): void
    {
        Log::warning('BuildTripPlanJob: plan could not be built.', [
            'plan_id' => $this->planId,
            'exception' => $exception->getMessage(),
        ]);

        $plan = TripPlan::find($this->planId);

        if ($plan && ! $plan->isReady()) {
            $planner->markFailed($plan, $exception);
        }
    }
}
