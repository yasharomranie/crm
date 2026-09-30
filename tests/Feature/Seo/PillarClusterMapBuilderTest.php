<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\PillarClusterMapBuilder;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PillarClusterMapBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_profiles_and_suggestions_under_their_shared_topic_cluster(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'اقامت']);

        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'query' => 'اجاره ویلا همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'بهترین ویلاهای همدان']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertCount(1, $map);
        $group = $map[$cluster->id];
        $this->assertSame('اقامت', $group['name']);
        $this->assertSame(['اجاره ویلا همدان'], $group['profiles']->pluck('query')->all());
        $this->assertSame(['بهترین ویلاهای همدان'], $group['suggestions']->pluck('keyword')->all());
    }

    public function test_keywords_with_no_topic_cluster_land_in_an_uncategorized_bucket(): void
    {
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => null, 'query' => 'یک کلمه بدون خوشه']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertSame('دسته‌بندی‌نشده', $map['uncategorized']['name']);
    }

    public function test_a_content_item_is_placed_under_its_suggestions_cluster(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'غار علیصدر']);
        $suggestion = SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id]);

        $item = SeoContentItem::factory()->create([
            'seo_project_id' => $project->id,
            'keyword_suggestion_id' => $suggestion->id,
            'keyword_profile_id' => null,
        ]);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertTrue($map[$cluster->id]['contentItems']->pluck('id')->contains($item->id));
    }

    public function test_two_keywords_sharing_the_same_core_phrase_are_flagged_as_a_possible_overlap(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);

        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'راهنمای سفر به غار علیصدر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'مسیرهای دسترسی به غار علیصدر']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertCount(1, $map[$cluster->id]['warnings']);
        $warning = $map[$cluster->id]['warnings'][0];
        $this->assertSame('راهنمای سفر به غار علیصدر', $warning['a']);
        $this->assertSame('مسیرهای دسترسی به غار علیصدر', $warning['b']);
    }

    public function test_two_genuinely_distinct_keywords_are_not_flagged(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);

        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'بهترین زمان سفر به همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'خرید سوغات همدان']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertSame([], $map[$cluster->id]['warnings']);
    }

    public function test_a_word_shared_by_most_of_the_projects_own_keywords_does_not_trigger_false_warnings_on_its_own(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);

        // "همدان" appears in every single keyword below, but each pair
        // only shares that one word out of three - too low an overlap to
        // flag regardless of whether "همدان" gets stripped, so this is a
        // baseline sanity check, not proof the stopword mechanism fired.
        $keywords = [
            'رویدادهای فرهنگی همدان',
            'جاذبه‌های تاریخی همدان',
            'بهترین زمان سفر به همدان',
            'تفریحات خانوادگی همدان',
            'خرید سوغات همدان',
        ];

        foreach ($keywords as $keyword) {
            SeoKeywordSuggestion::factory()->create([
                'seo_project_id' => $project->id,
                'topic_cluster_id' => $cluster->id,
                'keyword' => $keyword,
            ]);
        }

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertSame([], $map[$cluster->id]['warnings']);
    }

    public function test_a_word_shared_across_most_of_the_projects_clusters_is_stripped_before_comparing(): void
    {
        $project = SeoProject::factory()->create();
        $waterfallCluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'آبشار']);
        $caveCluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'غار']);
        $stayCluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'اقامت']);

        // "همدان" appears in a keyword under all three clusters (100% of
        // clusters) - it should be stripped as a project-wide word, not
        // counted as real overlap between the two two-word keywords below
        // that would otherwise share only that one word.
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $waterfallCluster->id, 'keyword' => 'آبشار همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $caveCluster->id, 'keyword' => 'غار همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $stayCluster->id, 'keyword' => 'اجاره همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $stayCluster->id, 'keyword' => 'بازدید همدان']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertSame([], $map[$stayCluster->id]['warnings']);
    }

    public function test_a_clusters_own_defining_word_is_not_stripped_when_the_project_has_too_few_clusters(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'غار علیصدر']);

        // Only one cluster exists project-wide, so "غار"/"علیصدر" being in
        // every keyword here must NOT be treated as a generic stopword -
        // it's this cluster's own defining topic, and the overlap between
        // these two keywords is real.
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'قیمت بلیط غار علیصدر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'ساعت کاری غار علیصدر']);

        $map = app(PillarClusterMapBuilder::class)->build($project);

        $this->assertCount(1, $map[$cluster->id]['warnings']);
    }
}
