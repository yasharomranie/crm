<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Room;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class RoomRack extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.room-rack';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'رک اتاق (وضعیت زنده)';

    protected static ?string $title = 'رک اتاق - وضعیت زنده';

    public static function accessControlPermission(): string
    {
        return 'room-rack.access';
    }

    public function getRooms(): Collection
    {
        return Room::query()
            ->with(['roomType', 'department'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) {
                $room->setRelation('currentBookingCache', $room->currentBooking());
                $room->setRelation('upcomingBookingCache', $room->upcomingBooking());

                return $room;
            });
    }
}
