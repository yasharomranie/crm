<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasTags;

    protected $fillable = ['name', 'national_code', 'mobile', 'email', 'notes'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
