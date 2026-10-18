<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cashier's work period at the box office: opened with a float of
 * cash in the drawer, closed with a count of what is actually there. The
 * difference between that count and what the sales say should be there is
 * what the manager looks at.
 */
class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'opening_float', 'opened_at', 'closed_at', 'expected_cash', 'counted_cash', 'difference', 'notes'];

    protected function casts(): array
    {
        return [
            'opening_float' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'difference' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
