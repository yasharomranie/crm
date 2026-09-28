<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\AiKeywordDiscoveryService;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_throws_when_no_ai_provider_is_active(): void
    {
        $project = SeoProject::factory()->create();

        $this->expectException(AiProviderException::class);

        (new AiKeywordDiscoveryService)->discover($project);
    }

    public function test_it_stores_suggestions_as_new_with_their_topic_cluster(): void
    {
        $this->activeProvider();
        $project = SeoProject::factory()->create();

        $content = json_encode([
            ['keyword' => 'اجاره ویلا نزدیک گنجنامه', 'intent' => 'transactional', 'topic' => 'اقامت', 'suggested_content_type' => 'صفحه فرود', 'reason' => 'مکمل تور است'],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);

        $count = (new AiKeywordDiscoveryService)->discover($project, 5);

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

        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '[]']]]])]);

        (new AiKeywordDiscoveryService)->discover($project);

        Http::assertSent(function ($request) {
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

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);

        $count = (new AiKeywordDiscoveryService)->discover($project);

        $this->assertSame(1, $count);
        $this->assertSame('کلمه معتبر', SeoKeywordSuggestion::firstOrFail()->keyword);
    }
}
