<?php

namespace App\Filament\Pages;

use App\Domain\Hospitality\OccupancyCalendar;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\RoomType;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * «تقویم اشغال»: every active room against the coming days, so the front desk
 * sees at a glance which nights are free, and can open a booking or start a
 * new one on a free night with the room and date already filled in.
 */
class OccupancyCalendarPage extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.occupancy-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'تقویم اشغال';

    protected static ?string $title = 'تقویم اشغال اتاق‌ها';

    public string $startDate = '';

    public int $days = 14;

    public ?string $roomTypeId = null;

    public static function accessControlPermission(): string
    {
        return 'occupancy-calendar.access';
    }

    public function mount(): void
    {
        $this->startDate = today()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function calendar(): array
    {
        return app(OccupancyCalendar::class)->build($this->start(), $this->days, filled($this->roomTypeId) ? (int) $this->roomTypeId : null);
    }

    public function start(): Carbon
    {
        try {
            return Carbon::parse($this->startDate)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    /**
     * @return array<int, string>
     */
    public function roomTypes(): array
    {
        return RoomType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function shift(int $direction): void
    {
        $this->startDate = $this->start()->addDays($direction * max(1, intdiv($this->days, 2)))->toDateString();
    }

    public function goToToday(): void
    {
        $this->startDate = today()->toDateString();
    }
}
