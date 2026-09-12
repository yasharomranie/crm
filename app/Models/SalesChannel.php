<?php

namespace App\Models;

use App\Enums\SalesChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesChannel extends Model
{
    protected $fillable = ['name', 'type', 'commission_rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => SalesChannelType::class,
            'commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(ChannelAllotment::class);
    }
}
