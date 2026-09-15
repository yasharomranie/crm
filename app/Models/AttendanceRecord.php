<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'work_shift_id', 'date', 'check_in_at', 'check_out_at',
        'check_in_ip', 'check_in_device', 'check_in_latitude', 'check_in_longitude',
        'check_out_ip', 'check_out_device', 'check_out_latitude', 'check_out_longitude',
        'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'worked_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * The device fingerprint this same user most recently checked in with,
     * before this record. Used to flag "this doesn't look like their usual
     * device" - a heads-up for HR, not a block, since a fingerprint this
     * weak (browser/OS/screen, not a hardware ID) legitimately changes
     * when someone gets a new phone or update their browser.
     */
    public function previousDeviceFingerprint(): ?string
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where(function ($query) {
                $query->where('date', '<', $this->date->toDateString())
                    ->orWhere(fn ($query) => $query->where('date', $this->date->toDateString())->where('id', '<', $this->id));
            })
            ->whereNotNull('check_in_device')
            ->latest('date')
            ->latest('id')
            ->value('check_in_device');
    }

    public function isDeviceUnusual(): bool
    {
        $previous = $this->previousDeviceFingerprint();

        return $this->check_in_device !== null
            && $previous !== null
            && $previous !== $this->check_in_device;
    }

    public function workedHoursLabel(): string
    {
        $hours = intdiv($this->worked_minutes, 60);
        $minutes = $this->worked_minutes % 60;

        return Number::toPersian($hours > 0 ? "{$hours} ساعت و {$minutes} دقیقه" : "{$minutes} دقیقه");
    }
}
