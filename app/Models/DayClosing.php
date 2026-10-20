<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record that a day was closed («بستن روز»): who closed it, when, and a
 * frozen copy of the day's figures (money taken by method, occupancy, and how
 * many open issues were left), so the day can be looked back on as it was.
 */
class DayClosing extends Model
{
    protected $fillable = ['date', 'snapshot', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
