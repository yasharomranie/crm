<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Resources\HandoverNotes\HandoverNoteResource;
use App\Filament\Resources\LostItems\LostItemResource;
use App\Filament\Resources\RoomBlocks\RoomBlockResource;
use App\Models\Booking;
use App\Models\HandoverNote;
use App\Models\LostItem;
use App\Models\RoomBlock;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The front desk's morning list: who arrives today, who leaves today and who
 * is still in the house past their check-out day, each with the unpaid
 * balance, and the check-in / check-out buttons right on the row.
 */
class TodayArrivals extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.today-arrivals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'ورود و خروج امروز';

    protected static ?string $title = 'ورود و خروج امروز';

    public static function accessControlPermission(): string
    {
        return 'today-arrivals.access';
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getArrivals(): Collection
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereDate('check_in_date', today())
            ->orderBy('room_id')
            ->get();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getDepartures(): Collection
    {
        return $this->bookings()
            ->where('status', BookingStatus::CheckedIn)
            ->whereDate('check_out_date', '<=', today())
            ->orderBy('check_out_date')
            ->get();
    }

    public function getInHouseCount(): int
    {
        return Booking::query()->where('status', BookingStatus::CheckedIn)->count();
    }

    /**
     * What else the front desk should have in mind today - open handover
     * notes, rooms out of service tonight and unclaimed lost items - each
     * only for users who may open the list behind it.
     *
     * @return list<array{label: string, count: int, url: string}>
     */
    public function getDeskReminders(): array
    {
        $reminders = [];

        if (HandoverNoteResource::canViewAny()) {
            $reminders[] = ['label' => 'یادداشت باز تحویل شیفت', 'count' => HandoverNote::query()->open()->count(), 'url' => HandoverNoteResource::getUrl('index')];
        }

        if (RoomBlockResource::canViewAny()) {
            $reminders[] = ['label' => 'اتاق خارج از سرویس امشب', 'count' => RoomBlock::query()->overlapping(today(), today()->addDay())->count(), 'url' => RoomBlockResource::getUrl('index')];
        }

        if (LostItemResource::canViewAny()) {
            $reminders[] = ['label' => 'شیء جامانده‌ی بدون تحویل', 'count' => LostItem::query()->whereIn('status', [LostItem::STATUS_FOUND, LostItem::STATUS_CONTACTED])->count(), 'url' => LostItemResource::getUrl('index')];
        }

        return array_values(array_filter($reminders, fn (array $reminder) => $reminder['count'] > 0));
    }

    public function checkIn(int $bookingId): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if (! auth()->user()?->can('update', $booking) || ! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            Notification::make()->title('امکان چک‌این این رزرو نیست.')->danger()->send();

            return;
        }

        $booking->checkIn();

        Notification::make()->title('چک‌این ثبت شد.')->success()->send();
    }

    public function checkOut(int $bookingId): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if (! auth()->user()?->can('update', $booking) || $booking->status !== BookingStatus::CheckedIn) {
            Notification::make()->title('امکان چک‌اوت این رزرو نیست.')->danger()->send();

            return;
        }

        $booking->checkOut();

        Notification::make()->title('چک‌اوت ثبت شد و تسک نظافت ساخته شد.')->success()->send();
    }

    private function bookings()
    {
        return Booking::query()->with(['guest', 'room.roomType', 'channel', 'charges', 'payments', 'damageDeposit', 'precheckin']);
    }
}
