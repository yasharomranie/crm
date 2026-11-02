<?php

namespace App\Domain\System;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A small, clearly marked set of sample records (hospitality and CRM) so the
 * screens can be tried on the real server without touching real data.
 *
 * Everything it makes starts with «[تست]», and removal only ever deletes
 * records carrying that mark. It never changes or deletes anything else.
 * Written out explicitly (no factories) because factories and Faker are
 * development-only and are not installed on the production host.
 */
class DemoDataGenerator
{
    public const MARK = '[تست] ';

    /**
     * @return array{created: bool, counts: array<string, int>}
     */
    public function create(): array
    {
        if ($this->exists()) {
            return ['created' => false, 'counts' => $this->counts()];
        }

        DB::transaction(function (): void {
            $type = RoomType::create([
                'name' => self::MARK.'سوئیت دو نفره',
                'capacity' => 3,
                'base_price' => 2000000,
                'is_active' => true,
            ]);

            $rooms = collect(['۱', '۲', '۳'])->map(fn (string $number): Room => Room::create([
                'room_type_id' => $type->id,
                'name' => self::MARK.'اتاق '.$number,
                'status' => RoomStatus::Available,
                'is_active' => true,
            ]));

            $guests = collect([
                ['name' => 'علی رضایی', 'mobile' => '09000000001', 'reception_alert' => 'مهمان ویژه؛ تخت اضافه آماده شود.'],
                ['name' => 'مریم احمدی', 'mobile' => '09000000002', 'reception_alert' => null],
                ['name' => 'رضا کریمی', 'mobile' => '09000000003', 'reception_alert' => null],
            ])->map(fn (array $guest): Guest => Guest::create([
                'name' => self::MARK.$guest['name'],
                'mobile' => $guest['mobile'],
                'reception_alert' => $guest['reception_alert'],
            ]));

            $today = today();

            $this->booking($rooms[0], $guests[0], BookingStatus::CheckedIn, $today->copy()->subDay(), $today->copy()->addDays(2), BookingSource::WalkIn)
                ->update(['actual_check_in_at' => now()->subDay()]);
            $this->booking($rooms[0], $guests[1], BookingStatus::CheckedOut, $today->copy()->subDays(10), $today->copy()->subDays(8), BookingSource::Phone)
                ->update(['actual_check_in_at' => $today->copy()->subDays(10)->setTime(14, 0), 'actual_check_out_at' => $today->copy()->subDays(8)->setTime(11, 0)]);
            $this->booking($rooms[1], $guests[1], BookingStatus::Confirmed, $today->copy()->addDays(3), $today->copy()->addDays(5), BookingSource::Phone);
            $this->booking($rooms[1], $guests[2], BookingStatus::Pending, $today->copy()->addDays(8), $today->copy()->addDays(10), BookingSource::Online);

            // Closed until further notice: no end date, the case a screen must not choke on.
            RoomBlock::create([
                'room_id' => $rooms[2]->id,
                'starts_on' => $today->copy()->subDay()->toDateString(),
                'ends_on' => null,
                'reason' => self::MARK.'بازسازی',
            ]);

            Company::create([
                'name' => self::MARK.'شرکت نمونه گنجنامه',
                'national_id' => '1000000001',
                'economic_code' => '1000000002',
                'address' => 'همدان، نمونهٔ آزمایشی',
                'representative_name' => 'نمایندهٔ آزمایشی',
                'representative_role' => 'مدیرعامل',
            ]);

            Deal::create([
                'guest_id' => $guests[0]->id,
                'title' => self::MARK.'رزرو گروهی نوروز',
                'stage' => DealStage::ProposalSent,
                'value' => 50000000,
                'source' => DealSource::Referral,
                'expected_close_date' => $today->copy()->addDays(14)->toDateString(),
            ]);
            Deal::create([
                'guest_id' => $guests[2]->id,
                'title' => self::MARK.'قرارداد اقامت سازمانی',
                'stage' => DealStage::NewLead,
                'value' => 120000000,
                'source' => DealSource::Phone,
            ]);
        });

        return ['created' => true, 'counts' => $this->counts()];
    }

    /**
     * Removes only the marked records. A marked room that somebody has
     * since booked with a real guest is kept, and reported.
     *
     * @return array{removed: array<string, int>, kept_rooms: int}
     */
    public function remove(): array
    {
        $removed = ['deals' => 0, 'companies' => 0, 'blocks' => 0, 'bookings' => 0, 'rooms' => 0, 'room_types' => 0, 'guests' => 0];
        $keptRooms = 0;

        DB::transaction(function () use (&$removed, &$keptRooms): void {
            $guestIds = Guest::query()->where('name', 'like', self::MARK.'%')->pluck('id');
            $roomIds = Room::query()->where('name', 'like', self::MARK.'%')->pluck('id');

            $removed['deals'] = Deal::query()->where('title', 'like', self::MARK.'%')->delete();
            $removed['companies'] = Company::query()->where('name', 'like', self::MARK.'%')->delete();
            $removed['blocks'] = RoomBlock::query()->whereIn('room_id', $roomIds)->delete();

            Booking::query()->whereIn('guest_id', $guestIds)->get()->each(function (Booking $booking) use (&$removed): void {
                $booking->delete();
                $removed['bookings']++;
            });

            Room::query()->whereIn('id', $roomIds)->get()->each(function (Room $room) use (&$removed, &$keptRooms): void {
                if ($room->bookings()->exists()) {
                    $keptRooms++;

                    return;
                }

                $room->delete();
                $removed['rooms']++;
            });

            RoomType::query()->where('name', 'like', self::MARK.'%')->get()->each(function (RoomType $type) use (&$removed): void {
                if (! Room::query()->where('room_type_id', $type->id)->exists()) {
                    $type->delete();
                    $removed['room_types']++;
                }
            });

            $removed['guests'] = Guest::query()->whereIn('id', $guestIds)->delete();
        });

        return ['removed' => $removed, 'kept_rooms' => $keptRooms];
    }

    public function exists(): bool
    {
        return array_sum($this->counts()) > 0;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $roomIds = Room::query()->where('name', 'like', self::MARK.'%')->pluck('id');
        $guestIds = Guest::query()->where('name', 'like', self::MARK.'%')->pluck('id');

        return [
            'room_types' => RoomType::query()->where('name', 'like', self::MARK.'%')->count(),
            'rooms' => $roomIds->count(),
            'guests' => $guestIds->count(),
            'bookings' => Booking::query()->whereIn('guest_id', $guestIds)->count(),
            'blocks' => RoomBlock::query()->whereIn('room_id', $roomIds)->count(),
            'companies' => Company::query()->where('name', 'like', self::MARK.'%')->count(),
            'deals' => Deal::query()->where('title', 'like', self::MARK.'%')->count(),
        ];
    }

    private function booking(Room $room, Guest $guest, BookingStatus $status, CarbonInterface $in, CarbonInterface $out, BookingSource $source): Booking
    {
        return Booking::create([
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'check_in_date' => $in->toDateString(),
            'check_out_date' => $out->toDateString(),
            'adults_count' => 2,
            'status' => $status,
            'source' => $source,
        ]);
    }
}
