<?php

namespace App\Filament\Pages;

use App\Domain\Hospitality\RoomInventory;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The live board of every unit: who is in it, who comes next, what needs
 * doing today. The board refreshes itself (see the view's wire:poll), can be
 * narrowed by status or by a name, and a unit that housekeeping finished can
 * be put back on sale from its own tile.
 */
class RoomRack extends Page
{
    use HasAccessControlledPage;

    /** Filters that are not a plain room status. */
    public const FILTER_ARRIVING = 'arriving';

    public const FILTER_DEPARTING = 'departing';

    protected string $view = 'filament.pages.room-rack';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'رک اتاق (وضعیت زنده)';

    protected static ?string $title = 'رک اتاق - وضعیت زنده';

    #[Url(as: 'status')]
    public string $filter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $tilesCache = null;

    public static function accessControlPermission(): string
    {
        return 'room-rack.access';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * Housekeeping is done: the unit goes back to «خالی و آماده».
     */
    public function markReady(int $roomId): void
    {
        $room = Room::query()->findOrFail($roomId);

        if (! auth()->user()?->can('update', $room) || $room->status !== RoomStatus::NeedsCleaning) {
            Notification::make()->title('امکان تغییر وضعیت این واحد نیست.')->danger()->send();

            return;
        }

        $room->update(['status' => RoomStatus::Available]);
        $this->tilesCache = null;

        Notification::make()->title("«{$room->name}» آماده‌ی پذیرش شد.")->success()->send();
    }

    /**
     * One entry per active unit, before any filter is applied.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getTiles(): Collection
    {
        if ($this->tilesCache !== null) {
            return $this->tilesCache;
        }

        $today = today();

        $rooms = Room::query()->with(['roomType', 'department'])->where('is_active', true)->orderBy('name')->get();

        $current = Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->latest('actual_check_in_at')
            ->get()
            ->unique('room_id')
            ->keyBy('room_id');

        $upcoming = Booking::query()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereDate('check_in_date', '>=', $today)
            ->orderBy('check_in_date')
            ->get()
            ->unique('room_id')
            ->keyBy('room_id');

        $blocks = RoomBlock::query()->overlapping($today, $today->copy()->addDay())->get()->keyBy('room_id');

        Collection::make([$current, $upcoming])->each(fn (Collection $bookings) => $bookings->load(['guest', 'charges', 'payments']));

        return $this->tilesCache = $rooms->map(function (Room $room) use ($current, $upcoming, $blocks, $today): array {
            $stay = $current->get($room->id);
            $next = $upcoming->get($room->id);
            $block = $blocks->get($room->id);

            $stay?->setRelation('room', $room);
            $next?->setRelation('room', $room);

            $nights = $stay?->nights();
            $nightIndex = $stay ? min($nights, max(1, (int) $stay->check_in_date->diffInDays($today) + 1)) : null;
            $effective = RoomInventory::effectiveStatus($room, $stay, $block);

            return [
                'room' => $room,
                'status' => $effective,
                'stay' => $stay,
                'next' => $next,
                'block' => $block,
                'night_index' => $nightIndex,
                'nights' => $nights,
                'progress' => $stay ? (int) round($nightIndex / $nights * 100) : null,
                'departs_today' => $stay && $stay->check_out_date->lte($today),
                'overdue' => $stay?->isPastCheckOutTime() ?? false,
                'arrives_today' => $next && $next->check_in_date->isSameDay($today),
                'balance' => ($stay ?? $next)?->balanceDue() ?? 0,
                'alert' => ($stay ?? $next)?->guest?->reception_alert,
                'blocked_guest' => (bool) ($stay ?? $next)?->guest?->is_blocked,
            ];
        });
    }

    /**
     * How many units match each filter chip.
     *
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        $tiles = $this->getTiles();

        $counts = ['all' => $tiles->count()];

        foreach (RoomStatus::cases() as $status) {
            $counts[$status->value] = $tiles->filter(fn (array $tile) => $tile['status'] === $status)->count();
        }

        $counts[self::FILTER_ARRIVING] = $tiles->where('arrives_today', true)->count();
        $counts[self::FILTER_DEPARTING] = $tiles->where('departs_today', true)->count();

        return $counts;
    }

    /**
     * The tiles for the chosen filter and search, grouped by room type.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function getGroups(): Collection
    {
        $search = trim($this->search);

        return $this->getTiles()
            ->filter(fn (array $tile): bool => match ($this->filter) {
                'all' => true,
                self::FILTER_ARRIVING => $tile['arrives_today'],
                self::FILTER_DEPARTING => $tile['departs_today'],
                default => $tile['status']->value === $this->filter,
            })
            ->filter(fn (array $tile): bool => $search === '' || str_contains(
                mb_strtolower($tile['room']->name.' '.($tile['stay'] ?? $tile['next'])?->guest?->name),
                mb_strtolower($search),
            ))
            ->groupBy(fn (array $tile): string => $tile['room']->roomType?->name ?? 'بدون نوع');
    }
}
