<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\HotelReport;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalesChannelType;
use App\Filament\Pages\HotelReports;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Room;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(Room $room, string $in, string $out, BookingStatus $status, int $folio, array $overrides = []): Booking
    {
        $booking = Booking::factory()->create($overrides + [
            'room_id' => $room->id,
            'check_in_date' => $in,
            'check_out_date' => $out,
            'status' => $status,
        ]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $folio]);

        return $booking->fresh();
    }

    /**
     * Two active rooms (and one switched off) over 10 nights, 2030-03-01..10.
     *
     * @return array{0: Room, 1: Room}
     */
    private function hotel(): array
    {
        Room::factory()->create(['is_active' => false]);

        return [Room::factory()->create(['is_active' => true]), Room::factory()->create(['is_active' => true])];
    }

    public function test_occupancy_adr_and_revpar_count_only_nights_inside_the_period(): void
    {
        [$one, $two] = $this->hotel();
        $channel = SalesChannel::query()->create(['name' => 'اسنپ‌تریپ', 'type' => SalesChannelType::Ota, 'commission_rate' => 10, 'is_active' => true]);

        // 3 nights, all inside: 3M.
        $this->booking($one, '2030-03-01', '2030-03-04', BookingStatus::Confirmed, 3_000_000, ['sales_channel_id' => $channel->id, 'source' => BookingSource::Ota]);
        // 4 nights from 2030-02-27, only Mar 1 and 2 inside: half of 4M.
        $this->booking($two, '2030-02-27', '2030-03-03', BookingStatus::CheckedOut, 4_000_000, ['source' => BookingSource::WalkIn]);
        // Not sold: pending and cancelled.
        $this->booking($one, '2030-03-06', '2030-03-08', BookingStatus::Pending, 2_000_000);
        $cancelled = $this->booking($two, '2030-03-05', '2030-03-07', BookingStatus::Cancelled, 0);
        $cancelled->forceFill(['cancellation_fee' => 100_000])->saveQuietly();

        $report = app(HotelReport::class)->build(Carbon::parse('2030-03-01'), Carbon::parse('2030-03-10'));

        $this->assertSame(2, $report['rooms']);
        $this->assertSame(20, $report['room_nights_available']);
        $this->assertSame(5, $report['room_nights_sold']);
        $this->assertSame(25.0, $report['occupancy_percent']);
        $this->assertSame(5_000_000, $report['revenue']);
        $this->assertSame(1_000_000, $report['adr']);
        $this->assertSame(250_000, $report['revpar']);
        $this->assertSame(2, $report['bookings']);
        $this->assertSame(3.5, $report['average_stay_nights']);
        $this->assertSame(1, $report['cancellations']);
        $this->assertSame(50.0, $report['cancellation_rate_percent']);
        $this->assertSame(100_000, $report['cancellation_fees']);

        $this->assertSame(['اسنپ‌تریپ', 'فروش مستقیم'], array_column($report['by_channel'], 'name'));
        $this->assertSame([3_000_000, 2_000_000], array_column($report['by_channel'], 'revenue'));
        $this->assertSame([60.0, 40.0], array_column($report['by_channel'], 'share_percent'));
        $this->assertEqualsCanonicalizing([BookingSource::Ota->getLabel(), BookingSource::WalkIn->getLabel()], array_column($report['by_source'], 'name'));
    }

    public function test_no_sales_gives_zeroes_and_no_division_errors(): void
    {
        $report = app(HotelReport::class)->build(Carbon::parse('2030-03-01'), Carbon::parse('2030-03-10'));

        $this->assertNull($report['occupancy_percent']);
        $this->assertNull($report['adr']);
        $this->assertNull($report['revpar']);
        $this->assertSame([], $report['by_channel']);

        [$one] = $this->hotel();
        $report = app(HotelReport::class)->build(Carbon::parse('2030-03-01'), Carbon::parse('2030-03-10'));

        $this->assertSame(0.0, $report['occupancy_percent']);
        $this->assertNull($report['adr']);
        $this->assertSame(0, $report['revpar']);
    }

    public function test_the_forecast_separates_firm_and_pending_nights(): void
    {
        [$one, $two] = $this->hotel();

        // Firm: today and tomorrow nights. Pending: tomorrow night only.
        $this->booking($one, today()->toDateString(), today()->addDays(2)->toDateString(), BookingStatus::Confirmed, 2_000_000);
        $this->booking($two, today()->addDay()->toDateString(), today()->addDays(3)->toDateString(), BookingStatus::Pending, 2_000_000);

        $forecast = app(HotelReport::class)->build(today(), today()->addDay())['forecast'];

        $this->assertCount(HotelReport::FORECAST_DAYS, $forecast);
        $this->assertSame(['sold' => 1, 'pending' => 0, 'available' => 2, 'occupancy_percent' => 50.0], array_intersect_key($forecast[0], array_flip(['sold', 'pending', 'available', 'occupancy_percent'])));
        $this->assertSame(1, $forecast[1]['sold']);
        $this->assertSame(1, $forecast[1]['pending']);
        $this->assertSame(0, $forecast[2]['sold']);
        $this->assertSame(1, $forecast[2]['pending']);
    }

    public function test_the_page_shows_the_figures_and_switches_period(): void
    {
        [$one] = $this->hotel();
        $this->booking($one, today()->toDateString(), today()->addDays(2)->toDateString(), BookingStatus::Confirmed, 2_000_000);

        Livewire::actingAs($this->admin())
            ->test(HotelReports::class)
            ->assertSuccessful()
            ->assertSee('RevPAR')
            ->set('period', 'last_month')
            ->assertSuccessful()
            ->set('period', 'next_30')
            ->assertSuccessful()
            ->assertSee('فروش مستقیم');
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی گزارش هتل']));

        Livewire::actingAs($user)->test(HotelReports::class)->assertForbidden();
    }
}
