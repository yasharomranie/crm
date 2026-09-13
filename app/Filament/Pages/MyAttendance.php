<?php

namespace App\Filament\Pages;

use App\Exceptions\AttendanceException;
use App\Models\AttendanceRecord;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class MyAttendance extends Page
{
    protected string $view = 'filament.pages.my-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'حضور و غیاب';

    protected static ?string $navigationLabel = 'حضور و غیاب من';

    protected static ?string $title = 'حضور و غیاب من';

    public function getToday(): ?AttendanceRecord
    {
        return Auth::user()->todaysAttendance();
    }

    /**
     * @return array{worked_minutes: int, late_days: int, absent_days: int, late_minutes: int}
     */
    public function getMonthlySummary(): array
    {
        return Auth::user()->monthlyAttendanceSummary();
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    public function getHistory(): Collection
    {
        return Auth::user()->attendanceRecords()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->latest('date')
            ->get();
    }

    public function checkIn(?float $latitude = null, ?float $longitude = null, ?string $deviceFingerprint = null): void
    {
        try {
            Auth::user()->checkIn($latitude, $longitude, Request::ip(), $deviceFingerprint);

            Notification::make()->title('ورود شما با موفقیت ثبت شد.')->success()->send();
        } catch (AttendanceException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();
        }
    }

    public function checkOut(?float $latitude = null, ?float $longitude = null, ?string $deviceFingerprint = null): void
    {
        try {
            Auth::user()->checkOut($latitude, $longitude, Request::ip(), $deviceFingerprint);

            Notification::make()->title('خروج شما با موفقیت ثبت شد.')->success()->send();
        } catch (AttendanceException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();
        }
    }

    public function shiftHasGeofence(): bool
    {
        return (bool) Auth::user()->workShift?->hasGeofence();
    }
}
