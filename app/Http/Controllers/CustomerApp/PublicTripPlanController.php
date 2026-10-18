<?php

namespace App\Http\Controllers\CustomerApp;

use App\Http\Controllers\Controller;
use App\Models\TripPlan;
use Illuminate\Contracts\View\View;

/**
 * The read-only page behind a plan's share link. The long random token is
 * the only credential; nothing on it can be edited and the owner can turn
 * the link off at any time.
 */
class PublicTripPlanController extends Controller
{
    public function show(string $token, TripPlannerController $planController): View
    {
        $plan = TripPlan::query()->where('share_token', $token)->firstOrFail();

        return view('customer-app-trip-plan-show', $planController->planViewData($plan) + [
            'guest' => null,
            'readonly' => true,
            'shareUrl' => null,
        ]);
    }
}
