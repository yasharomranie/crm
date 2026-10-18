<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled stop inside a TripPlan - a specific Attraction, on a
 * specific day of the trip, in a specific time window.
 */
class TripPlanItem extends Model
{
    protected $fillable = ['trip_plan_id', 'attraction_id', 'day_number', 'start_time', 'end_time', 'note', 'travel_minutes', 'sort_order'];

    public function tripPlan(): BelongsTo
    {
        return $this->belongsTo(TripPlan::class);
    }

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(Attraction::class);
    }
}
