<?php

namespace App\Domain\Hospitality;

use App\Domain\Crm\GuestMessenger;
use App\Enums\MessageDeliveryStatus;
use App\Models\Booking;
use App\Models\LostItem;
use App\Models\User;
use InvalidArgumentException;

/**
 * The few things done to a lost item: record it against the stay it came
 * from, tell the guest, hand it back, or put it away.
 */
class LostItemService
{
    public function __construct(private GuestMessenger $messenger) {}

    /**
     * Records an item found after (or during) a stay; the guest and room come from the booking.
     */
    public function recordForBooking(Booking $booking, string $description, ?string $location = null, ?int $userId = null): LostItem
    {
        return LostItem::query()->create([
            'booking_id' => $booking->id,
            'guest_id' => $booking->guest_id,
            'room_id' => $booking->room_id,
            'description' => $description,
            'location' => $location,
            'found_on' => today()->toDateString(),
            'status' => LostItem::STATUS_FOUND,
            'created_by' => $userId,
        ]);
    }

    /**
     * Texts the guest that their item was found; only an open item with a known guest can be announced.
     *
     * @throws InvalidArgumentException
     */
    public function tellGuest(LostItem $item, ?int $userId = null): bool
    {
        if (! $item->isOpen()) {
            throw new InvalidArgumentException('این مورد بسته شده است.');
        }

        if (! $item->guest) {
            throw new InvalidArgumentException('برای این مورد مهمانی ثبت نشده است.');
        }

        $message = $this->messenger->sendSms($item->guest, sprintf(
            '%s عزیز، «%s» شما%s پیدا شد و نزد ما نگهداری می‌شود. برای تحویل با ما تماس بگیرید.',
            $item->guest->name,
            $item->description,
            $item->room ? ' در '.$item->room->name : '',
        ), $userId ? User::find($userId) : null);

        if ($message->status !== MessageDeliveryStatus::Sent) {
            return false;
        }

        $item->update(['status' => LostItem::STATUS_CONTACTED, 'contacted_at' => now()]);

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function close(LostItem $item, string $status, ?string $note = null): void
    {
        if (! $item->isOpen()) {
            throw new InvalidArgumentException('این مورد قبلاً بسته شده است.');
        }

        if (! in_array($status, [LostItem::STATUS_RETURNED, LostItem::STATUS_DISPOSED], true)) {
            throw new InvalidArgumentException('وضعیت پایانی نامعتبر است.');
        }

        $item->update(['status' => $status, 'closed_at' => now(), 'closing_note' => $note]);
    }
}
