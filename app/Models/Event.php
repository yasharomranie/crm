<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = ['title', 'description', 'starts_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The users who should be notified by this event's reminders. The
     * creator is synced into this list when the event is created (see
     * EventForm/CreateEvent) so an event always has at least one recipient
     * even if no one else is explicitly added.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
