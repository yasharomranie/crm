<?php

namespace App\Filament\Pages;

use App\Domain\Hospitality\NightAudit;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Booking;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * «بستن روز»: the front desk's end-of-day checklist - what is left undecided
 * (guests who never came, guests who should have left, unpaid balances,
 * unsettled deposits, rooms with the wrong status), the money taken that day by
 * method, and the button that closes the day and keeps a copy of its figures.
 */
class NightAuditPage extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.night-audit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMoon;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'بستن روز';

    protected static ?string $title = 'بستن روز (ممیزی شبانه)';

    public string $date = '';

    public static function accessControlPermission(): string
    {
        return 'night-audit.access';
    }

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function day(): Carbon
    {
        try {
            return Carbon::parse($this->date)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return app(NightAudit::class)->build($this->day());
    }

    public function shiftDay(int $direction): void
    {
        $this->date = min($this->day()->addDays($direction), today())->toDateString();
    }

    public function markNoShow(int $bookingId): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if (! auth()->user()?->can('update', $booking)) {
            Notification::make()->title('اجازه‌ی این کار را ندارید.')->danger()->send();

            return;
        }

        try {
            $booking->cancel(null, true, false);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('عدم حضور ثبت شد')->success()->send();
    }

    public function closeDay(): void
    {
        try {
            app(NightAudit::class)->close($this->day(), auth()->id());
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('روز بسته شد')->success()->send();
    }

    public function methodLabel(string $method): string
    {
        return BookingPaymentMethod::tryFrom($method)?->getLabel() ?? $method;
    }

    public function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }

    public function statusLabel(Booking $booking): string
    {
        return $booking->status instanceof BookingStatus ? $booking->status->getLabel() : '';
    }
}
