<?php

namespace App\Domain\TripPlanner;

use App\Models\TripPlan;

/**
 * What a freshly made plan means to the business (timeline entry, staff
 * follow-up, reminders) - filled in by the CRM-integration step.
 */
class TripPlanFollowUp
{
    public function afterPlanCreated(TripPlan $plan): void {}
}
