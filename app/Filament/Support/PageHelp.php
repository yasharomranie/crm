<?php

namespace App\Filament\Support;

use App\Filament\Pages\BookingMessageSettings;
use App\Filament\Pages\DigitalProjectWeeklyEntry;
use App\Filament\Pages\HotelReports;
use App\Filament\Pages\NightAuditPage;
use App\Filament\Pages\OccupancyCalendarPage;
use App\Filament\Pages\RoomRack;
use App\Filament\Pages\TodayArrivals;
use App\Filament\Resources\BookingGroups\BookingGroupResource;
use App\Filament\Resources\BookingPromoCodes\BookingPromoCodeResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\CancellationPolicies\CancellationPolicyResource;
use App\Filament\Resources\ChannelAllotments\ChannelAllotmentResource;
use App\Filament\Resources\ChargeItems\ChargeItemResource;
use App\Filament\Resources\DigitalProjectLogs\DigitalProjectLogResource;
use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Resources\HandoverNotes\HandoverNoteResource;
use App\Filament\Resources\LostItems\LostItemResource;
use App\Filament\Resources\PriceRules\PriceRuleResource;
use App\Filament\Resources\RatePlans\RatePlanResource;
use App\Filament\Resources\RoomBlocks\RoomBlockResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\RoomTypes\RoomTypeResource;
use App\Filament\Resources\StayDiscounts\StayDiscountResource;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

/**
 * The «راهنمای ساده‌ی این صفحه» card: a collapsed box on top of a screen with
 * the few steps that matter there. The copy lives in lang/fa/page_help.php;
 * this class only knows which screen maps to which entry. A list/manage page
 * of a resource gets its resource's help; custom pages get their own.
 */
class PageHelp
{
    /** @var array<class-string, string> */
    public const PAGES = [
        TodayArrivals::class => 'today-arrivals',
        RoomRack::class => 'room-rack',
        BookingResource::class => 'bookings',
        OccupancyCalendarPage::class => 'occupancy-calendar',
        RoomBlockResource::class => 'room-blocks',
        LostItemResource::class => 'lost-items',
        HandoverNoteResource::class => 'handover-notes',
        WaitlistEntryResource::class => 'waitlist',
        NightAuditPage::class => 'night-audit',
        HotelReports::class => 'hotel-reports',
        BookingMessageSettings::class => 'booking-messages',
        BookingGroupResource::class => 'booking-groups',
        ChargeItemResource::class => 'charge-items',
        RoomResource::class => 'rooms',
        RoomTypeResource::class => 'room-types',
        RatePlanResource::class => 'rate-plans',
        CancellationPolicyResource::class => 'cancellation-policies',
        ChannelAllotmentResource::class => 'channel-allotments',
        PriceRuleResource::class => 'pricing',
        StayDiscountResource::class => 'pricing',
        BookingPromoCodeResource::class => 'pricing',
        DigitalProjectResource::class => 'digital-projects',
        DigitalProjectLogResource::class => 'digital-project-logs',
        DigitalProjectWeeklyEntry::class => 'digital-project-weekly-entry',
    ];

    /**
     * The help entry of the screen being rendered, or null when it has none.
     *
     * @return array{key: string, title: string, steps: list<string>, guide: string}|null
     */
    public static function forCurrentPage(): ?array
    {
        $component = Str::before((string) request()->route()?->getActionName(), '@');

        return static::forComponent($component);
    }

    /**
     * @return array{key: string, title: string, steps: list<string>, guide: string}|null
     */
    public static function forComponent(string $component): ?array
    {
        if (! class_exists($component)) {
            return null;
        }

        $key = static::PAGES[$component] ?? null;

        if ($key === null && (is_subclass_of($component, ListRecords::class) || is_subclass_of($component, ManageRecords::class))) {
            $key = static::PAGES[$component::getResource()] ?? null;
        }

        if ($key === null) {
            return null;
        }

        $entry = trans("page_help.{$key}");

        if (! is_array($entry)) {
            return null;
        }

        return ['key' => $key] + $entry;
    }
}
