<?php

namespace App\Domain\TripPlanner;

use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Models\AdmissionTicket;
use App\Models\AiProvider;
use App\Models\Attraction;
use App\Models\Event;
use App\Models\Guest;
use App\Models\TripPlan;
use App\Models\TripPlannerSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

/**
 * Turns "من N روز در همدان هستم" into a realistic, day-by-day itinerary.
 *
 * The AI (the same AiProvider the staff assistant uses, but as one
 * structured-JSON request with no tools) proposes; TripPlanNormalizer
 * decides. Whatever the model returns is forced into a schedule that fits
 * opening hours, closed weekdays and travel time, and if the AI is
 * missing, down, or returns nothing usable, RuleBasedTripPlanner builds
 * the plan instead - so a guest always gets a plan or a clear reason.
 *
 * Grounding: only attractions in the database are offered (and only those
 * open for the trip's dates), shortlisted to the most relevant ones to
 * keep the request small; the guest's own bookings/tickets and the events
 * of those days are given as context; every attraction's admin_note plus
 * the global TripPlannerSetting::assistant_note go into the prompt, so
 * staff can steer the result without a deploy.
 */
class TripPlannerService
{
    private const SHORTLIST_SIZE = 30;

    private const DUPLICATE_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly TripPlanNormalizer $normalizer,
        private readonly RuleBasedTripPlanner $ruleBasedPlanner,
        private readonly TripPlanFollowUp $followUp,
    ) {}

    /**
     * @param  array{days: int, starts_on?: ?string, interests?: array<int, string>, pace: string, group_type?: ?string, has_car?: bool, budget_level?: ?string}  $input
     */
    public function generate(Guest $guest, array $input): TripPlan
    {
        $settings = TripPlannerSetting::current();

        if (! $settings->is_enabled) {
            throw TripPlannerException::disabled();
        }

        $input = $this->cleanInput($input);
        $fingerprint = $this->fingerprint($guest, $input);

        $recent = $guest->tripPlans()
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->latest('id')
            ->first();

        if ($recent) {
            return $recent;
        }

        if ($guest->tripPlans()->where('created_at', '>=', now()->subDay())->count() >= $settings->plans_per_day_limit) {
            throw TripPlannerException::dailyLimitReached($settings->plans_per_day_limit);
        }

        $startsOn = $input['starts_on'] ? Carbon::parse($input['starts_on'])->startOfDay() : now()->startOfDay();
        $days = $input['days'];
        $endsOn = $startsOn->copy()->addDays($days - 1);

        $eligible = $this->eligibleAttractions($startsOn, $endsOn);

        if ($eligible->isEmpty()) {
            throw TripPlannerException::noEligibleAttractions();
        }

        $shortlist = $this->shortlist($eligible, $input);
        $byId = $shortlist->keyBy('id');
        $startedAt = microtime(true);

        $method = 'ai';
        $schedule = $this->scheduleFromAi($guest, $input, $startsOn, $days, $shortlist, $byId, $settings, $overview);

        if (collect($schedule)->flatten(1)->isEmpty()) {
            $method = 'fallback';
            $draft = $this->ruleBasedPlanner->plan($shortlist, $startsOn, $days, $input);
            $overview = $draft['overview'];
            $schedule = $this->normalizer->normalize($draft['days'], $byId, $startsOn, $days, $input['pace'], $input['has_car']);
        }

        if (collect($schedule)->flatten(1)->isEmpty()) {
            throw TripPlannerException::invalidAiResponse();
        }

        $plan = $guest->tripPlans()->create([
            'days' => $days,
            'starts_on' => $startsOn,
            'interests' => $input['interests'],
            'pace' => $input['pace'],
            'group_type' => $input['group_type'],
            'has_car' => $input['has_car'],
            'budget_level' => $input['budget_level'],
            'overview' => $overview,
            'fingerprint' => $fingerprint,
            'generation_method' => $method,
            'generation_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attractions_sent' => $shortlist->count(),
        ]);

        $this->saveSchedule($plan, $schedule);
        $this->followUp->afterPlanCreated($plan);

        return $plan;
    }

    /**
     * Rebuilds a single day from places not used anywhere else in the plan
     * (and, when there are enough others, not the ones that day already
     * had), so "بازسازی این روز" actually gives something different.
     */
    public function regenerateDay(TripPlan $plan, int $dayNumber): void
    {
        if ($dayNumber < 1 || $dayNumber > $plan->days) {
            throw TripPlannerException::invalidEdit('این روز در برنامه‌ی شما وجود ندارد.');
        }

        $startsOn = ($plan->starts_on ?? now())->copy()->startOfDay();
        $date = $startsOn->copy()->addDays($dayNumber - 1);

        $otherDayIds = $plan->items()->where('day_number', '!=', $dayNumber)->pluck('attraction_id')->all();
        $currentDayIds = $plan->items()->where('day_number', $dayNumber)->pluck('attraction_id')->all();

        $pool = Attraction::query()->where('is_active', true)->get()
            ->reject(fn (Attraction $a) => in_array($a->id, $otherDayIds, true) || $a->isClosedOnDate($date));

        $fresh = $pool->reject(fn (Attraction $a) => in_array($a->id, $currentDayIds, true));
        $pool = $fresh->isNotEmpty() ? $fresh : $pool;

        if ($pool->isEmpty()) {
            throw TripPlannerException::invalidEdit('جاذبه‌ی دیگری برای این روز پیدا نشد.');
        }

        $input = [
            'interests' => $plan->interests ?? [],
            'pace' => $plan->pace,
            'group_type' => $plan->group_type,
            'budget_level' => $plan->budget_level,
        ];
        $draft = $this->ruleBasedPlanner->plan($pool, $date, 1, $input);
        $draft['days'][0]['day_number'] = $dayNumber;

        $schedule = $this->normalizer->normalize(
            [$draft['days'][0]],
            $pool->keyBy('id'),
            $startsOn,
            $plan->days,
            $plan->pace,
            $plan->has_car,
            alreadyUsed: $otherDayIds,
        );

        $plan->items()->where('day_number', $dayNumber)->delete();
        $this->saveSchedule($plan, $schedule);
    }

    /**
     * @param  array<int, list<array{attraction_id: int, start_time: string, end_time: string, note: ?string, travel_minutes: ?int}>>  $schedule  keyed by day number
     */
    public function saveSchedule(TripPlan $plan, array $schedule): void
    {
        foreach ($schedule as $dayNumber => $items) {
            foreach (array_values($items) as $index => $item) {
                $plan->items()->create([
                    'attraction_id' => $item['attraction_id'],
                    'day_number' => $dayNumber,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'note' => $item['note'] !== null ? Str::limit($item['note'], 500) : null,
                    'travel_minutes' => $item['travel_minutes'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, list<array<string, mixed>>> normalized schedule, empty when the AI gave nothing usable
     */
    private function scheduleFromAi(Guest $guest, array $input, Carbon $startsOn, int $days, Collection $shortlist, Collection $byId, TripPlannerSetting $settings, ?string &$overview): array
    {
        $overview = null;
        $provider = AiProvider::active();

        if (! $provider) {
            return [];
        }

        try {
            $message = $provider->chatCompletion([
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($guest, $input, $startsOn, $days, $shortlist, $settings)],
            ]);
            $data = $this->parseResponse($message['content'] ?? '');
        } catch (AiProviderException|TripPlannerException) {
            return [];
        }

        $overview = isset($data['overview']) && is_string($data['overview']) ? $data['overview'] : null;

        return $this->normalizer->normalize($data['days'], $byId, $startsOn, $days, $input['pace'], $input['has_car']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{days: int, starts_on: ?string, interests: list<string>, pace: string, group_type: ?string, has_car: bool, budget_level: ?string}
     */
    private function cleanInput(array $input): array
    {
        $validCategories = array_column(AttractionCategory::cases(), 'value');
        $interests = array_values(array_unique(array_filter(
            (array) ($input['interests'] ?? []),
            fn ($interest) => is_string($interest) && in_array($interest, $validCategories, true),
        )));
        sort($interests);

        return [
            'days' => max(1, min(7, (int) $input['days'])),
            'starts_on' => $input['starts_on'] ?? null,
            'interests' => $interests,
            'pace' => in_array($input['pace'] ?? null, ['relaxed', 'balanced', 'packed'], true) ? $input['pace'] : 'balanced',
            'group_type' => in_array($input['group_type'] ?? null, ['solo', 'couple', 'family_kids', 'friends', 'seniors'], true) ? $input['group_type'] : null,
            'has_car' => (bool) ($input['has_car'] ?? false),
            'budget_level' => in_array($input['budget_level'] ?? null, ['low', 'mid', 'high'], true) ? $input['budget_level'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fingerprint(Guest $guest, array $input): string
    {
        return sha1(json_encode([$guest->id, $input], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return Collection<int, Attraction>
     */
    private function eligibleAttractions(Carbon $startsOn, Carbon $endsOn): Collection
    {
        return Attraction::query()
            ->where('is_active', true)
            ->get()
            ->reject(fn (Attraction $attraction) => $attraction->isClosedBetween($startsOn, $endsOn))
            ->values();
    }

    /**
     * Keeps the request small and relevant: the best-scoring places for
     * this guest, rather than every attraction in the database.
     *
     * @param  Collection<int, Attraction>  $attractions
     * @param  array<string, mixed>  $input
     * @return Collection<int, Attraction>
     */
    private function shortlist(Collection $attractions, array $input): Collection
    {
        if ($attractions->count() <= self::SHORTLIST_SIZE) {
            return $attractions->sortByDesc('boost_weight')->values();
        }

        return $attractions
            ->sortBy([[fn (Attraction $a) => $this->ruleBasedPlanner->score($a, $input), 'desc'], ['id', 'asc']])
            ->take(self::SHORTLIST_SIZE)
            ->values();
    }

    /**
     * Plain-text summary of the guest's own upcoming commitments (a villa
     * already booked, an admission ticket already bought) so the model
     * doesn't schedule straight through a check-in or an event the guest
     * is already locked into.
     */
    private function guestCommitments(Guest $guest, Carbon $startsOn, Carbon $endsOn): string
    {
        $lines = [];

        $bookings = $guest->bookings()
            ->with('room.roomType')
            ->where('check_in_date', '<=', $endsOn)
            ->where('check_out_date', '>=', $startsOn)
            ->get();

        foreach ($bookings as $booking) {
            $roomType = $booking->room?->roomType?->name ?? 'اقامتگاه';
            $lines[] = "رزرو اقامت در {$roomType} از {$booking->check_in_date->toDateString()} تا {$booking->check_out_date->toDateString()}";
        }

        if ($guest->mobile) {
            $tickets = AdmissionTicket::where('customer_phone', $guest->mobile)
                ->where('valid_until', '>=', $startsOn)
                ->where('valid_from', '<=', $endsOn)
                ->get();

            foreach ($tickets as $ticket) {
                $lines[] = "بلیط خریداری‌شده با اعتبار از {$ticket->valid_from->toDateString()} تا {$ticket->valid_until->toDateString()}";
            }
        }

        return $lines ? implode("\n", $lines) : 'مهمان در این بازه تعهد قبلی ثبت‌شده‌ای ندارد.';
    }

    /**
     * Events of the trip's days, for the prompt and for the plan page.
     *
     * @return Collection<int, Event>
     */
    public function eventsBetween(Carbon $startsOn, Carbon $endsOn): Collection
    {
        return Event::query()
            ->whereBetween('starts_at', [$startsOn->copy()->startOfDay(), $endsOn->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            شما یک برنامه‌ریز سفر حرفه‌ای و واقع‌بین برای شهر همدان (ایران) هستید.
            فقط و فقط یک JSON معتبر برگردان - بدون هیچ متن اضافه، بدون ```، دقیقاً با این ساختار:
            {"overview": "یک پاراگراف کوتاه معرفی برنامه", "days": [{"day_number": 1, "items": [{"attraction_id": 1, "start_time": "09:00", "end_time": "10:30", "note": "یک جمله کوتاه درباره این بازدید"}]}]}

            قوانین اجباری:
            - فقط از شناسه‌های (attraction_id) داده‌شده در لیست جاذبه‌ها استفاده کن؛ هیچ جاذبه‌ای خارج از این لیست پیشنهاد نده و هیچ جاذبه‌ای را دوبار نیاور.
            - ساعات کاری هر جاذبه و روزهای تعطیل هفتگی‌اش (closed_weekdays) را رعایت کن؛ روز هفته‌ی هر روز سفر در پیام آمده است.
            - بین پایان یک بازدید و شروع بعدی زمان واقعی جابه‌جایی را بگذار؛ جاذبه‌های نزدیک به هم (lat/lng) را در یک روز بیاور.
            - روزها را با تعهدات قبلی مهمان (رزرو اقامت، بلیط) تداخل نده و رویدادهای ذکرشده را در نظر بگیر.
            - برنامه باید واقعاً قابل‌اجرا باشد - نه بیش از حد فشرده و نه خیلی خلوت؛ به مدت بازدید پیشنهادی هر جاذبه (avg_visit_minutes) و سرعت گشت درخواستی مهمان توجه کن.
            - ترکیب گروه، داشتن خودرو و بودجه‌ی مهمان را رعایت کن (مثلاً برای خانواده‌ی دارای کودک جاذبه‌های kid_friendly، برای بودجه‌ی کم جاذبه‌های کم‌هزینه‌تر - entry_fee به تومان).
            - به یادداشت‌های ادمین (admin_note) و یادداشت عمومی دستیار توجه کن و در انتخاب/حذف جاذبه‌ها و نکات هر بازدید لحاظشان کن.
            - هر روز حداقل یک و حداکثر چهار توقف داشته باشد.
            PROMPT;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Collection<int, Attraction>  $attractions
     */
    private function userPrompt(Guest $guest, array $input, Carbon $startsOn, int $days, Collection $attractions, TripPlannerSetting $settings): string
    {
        $weekdays = Attraction::WEEKDAY_LABELS;

        $attractionsPayload = $attractions->map(fn (Attraction $attraction) => [
            'id' => $attraction->id,
            'name' => $attraction->name,
            'category' => $attraction->category->getLabel(),
            'avg_visit_minutes' => $attraction->avg_visit_minutes,
            'opens_at' => $attraction->opens_at,
            'closes_at' => $attraction->closes_at,
            'closed_weekdays' => collect($attraction->closed_weekdays ?? [])->map(fn ($day) => $weekdays[(int) $day] ?? null)->filter()->values()->all(),
            'entry_fee' => $attraction->entry_fee,
            'kid_friendly' => $attraction->kid_friendly,
            'senior_friendly' => $attraction->senior_friendly,
            'best_time_note' => $attraction->best_time_note,
            'lat' => $attraction->lat !== null ? round((float) $attraction->lat, 4) : null,
            'lng' => $attraction->lng !== null ? round((float) $attraction->lng, 4) : null,
            'admin_note' => $attraction->admin_note,
        ])->values()->all();

        $interests = $input['interests'] ? implode('، ', $input['interests']) : 'بدون ترجیح خاص';
        $endsOn = $startsOn->copy()->addDays($days - 1);

        $dayLines = [];

        for ($day = 1; $day <= $days; $day++) {
            $date = $startsOn->copy()->addDays($day - 1);
            $dayLines[] = "روز {$day}: ".Jalalian::fromCarbon($date)->format('%A %d %B').' ('.$date->toDateString().')';
        }

        $events = $this->eventsBetween($startsOn, $endsOn)
            ->map(fn (Event $event) => "- {$event->title} ({$event->starts_at->toDateTimeString()})")
            ->implode("\n");

        $lines = [
            "تعداد روزهای سفر: {$days}",
            'تاریخ شروع: '.$startsOn->toDateString().' - فصل/ماه: '.Jalalian::fromCarbon($startsOn)->format('%B'),
            ...$dayLines,
            "علایق مهمان: {$interests}",
            'سرعت گشت درخواستی: '.$this->paceLabel($input['pace']),
            'ترکیب گروه: '.$this->groupLabel($input['group_type']),
            'خودرو شخصی: '.($input['has_car'] ? 'دارد' : 'ندارد (تاکسی/پیاده)'),
            'بودجه: '.$this->budgetLabel($input['budget_level']),
            'تعهدات قبلی مهمان:',
            $this->guestCommitments($guest, $startsOn, $endsOn),
            'رویدادهای این روزها:',
            $events !== '' ? $events : 'رویداد ثبت‌شده‌ای نیست.',
        ];

        if ($settings->assistant_note) {
            $lines[] = 'یادداشت عمومی تیم گنج‌نامه برای دستیار: '.$settings->assistant_note;
        }

        $lines[] = 'لیست جاذبه‌های مجاز (فقط از این‌ها انتخاب کن):';
        $lines[] = json_encode($attractionsPayload, JSON_UNESCAPED_UNICODE);

        return implode("\n", $lines);
    }

    private function paceLabel(string $pace): string
    {
        return match ($pace) {
            'relaxed' => 'آرام (حداکثر ۲ توقف در روز)',
            'packed' => 'فشرده (تا ۴ توقف در روز)',
            default => 'متعادل (۲ تا ۳ توقف در روز)',
        };
    }

    private function groupLabel(?string $group): string
    {
        return match ($group) {
            'solo' => 'مسافر تنها',
            'couple' => 'زوج',
            'family_kids' => 'خانواده‌ی دارای کودک',
            'friends' => 'گروه دوستان',
            'seniors' => 'همراه با سالمند',
            default => 'مشخص نشده',
        };
    }

    private function budgetLabel(?string $budget): string
    {
        return match ($budget) {
            'low' => 'کم (ترجیحاً رایگان یا ارزان)',
            'mid' => 'متوسط',
            'high' => 'بالا (محدودیتی نیست)',
            default => 'مشخص نشده',
        };
    }

    /**
     * @return array{overview: ?string, days: array<int, mixed>}
     */
    private function parseResponse(string $content): array
    {
        $stripped = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($content)));

        $data = json_decode($stripped, true);

        if (! is_array($data) || ! isset($data['days']) || ! is_array($data['days'])) {
            throw TripPlannerException::invalidAiResponse();
        }

        return $data;
    }
}
