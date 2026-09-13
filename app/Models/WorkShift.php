<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start_time', 'end_time', 'grace_minutes', 'department_id', 'is_default'];

    protected function casts(): array
    {
        return [
            // The column is a plain SQL TIME; the datetime cast still works
            // since Carbon happily parses a bare "HH:MM:SS" string (onto
            // today's date) - only the H:i portion is ever used.
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'grace_minutes' => 'integer',
            'is_default' => 'boolean',
        ];
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
