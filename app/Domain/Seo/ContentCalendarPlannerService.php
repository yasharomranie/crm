<?php

namespace App\Domain\Seo;

use App\Enums\SeoContentItemStatus;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "when and how much" half of مرکز سئو's content calendar planning
 * (the "what to write" half is Content Gap, a later phase on the
 * project roadmap) - spreads the project's existing backlog (ideas/
 * plans with no due date yet, plus keyword suggestions already
 * approved but never turned into a content item) evenly across a
 * chosen period, sized either by an explicit target or by the
 * project's own recent publishing pace.
 */
class ContentCalendarPlannerService
{
    /** Used only when the project has no publishing history yet to pace off of. */
    private const DEFAULT_MONTHLY_VOLUME = 4;

    private const PACE_LOOKBACK_MONTHS = 3;

    /**
     * @return Collection<int, array{content_item_id: ?int, keyword_suggestion_id: ?int, title: string, target_keyword: ?string, content_type: ?string, suggested_due_date: Carbon}>
     */
    public function proposeSchedule(SeoProject $project, Carbon $periodStart, Carbon $periodEnd, ?int $desiredVolume = null): Collection
    {
        $volume = $desiredVolume ?? $this->historicalMonthlyPace($project);
        $backlog = $this->backlogItems($project)->take(max($volume, 0));

        if ($backlog->isEmpty()) {
            return collect();
        }

        $dates = $this->evenlySpacedDates($periodStart, $periodEnd, $backlog->count());

        return $backlog->values()->map(fn ($item, int $index) => $item instanceof SeoContentItem ? [
            'content_item_id' => $item->id,
            'keyword_suggestion_id' => null,
            'title' => $item->title,
            'target_keyword' => $item->target_keyword,
            'content_type' => $item->content_type,
            'suggested_due_date' => $dates[$index],
        ] : [
            'content_item_id' => null,
            'keyword_suggestion_id' => $item->id,
            'title' => $item->keyword,
            'target_keyword' => $item->keyword,
            'content_type' => $item->suggested_content_type,
            'suggested_due_date' => $dates[$index],
        ]);
    }

    /**
     * Applies a proposal exactly as returned by proposeSchedule() -
     * updates an existing content item's due_date (bumping a plain Idea
     * to Planned), or creates a new one from an approved suggestion
     * that never had one.
     *
     * @param  Collection<int, array{content_item_id: ?int, keyword_suggestion_id: ?int, title: string, target_keyword: ?string, content_type: ?string, suggested_due_date: Carbon}>  $proposal
     */
    public function applySchedule(SeoProject $project, Collection $proposal): int
    {
        $applied = 0;

        foreach ($proposal as $row) {
            if ($row['content_item_id']) {
                $item = SeoContentItem::find($row['content_item_id']);

                if (! $item) {
                    continue;
                }

                $item->update([
                    'due_date' => $row['suggested_due_date']->toDateString(),
                    'status' => $item->status === SeoContentItemStatus::Idea ? SeoContentItemStatus::Planned : $item->status,
                ]);
            } else {
                SeoContentItem::create([
                    'seo_project_id' => $project->id,
                    'keyword_suggestion_id' => $row['keyword_suggestion_id'],
                    'title' => $row['title'],
                    'target_keyword' => $row['target_keyword'],
                    'content_type' => $row['content_type'],
                    'status' => SeoContentItemStatus::Planned,
                    'due_date' => $row['suggested_due_date']->toDateString(),
                ]);
            }

            $applied++;
        }

        return $applied;
    }

    /**
     * @return Collection<int, SeoContentItem|SeoKeywordSuggestion>
     */
    private function backlogItems(SeoProject $project): Collection
    {
        $unscheduledItems = SeoContentItem::where('seo_project_id', $project->id)
            ->whereIn('status', [SeoContentItemStatus::Idea, SeoContentItemStatus::Planned])
            ->whereNull('due_date')
            ->get();

        $unscheduledSuggestions = SeoKeywordSuggestion::where('seo_project_id', $project->id)
            ->where('status', SeoKeywordSuggestionStatus::Approved)
            ->whereDoesntHave('contentItems')
            ->get();

        return $unscheduledItems->concat($unscheduledSuggestions);
    }

    private function historicalMonthlyPace(SeoProject $project): int
    {
        $publishedCount = SeoContentItem::where('seo_project_id', $project->id)
            ->where('status', SeoContentItemStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subMonths(self::PACE_LOOKBACK_MONTHS))
            ->count();

        return $publishedCount > 0
            ? max(1, (int) round($publishedCount / self::PACE_LOOKBACK_MONTHS))
            : self::DEFAULT_MONTHLY_VOLUME;
    }

    /**
     * @return array<int, Carbon>
     */
    private function evenlySpacedDates(Carbon $start, Carbon $end, int $count): array
    {
        if ($count === 1) {
            return [$start->clone()];
        }

        $totalDays = max($start->diffInDays($end), 1);
        $step = $totalDays / max($count - 1, 1);

        return collect(range(0, $count - 1))
            ->map(fn (int $index) => $start->clone()->addDays((int) round($index * $step)))
            ->all();
    }
}
