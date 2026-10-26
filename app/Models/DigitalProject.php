<?php

namespace App\Models;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One digital asset the organisation runs (a website, an Instagram page, a
 * Telegram channel, a LinkedIn page, a WhatsApp page...), kept as a short
 * brief: what it is, where it stands, who owns it and when it is next
 * reviewed. Distinct from Project (a budgeted project with phases) and from
 * SeoProject (the SEO work of one website).
 */
class DigitalProject extends Model
{
    use HasFactory;

    protected $fillable = ['channel', 'name', 'handle', 'description', 'status', 'owner_id', 'next_review_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'channel' => DigitalProjectChannel::class,
            'status' => DigitalProjectStatus::class,
            'next_review_at' => 'date',
        ];
    }

    /** Days without a journal line after which an open project counts as unattended. */
    public const STALE_AFTER_DAYS = 7;

    /** Optional reason for the next status change, picked up by the history recorder. */
    public ?string $statusChangeNote = null;

    protected static function booted(): void
    {
        static::created(function (self $project): void {
            $project->recordStatusChange(null);
        });

        static::updated(function (self $project): void {
            if ($project->wasChanged('status')) {
                $project->recordStatusChange($project->getOriginal('status'));
            }
        });
    }

    private function recordStatusChange(DigitalProjectStatus|string|null $from): void
    {
        $this->statusChanges()->create([
            'from_status' => $from instanceof DigitalProjectStatus ? $from->value : $from,
            'to_status' => ($this->status ?? DigitalProjectStatus::Planning)->value,
            'note' => $this->statusChangeNote,
            'changed_by' => auth()->id(),
        ]);

        $this->statusChangeNote = null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(DigitalProjectAction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DigitalProjectLog::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(DigitalProjectStatusChange::class);
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(DigitalProjectLog::class)->ofMany(['logged_on' => 'max', 'id' => 'max']);
    }

    /** Open projects with no journal line in the last week (a never-journaled project counts once it is a week old). */
    public function scopeWithoutRecentLog(Builder $query): Builder
    {
        $since = today()->subDays(self::STALE_AFTER_DAYS);

        return $query
            ->whereIn('status', DigitalProjectStatus::openValues())
            ->whereDate('created_at', '<=', $since)
            ->whereDoesntHave('logs', fn (Builder $logs) => $logs->whereDate('logged_on', '>', $since));
    }

    /** Projects still being worked on whose review date has passed. */
    public function scopeReviewOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_review_at')
            ->whereDate('next_review_at', '<', today())
            ->whereIn('status', DigitalProjectStatus::openValues());
    }

    public function isReviewOverdue(): bool
    {
        return $this->status->isOpen() && $this->next_review_at !== null && $this->next_review_at->lt(today());
    }
}
