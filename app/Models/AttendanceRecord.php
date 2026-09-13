<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'work_shift_id', 'date', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
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

    public function workedHoursLabel(): string
    {
        $hours = intdiv($this->worked_minutes, 60);
        $minutes = $this->worked_minutes % 60;

        return $hours > 0 ? "{$hours} ساعت و {$minutes} دقیقه" : "{$minutes} دقیقه";
    }
}
