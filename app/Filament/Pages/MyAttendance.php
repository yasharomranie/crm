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

    public function checkIn(): void
    {
        try {
            Auth::user()->checkIn();

            Notification::make()->title('ورود شما با موفقیت ثبت شد.')->success()->send();
        } catch (AttendanceException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();
        }
    }

    public function checkOut(): void
    {
        try {
            Auth::user()->checkOut();

            Notification::make()->title('خروج شما با موفقیت ثبت شد.')->success()->send();
        } catch (AttendanceException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();
        }
    }
}
