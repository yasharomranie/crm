<?php

namespace App\Domain\Ai;

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\Room;
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

    public function label(string $toolName): ?string
    {
        return match ($toolName) {
            'check_room_availability' => 'بررسی اتاق خالی',
            'lookup_guest' => 'جست‌وجوی مشتری',
            'check_support_ticket_status' => 'وضعیت تیکت پشتیبانی',
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
            default => ['error' => "ابزار ناشناخته: {$toolName}"],
        };
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
            ->where('is_active', true)
            ->when($roomTypeName !== '', fn ($query) => $query->whereHas(
                'roomType',
                fn ($q) => $q->where('name', 'like', "%{$roomTypeName}%"),
            ))
            ->whereDoesntHave('bookings', function ($query) use ($checkInDate, $checkOutDate) {
                $query->where('status', '!=', BookingStatus::Cancelled)
                    ->where('check_in_date', '<', $checkOutDate)
                    ->where('check_out_date', '>', $checkInDate);
            })
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
