<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_checking_in_within_the_grace_period_is_on_time(): void
    {
        $shift = WorkShift::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 15]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        Carbon::setTestNow(today()->setTime(9, 10));
        $record = $user->checkIn();

        $this->assertSame(AttendanceStatus::OnTime, $record->status);
        $this->assertSame(0, $record->late_minutes);
    }

    public function test_checking_in_after_the_grace_period_is_late(): void
    {
        $shift = WorkShift::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 15]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        Carbon::setTestNow(today()->setTime(9, 20));
        $record = $user->checkIn();

        $this->assertSame(AttendanceStatus::Late, $record->status);
        $this->assertSame(5, $record->late_minutes);
    }

    public function test_checking_in_twice_in_one_day_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->checkIn();

        $this->expectException(AttendanceException::class);
        $user->checkIn();
    }

    public function test_checking_out_before_checking_in_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(AttendanceException::class);
        $user->checkOut();
    }

    public function test_checking_out_early_records_early_leave_minutes(): void
    {
        $shift = WorkShift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00']);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        Carbon::setTestNow(today()->setTime(9, 0));
        $user->checkIn();

        Carbon::setTestNow(today()->setTime(16, 30));
        $record = $user->checkOut();

        $this->assertSame(30, $record->early_leave_minutes);
        $this->assertSame(450, $record->worked_minutes);
    }

    public function test_a_user_with_no_shift_is_never_marked_late(): void
    {
        $user = User::factory()->create(['work_shift_id' => null]);

        Carbon::setTestNow(today()->setTime(14, 0));
        $record = $user->checkIn();

        $this->assertSame(AttendanceStatus::OnTime, $record->status);
    }

    public function test_monthly_summary_aggregates_worked_minutes_and_late_days(): void
    {
        $shift = WorkShift::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 10]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        $user->attendanceRecords()->create([
            'date' => now()->subDays(2),
            'status' => AttendanceStatus::Late,
            'late_minutes' => 20,
            'worked_minutes' => 400,
        ]);
        $user->attendanceRecords()->create([
            'date' => now()->subDays(1),
            'status' => AttendanceStatus::Absent,
        ]);
        $user->attendanceRecords()->create([
            'date' => now(),
            'status' => AttendanceStatus::OnTime,
            'worked_minutes' => 480,
        ]);

        $summary = $user->monthlyAttendanceSummary();

        $this->assertSame(880, $summary['worked_minutes']);
        $this->assertSame(1, $summary['late_days']);
        $this->assertSame(1, $summary['absent_days']);
        $this->assertSame(20, $summary['late_minutes']);
    }

    public function test_mark_absentees_command_only_flags_users_who_never_checked_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-13 09:05:00')); // a Sunday, within the shift's grace period
        $shift = WorkShift::factory()->create();

        $checkedIn = User::factory()->create(['work_shift_id' => $shift->id]);
        $checkedIn->checkIn();

        $noShift = User::factory()->create(['work_shift_id' => null]);
        $absent = User::factory()->create(['work_shift_id' => $shift->id]);

        Carbon::setTestNow(Carbon::parse('2026-09-13 23:55:00'));
        $this->artisan('attendance:mark-absentees')->assertSuccessful();

        $this->assertSame(AttendanceStatus::OnTime, $checkedIn->todaysAttendance()->status);
        $this->assertNull($noShift->todaysAttendance());
        $this->assertSame(AttendanceStatus::Absent, $absent->todaysAttendance()->status);
    }

    public function test_mark_absentees_command_skips_fridays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-18 23:55:00')); // a Friday
        $shift = WorkShift::factory()->create();
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        $this->artisan('attendance:mark-absentees')->assertSuccessful();

        $this->assertNull($user->todaysAttendance());
    }

    public function test_a_shift_with_no_coordinates_has_no_geofence(): void
    {
        $shift = WorkShift::factory()->create(['latitude' => null, 'longitude' => null]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        // Miles away, but the shift has no geofence configured at all.
        $record = $user->checkIn(latitude: 10.0, longitude: 10.0);

        $this->assertNotNull($record->check_in_at);
    }

    public function test_checking_in_within_the_geofence_radius_succeeds(): void
    {
        $shift = WorkShift::factory()->create([
            'latitude' => 35.7219,
            'longitude' => 51.3347,
            'radius_meters' => 200,
        ]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        // ~55m north of the office - within the 200m radius.
        $record = $user->checkIn(latitude: 35.7224, longitude: 51.3347, ipAddress: '10.0.0.5');

        $this->assertNotNull($record->check_in_at);
        $this->assertSame('10.0.0.5', $record->check_in_ip);
    }

    public function test_checking_in_outside_the_geofence_radius_is_rejected(): void
    {
        $shift = WorkShift::factory()->create([
            'latitude' => 35.7219,
            'longitude' => 51.3347,
            'radius_meters' => 200,
        ]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        // ~5.5km away - well outside the radius.
        $this->expectException(AttendanceException::class);
        $user->checkIn(latitude: 35.7719, longitude: 51.3347);
    }

    public function test_checking_in_with_a_geofenced_shift_requires_coordinates(): void
    {
        $shift = WorkShift::factory()->create(['latitude' => 35.7219, 'longitude' => 51.3347]);
        $user = User::factory()->create(['work_shift_id' => $shift->id]);

        $this->expectException(AttendanceException::class);
        $user->checkIn(); // no coordinates provided - location denied/unavailable
    }

    public function test_a_device_fingerprint_that_differs_from_the_previous_check_in_is_flagged(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $user->checkIn(deviceFingerprint: 'phone-a');
        $user->checkOut();

        Carbon::setTestNow(Carbon::parse('2026-09-11 09:00:00'));
        $record = $user->checkIn(deviceFingerprint: 'phone-b');

        $this->assertTrue($record->isDeviceUnusual());
    }

    public function test_the_same_device_fingerprint_is_not_flagged(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $user->checkIn(deviceFingerprint: 'phone-a');
        $user->checkOut();

        Carbon::setTestNow(Carbon::parse('2026-09-11 09:00:00'));
        $record = $user->checkIn(deviceFingerprint: 'phone-a');

        $this->assertFalse($record->isDeviceUnusual());
    }
}
