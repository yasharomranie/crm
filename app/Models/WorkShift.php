<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_minutes',
        'latitude', 'longitude', 'radius_meters', 'department_id', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            // The column is a plain SQL TIME; the datetime cast still works
            // since Carbon happily parses a bare "HH:MM:SS" string (onto
            // today's date) - only the H:i portion is ever used.
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'grace_minutes' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * A shift with no coordinates set has no location restriction at all -
     * used for remote staff, or simply until an office location is
     * configured.
     */
    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Great-circle distance (Haversine formula) between this shift's
     * configured location and a reported position, in meters. Null if this
     * shift has no geofence configured.
     */
    public function distanceInMetersFrom(float $latitude, float $longitude): ?float
    {
        if (! $this->hasGeofence()) {
            return null;
        }

        $earthRadiusMeters = 6371000;

        $latDelta = deg2rad($latitude - (float) $this->latitude);
        $lngDelta = deg2rad($longitude - (float) $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($latitude)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMeters * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function isWithinGeofence(float $latitude, float $longitude): bool
    {
        $distance = $this->distanceInMetersFrom($latitude, $longitude);

        return $distance === null || $distance <= $this->radius_meters;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
