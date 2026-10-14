<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\AiKeywordDiscoveryService;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiKeywordDiscoveryServiceTest extends TestCase
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

    /**
     * The generate() call and review() call hit the same endpoint - a
     * response sequence lets each of the two calls in one discover() run
     * return different content.
     */
    private function fakeTwoStageResponses(string $generateContent, ?string $reviewContent = null): void
    {
        Http::fake([
            'api.test-provider.example/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => $generateContent]]]])
                ->push(['choices' => [['message' => ['content' => $reviewContent ?? $generateContent]]]]),
        ]);
    }

    public function test_it_throws_when_no_ai_provider_is_active(): void
    {
        $project = SeoProject::factory()->create();

        $this->expectException(AiProviderException::class);

        app(AiKeywordDiscoveryService::class)->discover($project);
    }

    public function test_it_stores_suggestions_as_new_with_their_topic_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        $content = json_encode([
            ['keyword' => 'اجاره ویلا نزدیک گنجنامه', 'intent' => 'transactional', 'topic' => 'اقامت', 'suggested_content_type' => 'صفحه فرود', 'reason' => 'مکمل تور است'],
        ], JSON_UNESCAPED_UNICODE);

        $this->fakeTwoStageResponses($content);

        $count = app(AiKeywordDiscoveryService::class)->discover($project, 5);

        $this->assertSame(1, $count);

        $suggestion = SeoKeywordSuggestion::firstOrFail();
        $this->assertSame('اجاره ویلا نزدیک گنجنامه', $suggestion->keyword);
        $this->assertSame(SeoSearchIntent::Transactional, $suggestion->intent);
        $this->assertSame('صفحه فرود', $suggestion->suggested_content_type);
        $this->assertSame(SeoKeywordSuggestionStatus::New, $suggestion->status);
        $this->assertSame('اقامت', $suggestion->topicCluster->name);
    }

    public function test_it_tells_the_ai_not_to_repeat_existing_keywords_and_suggestions(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه موجود']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'پیشنهاد قبلی']);

        // An empty generation result short-circuits before the review
        // call, so only one fake response is needed here.
        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, 'کلمه موجود') && str_contains($systemPrompt, 'پیشنهاد قبلی');
        });
    }

    public function test_a_blank_keyword_in_the_response_is_skipped(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        $content = json_encode([
            ['keyword' => '', 'intent' => 'informational'],
            ['keyword' => 'کلمه معتبر', 'intent' => 'informational'],
        ], JSON_UNESCAPED_UNICODE);

        $this->fakeTwoStageResponses($content);

        $count = app(AiKeywordDiscoveryService::class)->discover($project);

        $this->assertSame(1, $count);
        $this->assertSame('کلمه معتبر', SeoKeywordSuggestion::firstOrFail()->keyword);
    }

    public function test_the_review_call_receives_the_raw_draft_and_only_its_output_is_stored(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        $rawContent = json_encode([
            ['keyword' => 'راهنمای سفر به غار علیصدر', 'intent' => 'informational', 'topic' => 'غار علیصدر'],
            ['keyword' => 'مسیرهای دسترسی به غار علیصدر', 'intent' => 'informational', 'topic' => 'غار علیصدر'],
        ], JSON_UNESCAPED_UNICODE);

        // The reviewer merges the two near-duplicates into one.
        $reviewedContent = json_encode([
            ['keyword' => 'راهنمای سفر به غار علیصدر', 'intent' => 'informational', 'topic' => 'غار علیصدر'],
        ], JSON_UNESCAPED_UNICODE);

        $this->fakeTwoStageResponses($rawContent, $reviewedContent);

        $count = app(AiKeywordDiscoveryService::class)->discover($project, 2);

        $this->assertSame(1, $count);
        $this->assertSame('راهنمای سفر به غار علیصدر', SeoKeywordSuggestion::firstOrFail()->keyword);

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) use ($rawContent) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, $rawContent);
        });
    }

    public function test_when_the_review_response_is_not_parseable_the_raw_draft_is_kept(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        $rawContent = json_encode([
            ['keyword' => 'کلمه معتبر', 'intent' => 'informational'],
        ], JSON_UNESCAPED_UNICODE);

        $this->fakeTwoStageResponses($rawContent, 'این یک متن غیرقابل‌تجزیه است، نه JSON.');

        $count = app(AiKeywordDiscoveryService::class)->discover($project);

        $this->assertSame(1, $count);
        $this->assertSame('کلمه معتبر', SeoKeywordSuggestion::firstOrFail()->keyword);
    }

    public function test_the_prompt_warns_when_real_services_is_empty(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create(['real_services' => null]);

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, 'هنوز فهرست خدمات واقعی خودش را مشخص نکرده');
        });
    }

    public function test_the_prompt_includes_the_real_services_text_when_filled(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create(['real_services' => 'داریم: تله‌کابین - نداریم: پیست اسکی']);

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, 'داریم: تله‌کابین - نداریم: پیست اسکی');
        });
    }

    public function test_the_prompt_includes_competitor_coverage_per_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'تور طبیعت‌گردی']);
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create();
        SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'topic_cluster_id' => $cluster->id,
            'classified_at' => now(),
        ]);

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, 'تور طبیعت‌گردی: 1 رقیب با 1 صفحه');
        });
    }

    public function test_the_prompt_omits_the_competitor_section_when_there_are_no_competitors(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return ! str_contains($systemPrompt, 'What competitors are already publishing');
        });
    }

    public function test_the_prompt_includes_own_sitemap_coverage_per_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'تور طبیعت‌گردی']);
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'classified_at' => now(),
        ]);

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return str_contains($systemPrompt, 'تور طبیعت‌گردی: 1 صفحهٔ واقعی منتشرشده');
        });
    }

    public function test_the_prompt_omits_the_own_sitemap_section_when_nothing_is_classified_yet(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        app(AiKeywordDiscoveryService::class)->discover($project);

        Http::assertSent(function (Request $request) {
            $systemPrompt = json_decode($request->body(), true)['messages'][0]['content'] ?? '';

            return ! str_contains($systemPrompt, 'Topics this business already has real, published pages for');
        });
    }
}
