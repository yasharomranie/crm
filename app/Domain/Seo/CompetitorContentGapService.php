<?php

namespace App\Domain\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Support\Collection;

/**
 * The competitor-driven half of App\Domain\Seo\ContentGapService's own
 * doc comment, which explicitly left this out: "that needs a paid
 * third-party keyword/SERP API this project doesn't have wired up." It
 * doesn't, after all - a competitor's own sitemap.xml is public and
 * free, and App\Domain\Seo\CompetitorTopicMatcher already turns it into
 * "which of MY topic clusters does this competitor page belong to."
 * This is the last step: a topic cluster that several competitors cover
 * with dedicated pages, but I have zero content items for, is a genuine,
 * demonstrated gap - not a guess, and not a SERP-rank claim (this never
 * says a competitor outranks me for anything, only that they publish
 * where I don't).
 *
 * Every gap found is stored as an ordinary App\Models\SeoKeywordSuggestion
 * (status New), the exact same human-approval pipeline every other
 * keyword idea in this app already goes through.
 */
class CompetitorContentGapService
{
    /**
     * One competitor covering a topic could just be their own editorial
     * choice; two or more agreeing is a real market signal.
     */
    private const MIN_COMPETITORS_COVERING = 2;

    public function __construct(private readonly PillarClusterMapBuilder $mapBuilder) {}

    /**
     * Per-cluster competitor coverage: how many distinct competitors have
     * at least one page matched to it, and how many pages in total -
     * also used by App\Filament\Pages\SeoPillarClusterMap to show
     * competitive intensity on the topic map itself, not just here.
     *
     * @return Collection<int, array{competitor_count: int, page_count: int}>
     */
    public function coverageByCluster(SeoProject $project): Collection
    {
        $competitorIds = $project->competitors()->pluck('id');

        if ($competitorIds->isEmpty()) {
            return collect();
        }

        return SeoCompetitorSitemapEntry::whereIn('seo_project_competitor_id', $competitorIds)
            ->whereNotNull('topic_cluster_id')
            ->get()
            ->groupBy('topic_cluster_id')
            ->map(fn ($entries) => [
                'competitor_count' => $entries->pluck('seo_project_competitor_id')->unique()->count(),
                'page_count' => $entries->count(),
            ]);
    }

    public function detect(SeoProject $project): int
    {
        $coverageByCluster = $this->coverageByCluster($project);

        if ($coverageByCluster->isEmpty()) {
            return 0;
        }

        $myMap = $this->mapBuilder->build($project);
        $created = 0;

        foreach ($coverageByCluster as $clusterId => $stats) {
            if ($stats['competitor_count'] < self::MIN_COMPETITORS_COVERING) {
                continue;
            }

            $myGroup = $myMap->get($clusterId);
            $myContentCount = $myGroup ? $myGroup['contentItems']->count() : 0;

            if ($myContentCount > 0) {
                continue;
            }

            if ($this->createGapSuggestion($project, (int) $clusterId, $stats)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  array{competitor_count: int, page_count: int}  $stats
     */
    private function createGapSuggestion(SeoProject $project, int $clusterId, array $stats): bool
    {
        $cluster = SeoTopicCluster::find($clusterId);

        if (! $cluster) {
            return false;
        }

        $normalizedName = mb_strtolower(trim($cluster->name));

        $alreadySuggested = SeoKeywordSuggestion::where('seo_project_id', $project->id)
            ->where('topic_cluster_id', $clusterId)
            ->pluck('keyword')
            ->map(fn (string $keyword) => mb_strtolower(trim($keyword)))
            ->contains($normalizedName);

        if ($alreadySuggested) {
            return false;
        }

        SeoKeywordSuggestion::create([
            'seo_project_id' => $project->id,
            'keyword' => $cluster->name,
            'topic_cluster_id' => $clusterId,
            'reason' => "شکاف رقابتی: {$stats['competitor_count']} از رقبای شما (در مجموع {$stats['page_count']} صفحه) محتوای اختصاصی برای موضوع «{$cluster->name}» دارند، اما شما هنوز محتوایی برای آن تولید نکرده‌اید.",
            'status' => SeoKeywordSuggestionStatus::New,
        ]);

        return true;
    }
}
