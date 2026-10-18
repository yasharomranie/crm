<?php

namespace App\Http\Controllers\CustomerApp;

use App\Domain\TripPlanner\TripPlanCalendar;
use App\Domain\TripPlanner\TripPlanEditor;
use App\Domain\TripPlanner\TripPlannerException;
use App\Domain\TripPlanner\TripPlannerService;
use App\Enums\AttractionCategory;
use App\Http\Controllers\Controller;
use App\Jobs\BuildTripPlanJob;
use App\Models\Attraction;
use App\Models\Guest;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
            $plan = $this->planner->request($guest, [
                'days' => $data['days'],
                'starts_on' => $data['starts_on'] ?? null,
                'interests' => $data['interests'] ?? [],
                'pace' => $data['pace'],
                'group_type' => $data['group_type'] ?? null,
                'has_car' => (bool) ($data['has_car'] ?? false),
                'budget_level' => $data['budget_level'] ?? null,
            ]);
        } catch (TripPlannerException $exception) {
            return back()->withErrors(['days' => $exception->getMessage()])->withInput();
        }

        if ($plan->wasRecentlyCreated) {
            BuildTripPlanJob::dispatch($plan->id);
        }

        return redirect()->route('customer-app.trip-planner.show', $plan)
            ->with('success', $plan->isReady() ? 'برنامه سفر شما آماده شد.' : 'درخواست شما ثبت شد؛ برنامه در حال ساخت است.');
    }

    public function show(TripPlan $plan): View
    {
        $guest = $this->guest();
        $this->authorizePlan($plan, $guest);

        if (! $plan->isReady()) {
            return view('customer-app-trip-plan-pending', ['plan' => $plan, 'guest' => $guest]);
        }

        return view('customer-app-trip-plan-show', $this->planViewData($plan) + [
            'guest' => $guest,
            'readonly' => false,
            'shareUrl' => $plan->share_token ? route('trip-plan.public', $plan->share_token) : null,
        ]);
    }

    public function storeItem(Request $request, TripPlan $plan, TripPlanEditor $editor): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        $data = $request->validate([
            'attraction_id' => ['required', 'integer'],
            'day_number' => ['required', 'integer', 'min:1'],
        ]);

        return $this->edit($plan, (int) $data['day_number'], fn () => $editor->addItem($plan, (int) $data['attraction_id'], (int) $data['day_number']));
    }

    public function destroyItem(TripPlan $plan, TripPlanItem $item, TripPlanEditor $editor): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());
        abort_unless($item->trip_plan_id === $plan->id, 404);

        return $this->edit($plan, $item->day_number, fn () => $editor->removeItem($plan, $item));
    }

    public function moveItem(Request $request, TripPlan $plan, TripPlanItem $item, TripPlanEditor $editor): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());
        abort_unless($item->trip_plan_id === $plan->id, 404);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        return $this->edit($plan, $item->day_number, fn () => $editor->moveItem($plan, $item, $direction));
    }

    public function regenerateDay(TripPlan $plan, int $day): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        return $this->edit($plan, $day, fn () => $this->planner->regenerateDay($plan, $day));
    }

    public function rate(Request $request, TripPlan $plan): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan->update(['rating' => $data['rating'], 'feedback' => $data['feedback'] ?? null]);

        return back()->with('success', 'ممنون از بازخوردتان!');
    }

    public function share(TripPlan $plan): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        $plan->update(['share_token' => $plan->share_token ?? Str::random(32)]);

        return back()->with('success', 'لینک اشتراک ساخته شد؛ هر کس این لینک را داشته باشد می‌تواند برنامه را ببیند.');
    }

    public function unshare(TripPlan $plan): RedirectResponse
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        $plan->update(['share_token' => null]);

        return back()->with('success', 'لینک اشتراک غیرفعال شد.');
    }

    public function calendar(TripPlan $plan, TripPlanCalendar $calendar): Response
    {
        $this->authorizeReadyPlan($plan, $this->guest());

        return response($calendar->render($plan), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="hamedan-trip-plan-'.$plan->id.'.ics"',
        ]);
    }

    /**
     * Everything the plan page shows besides who is looking at it - shared
     * with the public read-only link.
     *
     * @return array<string, mixed>
     */
    public function planViewData(TripPlan $plan): array
    {
        $startsOn = ($plan->starts_on ?? $plan->created_at)->copy()->startOfDay();
        $itemsByDay = $plan->itemsByDay();
        $usedIds = $plan->items()->pluck('attraction_id');

        return [
            'plan' => $plan,
            'itemsByDay' => $itemsByDay,
            'events' => $this->planner->eventsBetween($startsOn, $startsOn->copy()->addDays($plan->days - 1)),
            'addableAttractions' => Attraction::query()->where('is_active', true)->whereNotIn('id', $usedIds)->orderBy('name')->get(),
            'bookingCheckIn' => $plan->starts_on?->toDateString(),
            'bookingCheckOut' => $plan->starts_on?->copy()->addDays($plan->days)->toDateString(),
            'activeDay' => session('active_day', $itemsByDay->keys()->first()),
        ];
    }

    private function guest(): Guest
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return $guest;
    }

    private function authorizePlan(TripPlan $plan, Guest $guest): void
    {
        abort_unless($plan->guest_id === $guest->id, 404);
    }

    private function authorizeReadyPlan(TripPlan $plan, Guest $guest): void
    {
        $this->authorizePlan($plan, $guest);
        abort_unless($plan->isReady(), 404);
    }

    private function edit(TripPlan $plan, int $dayNumber, \Closure $change): RedirectResponse
    {
        try {
            $change();
        } catch (TripPlannerException $exception) {
            return back()->withErrors(['edit' => $exception->getMessage()])->with('active_day', $dayNumber);
        }

        return back()->with('active_day', $dayNumber)->with('success', 'برنامه به‌روز شد.');
    }
}
