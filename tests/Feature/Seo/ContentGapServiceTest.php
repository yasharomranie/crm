<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\ContentGapService;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentGapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suggests_a_keyword_with_real_demand_and_no_content(): void
    {
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'query' => 'اجاره ویلا همدان',
            'impressions_28d' => 200,
        ]);

        $created = app(ContentGapService::class)->detect($project);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas(SeoKeywordSuggestion::class, [
            'seo_project_id' => $project->id,
            'keyword' => 'اجاره ویلا همدان',
            'status' => SeoKeywordSuggestionStatus::New->value,
        ]);
    }

    public function test_it_ignores_a_profile_below_the_impression_threshold(): void
    {
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'impressions_28d' => 5,
        ]);

        $created = app(ContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
        $this->assertSame(0, SeoKeywordSuggestion::count());
    }

    public function test_it_ignores_a_profile_that_already_has_a_content_item(): void
    {
        $project = SeoProject::factory()->create();
        $profile = SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'impressions_28d' => 200,
        ]);
        SeoContentItem::factory()->create([
            'seo_project_id' => $project->id,
            'keyword_profile_id' => $profile->id,
        ]);

        $created = app(ContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
        $this->assertSame(0, SeoKeywordSuggestion::count());
    }

    public function test_it_does_not_duplicate_a_gap_already_suggested(): void
    {
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'query' => 'تور غار علیصدر',
            'impressions_28d' => 200,
        ]);
        SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'تور غار علیصدر',
        ]);

        $created = app(ContentGapService::class)->detect($project);

        $this->assertSame(0, $created);
        $this->assertSame(1, SeoKeywordSuggestion::count());
    }

    public function test_it_carries_the_profiles_topic_cluster_onto_the_suggestion(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'ویلا']);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'impressions_28d' => 200,
            'topic_cluster_id' => $cluster->id,
        ]);

        app(ContentGapService::class)->detect($project);

        $this->assertSame($cluster->id, SeoKeywordSuggestion::firstOrFail()->topic_cluster_id);
    }
}
