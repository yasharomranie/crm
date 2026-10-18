<?php

namespace App\Http\Controllers\CustomerApp;

use App\Domain\TripPlanner\TripPlannerException;
use App\Domain\TripPlanner\TripPlannerService;
use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\TripPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "تریپ‌پلنر" in the وب‌اپ مشتریان - a guest says how many days/hours
 * they have in Hamedan and the AI assistant (see
 * App\Domain\TripPlanner\TripPlannerService) builds a realistic,
 * day-by-day itinerary from the admin-managed "جاذبه‌های گردشگری" list.
 */
class TripPlannerController extends Controller
{
    public function __construct(private readonly TripPlannerService $planner) {}

    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return view('customer-app-trip-planner', [
            'guest' => $guest,
            'plans' => $guest->tripPlans()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:7'],
            'starts_on' => ['nullable', 'date', 'after_or_equal:today'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', Rule::in(array_column(AttractionCategory::cases(), 'value'))],
            'pace' => ['required', 'in:relaxed,balanced,packed'],
            'group_type' => ['nullable', 'in:solo,couple,family_kids,friends,seniors'],
            'has_car' => ['nullable', 'boolean'],
            'budget_level' => ['nullable', 'in:low,mid,high'],
        ]);

        try {
            $plan = $this->planner->generate($guest, [
                'days' => $data['days'],
                'starts_on' => $data['starts_on'] ?? null,
                'interests' => $data['interests'] ?? [],
                'pace' => $data['pace'],
                'group_type' => $data['group_type'] ?? null,
                'has_car' => (bool) ($data['has_car'] ?? false),
                'budget_level' => $data['budget_level'] ?? null,
            ]);
        } catch (TripPlannerException|AiProviderException $exception) {
            return back()->withErrors(['days' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('customer-app.trip-planner.show', $plan)
            ->with('success', 'برنامه سفر شما آماده شد.');
    }

    public function show(TripPlan $plan): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        abort_unless($plan->guest_id === $guest->id, 404);

        return view('customer-app-trip-plan-show', [
            'guest' => $guest,
            'plan' => $plan,
            'itemsByDay' => $plan->itemsByDay(),
        ]);
    }
}
