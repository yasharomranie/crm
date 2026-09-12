<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCharge extends Model
{
    protected $fillable = ['booking_id', 'title', 'quantity', 'unit_amount'];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'decimal:0',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lineTotal(): int
    {
        return (int) ($this->quantity * $this->unit_amount);
    }
}
