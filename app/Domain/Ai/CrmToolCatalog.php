<?php

namespace App\Domain\Ai;

use App\Domain\Hospitality\HotelReport;
use App\Domain\Hospitality\MorningDigest;
use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Models\AiAssistantProfile;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\Room;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Support\Carbon;

/**
 * A small, curated, read-only subset of the ~50 reporting tools
 * HasAiAssistantEngine offers the built-in management assistant - exposed
 * to custom AiAssistantProfile chats (HasCustomAssistantChatEngine) so one
 * can genuinely answer real operational questions ("دو اتاق خالی داریم؟",
 * "رزرو آقای فلانی کجاست؟") instead of only ever reading back its own
 * knowledge base. Deliberately a short, independent list rather than a
 * reuse of HasAiAssistantEngine's own executeTool(): that trait's ~50
 * tools are built for the single built-in assistant and freely mix
 * read/write and cross-module reach, while a custom profile - which the
 * owner may have made shared, or even one day guest-facing over a channel
 * like WhatsApp - needs a much smaller, deliberately-curated, strictly
 * read-only surface that can be granted one tool at a time
 * (AiAssistantProfile::$allowed_tools) rather than all-or-nothing.
 */
class CrmToolCatalog
{
    /**
     * @return array<string, array{description: string, parameters: array<string, mixed>}>
     */
    public function definitions(): array
    {
        return [
            'check_room_availability' => [
                'description' => 'اتاق‌های خالی برای یک بازهٔ تاریخ مشخص را پیدا می‌کند، همراه با نوع اتاق و قیمت کل اقامت.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'check_in' => ['type' => 'string', 'description' => 'تاریخ ورود، به‌صورت YYYY-MM-DD'],
                        'check_out' => ['type' => 'string', 'description' => 'تاریخ خروج، به‌صورت YYYY-MM-DD'],
                        'room_type_name' => ['type' => 'string', 'description' => 'نام نوع اتاق برای فیلتر کردن (اختیاری)'],
                    ],
                    'required' => ['check_in', 'check_out'],
                ],
            ],
            'lookup_guest' => [
                'description' => 'یک مشتری را با نام یا شمارهٔ موبایل پیدا می‌کند و وضعیت کلی‌اش (سطح باشگاه مشتریان، رزروهای باز، تیکت‌های باز) را برمی‌گرداند.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name_or_mobile' => ['type' => 'string', 'description' => 'نام کامل یا بخشی از نام، یا شمارهٔ موبایل مشتری'],
                    ],
                    'required' => ['name_or_mobile'],
                ],
            ],
            'get_hotel_report' => [
                'description' => 'گزارش عملکرد اقامتگاه برای هفتهٔ گذشته یا ماه گذشته (تا امروز): درصد اشغال، میانگین نرخ اتاق (ADR)، درآمد به‌ازای هر اتاق قابل‌فروش (RevPAR)، درآمد، تعداد رزرو، لغو و عدم حضور، و سه کانال فروش برتر.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => ['week', 'month'], 'description' => 'week = ۷ روز گذشته، month = ۳۰ روز گذشته (پیش‌فرض)'],
                    ],
                    'required' => [],
                ],
            ],
            'get_occupancy_forecast' => [
                'description' => 'پیش‌بینی روزبه‌روز اشغال برای روزهای آینده: تعداد اتاق فروخته‌شده، رزروهای تأییدنشده، اتاق قابل‌فروش و درصد اشغال هر شب.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'چند روز آینده، از امروز (پیش‌فرض ۷، حداکثر ۳۰)'],
                    ],
                    'required' => [],
                ],
            ],
            'get_today_operations' => [
                'description' => 'وضعیت عملیاتی امروز در یک نگاه: ورودی‌های امروز، خروجی‌ها و تأخیرهای خروج، مهمانان داخل اقامتگاه، اتاق‌های خارج از سرویس، یادداشت‌های تحویل شیفتِ باز و دریافتیِ نقدیِ دیروز.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'check_support_ticket_status' => [
                'description' => 'تیکت‌های پشتیبانی باز یک مشتری خاص (یا همهٔ تیکت‌های باز، اگر نامی داده نشود) را همراه با وضعیت و مهلت SLA برمی‌گرداند.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'guest_name' => ['type' => 'string', 'description' => 'نام مشتری برای محدود کردن نتایج (اختیاری - خالی بگذار برای همهٔ تیکت‌های باز)'],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * The tools this profile's owner granted it (AiAssistantProfile::$allowed_tools),
     * narrowed to ones that still exist and that $user may use: the same
     * "ai-assistant.report" gate and per-module permission as the built-in
     * assistant. Granting a tool on a profile only ever narrows access - it can
     * never give a viewer a permission they do not already have.
     *
     * @return array<string, array{description: string, parameters: array<string, mixed>}>
     */
    public function grantedTo(AiAssistantProfile $profile, ?User $user): array
    {
        if (! $user?->can('ai-assistant.report')) {
            return [];
        }

        $catalog = $this->definitions();

        return collect($profile->allowed_tools ?? [])
            ->filter(fn (string $toolName) => isset($catalog[$toolName]) && AiToolAccess::allowsTool($user, $toolName))
            ->mapWithKeys(fn (string $toolName) => [$toolName => $catalog[$toolName]])
            ->all();
    }

    public function label(string $toolName): ?string
    {
        return match ($toolName) {
            'check_room_availability' => 'بررسی اتاق خالی',
            'lookup_guest' => 'جست‌وجوی مشتری',
            'check_support_ticket_status' => 'وضعیت تیکت پشتیبانی',
            'get_hotel_report' => 'گزارش عملکرد (اشغال، ADR، RevPAR)',
            'get_occupancy_forecast' => 'پیش‌بینی اشغال',
            'get_today_operations' => 'وضعیت عملیاتی امروز',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'check_room_availability' => $this->checkRoomAvailability($arguments),
            'lookup_guest' => $this->lookupGuest($arguments),
            'check_support_ticket_status' => $this->checkSupportTicketStatus($arguments),
            'get_hotel_report' => $this->hotelReport($arguments),
            'get_occupancy_forecast' => $this->occupancyForecast($arguments),
            'get_today_operations' => app(MorningDigest::class)->summary(today()),
            default => ['error' => "ابزار ناشناخته: {$toolName}"],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function hotelReport(array $arguments): array
    {
        $days = ($arguments['period'] ?? 'month') === 'week' ? 7 : 30;
        $report = app(HotelReport::class)->build(today()->subDays($days - 1), today());

        return [
            'from' => $report['from']->toDateString(),
            'to' => $report['to']->toDateString(),
            ...collect($report)->only(['rooms', 'room_nights_available', 'room_nights_sold', 'occupancy_percent', 'revenue', 'adr', 'revpar', 'bookings', 'average_stay_nights', 'cancellations', 'no_shows', 'cancellation_rate_percent'])->all(),
            'top_channels' => collect($report['by_channel'])->take(3)->map(fn (array $row) => collect($row)->only(['name', 'bookings', 'revenue', 'share_percent'])->all())->all(),
            'currency' => 'تومان',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function occupancyForecast(array $arguments): array
    {
        $days = max(1, min(30, (int) ($arguments['days'] ?? 7)));

        return ['days' => array_slice(app(HotelReport::class)->forecast(), 0, $days)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function checkRoomAvailability(array $arguments): array
    {
        $checkIn = trim((string) ($arguments['check_in'] ?? ''));
        $checkOut = trim((string) ($arguments['check_out'] ?? ''));
        $roomTypeName = trim((string) ($arguments['room_type_name'] ?? ''));

        if ($checkIn === '' || $checkOut === '') {
            return ['error' => 'تاریخ ورود و خروج هر دو لازم است.'];
        }

        try {
            $checkInDate = Carbon::parse($checkIn);
            $checkOutDate = Carbon::parse($checkOut);
        } catch (\Throwable) {
            return ['error' => 'تاریخ‌های داده‌شده معتبر نیستند.'];
        }

        if (! $checkOutDate->isAfter($checkInDate)) {
            return ['error' => 'تاریخ خروج باید بعد از تاریخ ورود باشد.'];
        }

        $rooms = Room::query()
            ->freeBetween($checkInDate, $checkOutDate)
            ->when($roomTypeName !== '', fn ($query) => $query->whereHas(
                'roomType',
                fn ($q) => $q->where('name', 'like', "%{$roomTypeName}%"),
            ))
            ->with('roomType')
            ->get();

        if ($rooms->isEmpty()) {
            return ['message' => 'هیچ اتاق خالی‌ای برای این بازهٔ تاریخ پیدا نشد.'];
        }

        return [
            'available_rooms_count' => $rooms->count(),
            'rooms' => $rooms->map(fn (Room $room) => [
                'name' => $room->name,
                'room_type' => $room->roomType->name,
                'capacity' => $room->roomType->capacity,
                'price_for_stay_toman' => $room->roomType->effectivePriceForRange($checkInDate, $checkOutDate),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function lookupGuest(array $arguments): array
    {
        $term = trim((string) ($arguments['name_or_mobile'] ?? ''));

        if ($term === '') {
            return ['error' => 'نام یا شمارهٔ موبایلی داده نشد.'];
        }

        $guest = (($canonicalMobile = MobileNumber::canonical($term)) ? Guest::query()->where('mobile', $canonicalMobile)->first() : null)
            ?? Guest::query()->where('name', $term)->first()
            ?? Guest::query()->where('name', 'like', "%{$term}%")->first();

        if (! $guest) {
            return ['error' => "مشتری‌ای با نام یا شمارهٔ «{$term}» پیدا نشد."];
        }

        return [
            'name' => $guest->name,
            'mobile' => $guest->mobile,
            'loyalty_tier' => $guest->loyaltyTier()->getLabel(),
            'open_bookings' => $guest->bookings()->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::CheckedOut])->count(),
            'open_support_tickets' => $guest->tickets()->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function checkSupportTicketStatus(array $arguments): array
    {
        $guestName = trim((string) ($arguments['guest_name'] ?? ''));

        $query = GuestTicket::query()
            ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->with('guest');

        if ($guestName !== '') {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$guestName}%"));
        }

        $tickets = $query->orderBy('sla_due_at')->limit(20)->get();

        if ($tickets->isEmpty()) {
            return ['message' => 'هیچ تیکت پشتیبانی بازی پیدا نشد.'];
        }

        return [
            'open_count' => $tickets->count(),
            'tickets' => $tickets->map(fn (GuestTicket $ticket) => [
                'subject' => $ticket->subject,
                'guest' => $ticket->guest?->name,
                'status' => $ticket->status->getLabel(),
                'priority' => $ticket->priority->getLabel(),
                'is_overdue' => $ticket->isOverdue(),
            ])->all(),
        ];
    }
}
