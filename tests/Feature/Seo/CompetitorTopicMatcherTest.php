<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\CompetitorTopicMatcher;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetitorTopicMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function activeProvider(): AiProvider
    {
        return AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);
    }

    private function fakeChatResponse(array $content): void
    {
        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
    }

    public function test_it_throws_when_no_ai_provider_is_active_and_candidates_exist(): void
    {
        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/eco-tour',
            'title' => 'تور طبیعت‌گردی همدان',
        ]);

        $this->expectException(AiProviderException::class);

        (new CompetitorTopicMatcher)->match($project);
    }

    public function test_it_matches_a_page_to_an_existing_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create(['name' => 'تور طبیعت‌گردی']);
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $entry = SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/eco-tour',
            'title' => 'تور طبیعت‌گردی همدان',
        ]);

        $this->fakeChatResponse([
            ['url' => $entry->url, 'topic' => 'تور طبیعت‌گردی'],
        ]);

        $matchedCount = (new CompetitorTopicMatcher)->match($project);

        $this->assertSame(1, $matchedCount);
        $entry->refresh();
        $this->assertSame($cluster->id, $entry->topic_cluster_id);
        $this->assertNotNull($entry->classified_at);
    }

    public function test_it_creates_a_new_cluster_when_the_ai_proposes_one(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $entry = SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/winter-ski',
            'title' => 'پیست اسکی آلوارس',
        ]);

        $this->fakeChatResponse([
            ['url' => $entry->url, 'topic' => 'پیست اسکی'],
        ]);

        (new CompetitorTopicMatcher)->match($project);

        $entry->refresh();
        $this->assertSame('پیست اسکی', $entry->topicCluster->name);
    }

    public function test_a_page_the_ai_says_is_irrelevant_is_marked_classified_without_a_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $entry = SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/about-us',
            'title' => 'درباره ما',
        ]);

        $this->fakeChatResponse([
            ['url' => $entry->url, 'topic' => null],
        ]);

        $matchedCount = (new CompetitorTopicMatcher)->match($project);

        $this->assertSame(0, $matchedCount);
        $entry->refresh();
        $this->assertNull($entry->topic_cluster_id);
        $this->assertNotNull($entry->classified_at);
    }

    public function test_an_obviously_noisy_url_is_never_sent_to_the_ai(): void
    {
        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        $noise = SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/wp-login.php',
            'title' => 'ورود',
        ]);

        Http::fake();

        $matchedCount = (new CompetitorTopicMatcher)->match($project);

        $this->assertSame(0, $matchedCount);
        Http::assertNothingSent();
        $this->assertNotNull($noise->refresh()->classified_at);
        $this->assertNull($noise->topic_cluster_id);
    }

    public function test_already_classified_entries_are_never_resent(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'title' => 'قبلا طبقه‌بندی‌شده',
            'classified_at' => now(),
        ]);

        Http::fake();

        $matchedCount = (new CompetitorTopicMatcher)->match($project);

        $this->assertSame(0, $matchedCount);
        Http::assertNothingSent();
    }

    public function test_a_project_with_no_competitors_is_a_noop(): void
    {
        $project = SeoProject::factory()->create();

        Http::fake();

        $this->assertSame(0, (new CompetitorTopicMatcher)->match($project));
        Http::assertNothingSent();
    }
}
