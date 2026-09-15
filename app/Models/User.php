<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'mobile', 'national_code', 'email', 'password', 'department_id', 'work_shift_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * The mascot (in its cap) stands in for every user's avatar - there's no
     * uploaded-photo field on this model, and this beats Filament's default
     * UiAvatarsProvider, which calls out to ui-avatars.com for initials.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return asset('images/mascot-avatar.svg');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function todaysAttendance(): ?AttendanceRecord
    {
        return $this->attendanceRecords()->whereDate('date', today())->first();
    }

    /**
     * Records the user's arrival for today. Late-ness is measured against
     * their assigned shift's start time plus its grace period; users with
     * no assigned shift are always marked on-time (there's nothing to be
     * late against).
     *
     * If the shift has a geofence configured, $latitude/$longitude (read
     * from the browser's Geolocation API - see MyAttendance) are required
     * and must fall within the shift's allowed radius. $ipAddress and
     * $deviceFingerprint are recorded for HR to review, not enforced here;
     * see AttendanceRecord::isDeviceUnusual().
     */
    public function checkIn(
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $deviceFingerprint = null,
    ): AttendanceRecord {
        $existing = $this->todaysAttendance();

        if ($existing?->check_in_at) {
            throw AttendanceException::alreadyCheckedInToday();
        }

        $shift = $this->workShift;

        if ($shift?->hasGeofence()) {
            if ($latitude === null || $longitude === null) {
                throw AttendanceException::locationRequired();
            }

            if (! $shift->isWithinGeofence($latitude, $longitude)) {
                throw AttendanceException::outsideAllowedLocation(
                    (int) round($shift->distanceInMetersFrom($latitude, $longitude)),
                    $shift->radius_meters,
                );
            }
        }

        $now = now();
        $lateMinutes = 0;
        $status = AttendanceStatus::OnTime;

        if ($shift) {
            $graceEnd = today()->setTimeFrom($shift->start_time)->addMinutes($shift->grace_minutes);

            if ($now->greaterThan($graceEnd)) {
                $lateMinutes = $graceEnd->diffInMinutes($now);
                $status = AttendanceStatus::Late;
            }
        }

        return $this->attendanceRecords()->updateOrCreate(
            ['date' => today()->toDateString()],
            [
                'work_shift_id' => $shift?->id,
                'check_in_at' => $now,
                'check_in_ip' => $ipAddress,
                'check_in_device' => $deviceFingerprint,
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'late_minutes' => $lateMinutes,
                'status' => $status,
            ],
        );
    }

    /**
     * Records the user's departure for today. Early-leave is measured
     * against their shift's end time (also the shift in effect when they
     * checked in today, not whatever they're assigned to right now). Uses
     * the same geofence as check-in.
     */
    public function checkOut(
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $deviceFingerprint = null,
    ): AttendanceRecord {
        $record = $this->todaysAttendance();

        if (! $record?->check_in_at) {
            throw AttendanceException::notCheckedInYet();
        }

        if ($record->check_out_at) {
            throw AttendanceException::alreadyCheckedOutToday();
        }

        $shift = $record->workShift;

        if ($shift?->hasGeofence()) {
            if ($latitude === null || $longitude === null) {
                throw AttendanceException::locationRequired();
            }

            if (! $shift->isWithinGeofence($latitude, $longitude)) {
                throw AttendanceException::outsideAllowedLocation(
                    (int) round($shift->distanceInMetersFrom($latitude, $longitude)),
                    $shift->radius_meters,
                );
            }
        }

        $now = now();
        $earlyLeaveMinutes = 0;

        if ($shift) {
            $shiftEnd = today()->setTimeFrom($shift->end_time);

            if ($now->lessThan($shiftEnd)) {
                $earlyLeaveMinutes = $now->diffInMinutes($shiftEnd);
            }
        }

        $record->update([
            'check_out_at' => $now,
            'check_out_ip' => $ipAddress,
            'check_out_device' => $deviceFingerprint,
            'check_out_latitude' => $latitude,
            'check_out_longitude' => $longitude,
            'worked_minutes' => $record->check_in_at->diffInMinutes($now),
            'early_leave_minutes' => $earlyLeaveMinutes,
        ]);

        return $record;
    }

    /**
     * Attendance summary for the calendar month containing $reference
     * (defaults to the current month) - powers the "حضور و غیاب من" stat
     * cards.
     *
     * @return array{worked_minutes: int, late_days: int, absent_days: int, late_minutes: int}
     */
    public function monthlyAttendanceSummary(?Carbon $reference = null): array
    {
        $reference ??= today();

        $records = $this->attendanceRecords()
            ->whereBetween('date', [$reference->copy()->startOfMonth(), $reference->copy()->endOfMonth()])
            ->get();

        return [
            'worked_minutes' => (int) $records->sum('worked_minutes'),
            'late_days' => $records->where('status', AttendanceStatus::Late)->count(),
            'absent_days' => $records->where('status', AttendanceStatus::Absent)->count(),
            'late_minutes' => (int) $records->sum('late_minutes'),
        ];
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'submitted_by');
    }

    public function workflowActions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class, 'actor_id');
    }
}
