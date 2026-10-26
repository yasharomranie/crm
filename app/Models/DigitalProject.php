<?php

namespace App\Models;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectMetric;
use App\Enums\DigitalProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Morilog\Jalali\Jalalian;

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

    protected $fillable = ['channel', 'name', 'handle', 'description', 'status', 'owner_id', 'next_review_at', 'created_by', 'seo_project_id'];

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

    /** The SEO project of the same website, whose uptime and certificate checks this project shows. */
    public function seoProject(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class);
    }

    /** Days left on a certificate before it counts as a site problem. */
    public const SSL_WARNING_DAYS = 14;

    /**
     * The one-line state of the linked website: null when nothing is linked
     * or not checked yet.
     *
     * @return array{label: string, color: string}|null
     */
    public function siteHealth(): ?array
    {
        $check = $this->seoProject?->siteCheck;

        if ($check === null || $check->checked_at === null) {
            return null;
        }

        if (! $check->is_up) {
            return ['label' => 'از دسترس خارج است', 'color' => 'danger'];
        }

        $daysLeft = $check->sslDaysLeft();

        if ($daysLeft !== null && $daysLeft <= self::SSL_WARNING_DAYS) {
            return $daysLeft >= 0
                ? ['label' => 'SSL '.Number::toPersian($daysLeft).' روز دیگر', 'color' => 'warning']
                : ['label' => 'SSL منقضی شده', 'color' => 'danger'];
        }

        return ['label' => 'در دسترس', 'color' => 'success'];
    }

    /** Linked websites that are down or whose certificate is close to expiry. */
    public function scopeWithSiteProblem(Builder $query): Builder
    {
        return $query->whereHas('seoProject.siteCheck', fn (Builder $check) => $check
            ->whereNotNull('checked_at')
            ->where(fn (Builder $problem) => $problem
                ->where('is_up', false)
                ->orWhere('ssl_expires_at', '<=', now()->addDays(self::SSL_WARNING_DAYS))));
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

    public function stats(): HasMany
    {
        return $this->hasMany(DigitalProjectStat::class);
    }

    /**
     * The newest reading of each hand-entered number and how it moved since the
     * reading before it.
     *
     * @return Collection<string, array{metric: DigitalProjectMetric, value: int, delta: ?int, recorded_on: Carbon}>
     */
    public function statTrends(): Collection
    {
        return $this->stats
            ->sortByDesc(fn (DigitalProjectStat $stat) => $stat->recorded_on->format('Ymd').str_pad((string) $stat->id, 12, '0', STR_PAD_LEFT))
            ->groupBy(fn (DigitalProjectStat $stat) => $stat->metric->value)
            ->map(function (Collection $readings) {
                $latest = $readings->first();
                $before = $readings->get(1);

                return [
                    'metric' => $latest->metric,
                    'value' => $latest->value,
                    'delta' => $before ? $latest->value - $before->value : null,
                    'recorded_on' => $latest->recorded_on,
                ];
            });
    }

    /** "دنبال‌کننده / عضو ۱٬۲۰۰ (+۴۰)، بازدید ..." or null when nothing was entered. */
    public function statTrendsText(): ?string
    {
        $trends = $this->statTrends();

        if ($trends->isEmpty()) {
            return null;
        }

        return $trends->map(function (array $trend): string {
            $delta = match (true) {
                $trend['delta'] === null => '',
                $trend['delta'] > 0 => ' (+'.Number::toPersian(number_format($trend['delta'])).')',
                $trend['delta'] < 0 => ' (−'.Number::toPersian(number_format(abs($trend['delta']))).')',
                default => ' (بدون تغییر)',
            };

            return $trend['metric']->getLabel().' '.Number::toPersian(number_format($trend['value'])).$delta;
        })->implode('، ');
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

    /**
     * Everything that was done on the project, newest first: finished actions
     * and journal lines of the "done" kind, one "Y/m/d — text" line each.
     *
     * @return Collection<int, string>
     */
    public function doneWorkLines(): Collection
    {
        $finished = $this->actions()
            ->where('status', DigitalProjectActionStatus::Done->value)
            ->get()
            ->map(fn (DigitalProjectAction $action) => [
                'on' => ($action->completed_at ?? $action->updated_at)->copy()->startOfDay(),
                'order' => $action->completed_at?->timestamp ?? $action->updated_at->timestamp,
                'text' => $action->title,
            ]);

        $journal = $this->logs()
            ->where('kind', DigitalProjectLogKind::Done->value)
            ->get()
            ->map(fn (DigitalProjectLog $log) => [
                'on' => $log->logged_on->copy()->startOfDay(),
                'order' => $log->logged_on->timestamp + $log->id,
                'text' => preg_replace('/\s+/u', ' ', $log->body),
            ]);

        return $finished->concat($journal)
            ->sortByDesc('order')
            ->map(fn (array $item) => Jalalian::fromCarbon($item['on'])->format('Y/m/d').' — '.$item['text'])
            ->values();
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
