<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «تخفیف اقامت طولانی»: a stay of at least min_nights gets percent off its
 * room nights. A tier without a room type applies to every type; for one stay
 * the best matching tier wins (tiers never add up).
 */
class StayDiscount extends Model
{
    protected $fillable = ['room_type_id', 'min_nights', 'percent', 'is_active'];

    protected function casts(): array
    {
        return [
            'min_nights' => 'integer',
            'percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
