<?php

namespace App\Domain\TripPlanner;

use App\Exceptions\AiProviderException;
use App\Models\AdmissionTicket;
use App\Models\AiProvider;
use App\Models\Attraction;
use App\Models\Guest;
use App\Models\TripPlan;
use App\Models\TripPlannerSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns "من N روز/ساعت در همدان هستم" into a realistic, day-by-day
 * itinerary - reusing the same AiProvider (see app/Models/AiProvider.php)
 * the staff AI assistant already talks to, but as a single structured-JSON
 * request rather than that engine's open-ended tool-calling conversation:
 * this is one well-defined task (schedule these attractions into this
 * many days), not an interactive agent, so it calls chatCompletion()
 * directly with no tools and a strict response schema.
 *
 * Three levers keep the result grounded instead of a generic travel-blog
 * list: only attractions actually in the database are offered to the
 * model (and only the ones open, not temporarily closed, for the trip's
 * dates); the guest's own upcoming bookings/tickets are given as context
 * so the plan doesn't double-book that time; and every attraction's own
 * admin_note plus the global TripPlannerSetting::assistant_note are
 * injected into the prompt verbatim, so staff can steer what gets
 * suggested (a festival, a closed road, a seasonal note) without a
 * deploy - see Attraction's own doc comment for the admin-override
 * reasoning.
 */
class TripPlannerService
{
    /**
     * @param  array{days: int, starts_on: ?string, interests: array<int, string>, pace: string}  $input
     */
    public function generate(Guest $guest, array $input): TripPlan
    {
        $settings = TripPlannerSetting::current();

        if (! $settings->is_enabled) {
            throw TripPlannerException::disabled();
        }

        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $startsOn = $input['starts_on'] ? Carbon::parse($input['starts_on']) : now();
        $days = $input['days'];
        $endsOn = $startsOn->copy()->addDays($days - 1);

        $attractions = $this->eligibleAttractions($startsOn, $endsOn);

        if ($attractions->isEmpty()) {
            throw TripPlannerException::noEligibleAttractions();
        }

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($guest, $input, $startsOn, $days, $attractions, $settings)],
        ]);

        $data = $this->parseResponse($message['content'] ?? '');

        return $this->persist($guest, $input, $startsOn, $days, $data, $attractions->keyBy('id'));
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
            ->sortByDesc('boost_weight')
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

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            شما یک برنامه‌ریز سفر حرفه‌ای و واقع‌بین برای شهر همدان (ایران) هستید.
            فقط و فقط یک JSON معتبر برگردان - بدون هیچ متن اضافه، بدون ```، دقیقاً با این ساختار:
            {"overview": "یک پاراگراف کوتاه معرفی برنامه", "days": [{"day_number": 1, "items": [{"attraction_id": 1, "start_time": "09:00", "end_time": "10:30", "note": "یک جمله کوتاه درباره این بازدید"}]}]}

            قوانین اجباری:
            - فقط از شناسه‌های (attraction_id) داده‌شده در لیست جاذبه‌ها استفاده کن؛ هیچ جاذبه‌ای خارج از این لیست پیشنهاد نده.
            - ساعات کاری هر جاذبه را رعایت کن (اگر داده شده).
            - بین پایان یک بازدید و شروع بعدی حداقل ۱۵ تا ۳۰ دقیقه برای جابه‌جایی لحاظ کن.
            - روزها را با تعهدات قبلی مهمان (رزرو اقامت، بلیط) تداخل نده.
            - برنامه باید واقعاً قابل‌اجرا باشد - نه بیش از حد فشرده و نه خیلی خلوت؛ به مدت بازدید پیشنهادی هر جاذبه (avg_visit_minutes) و سرعت گشت درخواستی مهمان توجه کن.
            - به یادداشت‌های ادمین (admin_note) و یادداشت عمومی دستیار توجه کن و در انتخاب/حذف جاذبه‌ها و نکات هر بازدید لحاظشان کن.
            - هر روز حداقل یک و حداکثر چهار توقف داشته باشد.
            PROMPT;
    }

    /**
     * @param  array{days: int, starts_on: ?string, interests: array<int, string>, pace: string}  $input
     * @param  Collection<int, Attraction>  $attractions
     */
    private function userPrompt(Guest $guest, array $input, Carbon $startsOn, int $days, Collection $attractions, TripPlannerSetting $settings): string
    {
        $attractionsPayload = $attractions->map(fn (Attraction $attraction) => [
            'id' => $attraction->id,
            'name' => $attraction->name,
            'category' => $attraction->category->getLabel(),
            'avg_visit_minutes' => $attraction->avg_visit_minutes,
            'opens_at' => $attraction->opens_at,
            'closes_at' => $attraction->closes_at,
            'admin_note' => $attraction->admin_note,
        ])->values()->all();

        $interests = $input['interests'] ? implode('، ', $input['interests']) : 'بدون ترجیح خاص';

        $lines = [
            "تعداد روزهای سفر: {$days}",
            'تاریخ شروع: '.$startsOn->toDateString(),
            "علایق مهمان: {$interests}",
            'سرعت گشت درخواستی: '.$this->paceLabel($input['pace']),
            'تعهدات قبلی مهمان:',
            $this->guestCommitments($guest, $startsOn, $startsOn->copy()->addDays($days - 1)),
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
            'relaxed' => 'آرام (کمتر از ۲ توقف در روز)',
            'packed' => 'فشرده (تا ۴ توقف در روز)',
            default => 'متعادل (۲ تا ۳ توقف در روز)',
        };
    }

    /**
     * @return array{overview: ?string, days: array<int, array{day_number: int, items: array<int, array{attraction_id: int, start_time: string, end_time: string, note: ?string}>}>}
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

    /**
     * @param  array{days: int, starts_on: ?string, interests: array<int, string>, pace: string}  $input
     * @param  array{overview: ?string, days: array<int, mixed>}  $data
     * @param  Collection<int, Attraction>  $knownAttractions  keyed by id
     */
    private function persist(Guest $guest, array $input, Carbon $startsOn, int $days, array $data, Collection $knownAttractions): TripPlan
    {
        $plan = $guest->tripPlans()->create([
            'days' => $days,
            'starts_on' => $startsOn,
            'interests' => $input['interests'],
            'pace' => $input['pace'],
            'overview' => $data['overview'] ?? null,
        ]);

        $itemsCreated = 0;

        foreach ($data['days'] as $day) {
            if (! is_array($day) || ! isset($day['items']) || ! is_array($day['items'])) {
                continue;
            }

            $dayNumber = (int) ($day['day_number'] ?? 0);

            if ($dayNumber < 1 || $dayNumber > $days) {
                continue;
            }

            foreach ($day['items'] as $index => $item) {
                if (! is_array($item) || ! $knownAttractions->has($item['attraction_id'] ?? null)) {
                    continue;
                }

                if (! isset($item['start_time'], $item['end_time'])) {
                    continue;
                }

                $plan->items()->create([
                    'attraction_id' => $item['attraction_id'],
                    'day_number' => $dayNumber,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'note' => Str::limit((string) ($item['note'] ?? ''), 500) ?: null,
                    'sort_order' => $index,
                ]);

                $itemsCreated++;
            }
        }

        if ($itemsCreated === 0) {
            $plan->delete();

            throw TripPlannerException::invalidAiResponse();
        }

        return $plan;
    }
}
