<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\CompetitorContentGapService;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorContentGapServiceTest extends TestCase
{
    use RefreshDatabase;

    private function matchedEntry(SeoProjectCompetitor $competitor, SeoTopicCluster $cluster): SeoCompetitorSitemapEntry
    {
        return SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'topic_cluster_id' => $cluster->id,
            'classified_at' => now(),
        ]);
    }

    public function test_a_topic_two_competitors_cover_and_i_have_no_content_for_becomes_a_suggestion(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create(['name' => 'تور طبیعت‌گردی']);
        $competitorA = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $competitorB = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $this->matchedEntry($competitorA, $cluster);
        $this->matchedEntry($competitorB, $cluster);

        $created = app(CompetitorContentGapService::class)->detect($project);

        $this->assertSame(1, $created);
        $suggestion = SeoKeywordSuggestion::sole();
        $this->assertSame('تور طبیعت‌گردی', $suggestion->keyword);
        $this->assertSame($cluster->id, $suggestion->topic_cluster_id);
        $this->assertSame(SeoKeywordSuggestionStatus::New, $suggestion->status);
        $this->assertStringContainsString('2 از رقبای شما', $suggestion->reason);
    }

    public function test_a_single_competitor_covering_a_topic_is_not_enough_to_count_as_a_gap(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $this->matchedEntry($competitor, $cluster);

        $created = app(CompetitorContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
        $this->assertSame(0, SeoKeywordSuggestion::count());
    }

    public function test_no_gap_is_recorded_when_i_already_have_a_content_item_for_that_topic(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create();
        $competitorA = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $competitorB = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $this->matchedEntry($competitorA, $cluster);
        $this->matchedEntry($competitorB, $cluster);

        $mySuggestion = SeoKeywordSuggestion::create([
            'seo_project_id' => $project->id,
            'keyword' => 'کلمه موجود',
            'topic_cluster_id' => $cluster->id,
            'status' => SeoKeywordSuggestionStatus::Approved,
        ]);
        SeoContentItem::factory()->for($project, 'project')->create(['keyword_suggestion_id' => $mySuggestion->id]);

        $created = app(CompetitorContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
    }

    public function test_running_it_twice_never_creates_a_duplicate_suggestion(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create(['name' => 'تور طبیعت‌گردی']);
        $competitorA = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $competitorB = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $this->matchedEntry($competitorA, $cluster);
        $this->matchedEntry($competitorB, $cluster);

        $service = app(CompetitorContentGapService::class);
        $service->detect($project);
        $service->detect($project);

        $this->assertSame(1, SeoKeywordSuggestion::count());
    }

    public function test_a_project_with_no_competitors_is_a_noop(): void
    {
        $project = SeoProject::factory()->create();

        $created = app(CompetitorContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
    }
}
