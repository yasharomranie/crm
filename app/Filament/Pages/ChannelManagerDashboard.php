<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\SalesChannel;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ChannelManagerDashboard extends Page
{
    protected string $view = 'filament.pages.channel-manager-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'داشبورد کانال‌های فروش';

    protected static ?string $title = 'کانال‌های فروش - سهمیه و کمیسیون';

    /**
     * Per channel: today's allotment usage per room type (how many of the
     * allotted units are currently occupied by a booking from that
     * channel), plus commission owed so far. "Owed" is simplified to the
     * sum of commission_amount across all non-cancelled bookings - it does
     * not track whether the commission invoice has actually been settled
     * with the OTA, since that reconciliation happens outside this system.
     */
    public function getChannels(): Collection
    {
        $today = Carbon::today();

        return SalesChannel::query()
            ->with(['allotments.roomType'])
            ->orderBy('name')
            ->get()
            ->map(function (SalesChannel $channel) use ($today) {
                $channel->setRelation('allotmentUsageCache', $channel->allotments->map(function ($allotment) use ($channel, $today) {
                    $usedCount = $channel->bookings()
                        ->where('status', '!=', BookingStatus::Cancelled)
                        ->whereHas('room', fn ($query) => $query->where('room_type_id', $allotment->room_type_id))
                        ->where('check_in_date', '<=', $today)
                        ->where('check_out_date', '>', $today)
                        ->count();

                    return [
                        'room_type' => $allotment->roomType->name,
                        'allotment_count' => $allotment->allotment_count,
                        'used_count' => $usedCount,
                    ];
                }));

                $channel->setAttribute('bookings_count_cache', $channel->bookings()->where('status', '!=', BookingStatus::Cancelled)->count());
                $channel->setAttribute('commission_owed_cache', (int) $channel->bookings()->sum('commission_amount'));

                return $channel;
            });
    }
}
