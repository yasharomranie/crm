<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\AiKeywordClassifier;
use App\Enums\SeoBusinessValue;
use App\Enums\SeoSearchIntent;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiKeywordClassifierTest extends TestCase
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

    public function test_it_throws_when_no_ai_provider_is_active(): void
    {
        $project = SeoProject::factory()->create();

        $this->expectException(AiProviderException::class);

        (new AiKeywordClassifier)->classify($project);
    }

    public function test_it_classifies_a_profile_and_creates_its_topic_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $profile = SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'query' => 'تله کابین گنجنامه قیمت',
        ]);

        $this->fakeChatResponse([
            ['keyword' => 'تله کابین گنجنامه قیمت', 'intent' => 'transactional', 'business_value' => 'high', 'topic' => 'قیمت بلیط'],
        ]);

        $classifiedCount = (new AiKeywordClassifier)->classify($project);

        $this->assertSame(1, $classifiedCount);

        $profile->refresh();
        $this->assertSame(SeoSearchIntent::Transactional, $profile->intent);
        $this->assertSame(SeoBusinessValue::High, $profile->business_value);
        $this->assertSame('قیمت بلیط', $profile->topicCluster->name);
        $this->assertNotNull($profile->classified_at);
    }

    public function test_it_reuses_an_existing_topic_cluster_with_the_same_name(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $profileA = SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه اول']);
        $profileB = SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه دوم']);

        $this->fakeChatResponse([
            ['keyword' => 'کلمه اول', 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع مشترک'],
            ['keyword' => 'کلمه دوم', 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع مشترک'],
        ]);

        (new AiKeywordClassifier)->classify($project);

        $this->assertSame(1, $project->topicClusters()->count());
        $this->assertSame($profileA->fresh()->topic_cluster_id, $profileB->fresh()->topic_cluster_id);
    }

    public function test_a_keyword_missing_from_the_ai_response_is_left_unclassified_for_a_later_retry(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $profile = SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه ناشناخته']);

        $this->fakeChatResponse([]);

        $classifiedCount = (new AiKeywordClassifier)->classify($project);

        $this->assertSame(0, $classifiedCount);
        $this->assertNull($profile->fresh()->classified_at);
    }

    public function test_it_parses_a_response_wrapped_in_a_markdown_code_fence(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه تست']);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => "```json\n".json_encode([
                    ['keyword' => 'کلمه تست', 'intent' => 'local', 'business_value' => 'medium', 'topic' => 'تست'],
                ], JSON_UNESCAPED_UNICODE)."\n```"]]],
            ]),
        ]);

        $classifiedCount = (new AiKeywordClassifier)->classify($project);

        $this->assertSame(1, $classifiedCount);
    }

    public function test_it_only_sends_unclassified_profiles_to_the_ai(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id, 'query' => 'قبلا طبقه‌بندی‌شده', 'classified_at' => now(),
        ]);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'جدید']);

        $this->fakeChatResponse([
            ['keyword' => 'جدید', 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع'],
        ]);

        (new AiKeywordClassifier)->classify($project);

        Http::assertSent(function ($request) {
            $sentKeywords = json_decode($request->body(), true)['messages'][1]['content'] ?? '[]';

            return ! str_contains($sentKeywords, 'قبلا طبقه‌بندی‌شده') && str_contains($sentKeywords, 'جدید');
        });
    }

    public function test_it_batches_requests_when_there_are_more_than_twenty_unclassified_keywords(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        // classify() orders profiles by impressions_28d desc before chunking,
        // so impressions must be pinned to descending values matching
        // creation order for the mocked batch responses below to line up
        // with the batches the classifier actually sends.
        $profiles = SeoKeywordProfile::factory()
            ->count(25)
            ->sequence(fn ($sequence) => ['impressions_28d' => 10000 - $sequence->index])
            ->create(['seo_project_id' => $project->id]);

        Http::fake([
            'api.test-provider.example/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode(
                    $profiles->take(20)->map(fn ($p) => ['keyword' => $p->query, 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع'])->all(),
                    JSON_UNESCAPED_UNICODE,
                )]]]])
                ->push(['choices' => [['message' => ['content' => json_encode(
                    $profiles->slice(20)->map(fn ($p) => ['keyword' => $p->query, 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع'])->all(),
                    JSON_UNESCAPED_UNICODE,
                )]]]]),
        ]);

        $classifiedCount = (new AiKeywordClassifier)->classify($project);

        $this->assertSame(25, $classifiedCount);
        Http::assertSentCount(2);
    }
}
