<?php

namespace App\Models;

use Database\Factories\ChargeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A ready-made extra the front desk adds to a stay's folio with one click
 * (breakfast, dinner, laundry, minibar item...). Adding one copies its name
 * and price onto the booking as an ordinary folio line, so changing the
 * catalog later never changes a bill that was already written.
 */
class ChargeItem extends Model
{
    /** @use HasFactory<ChargeItemFactory> */
    use HasFactory;

    protected $fillable = ['name', 'unit_amount', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Writes this item onto a booking's folio.
     */
    public function addTo(Booking $booking, int $quantity = 1): BookingCharge
    {
        return $booking->charges()->create([
            'title' => $this->name,
            'quantity' => max(1, $quantity),
            'unit_amount' => $this->unit_amount,
        ]);
    }
}
