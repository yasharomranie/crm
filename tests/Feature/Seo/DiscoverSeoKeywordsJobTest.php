<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\AiKeywordDiscoveryService;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Models\AiProvider;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverSeoKeywordsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_discovers_and_stores_keyword_suggestions_for_the_project(): void
    {
        AiProvider::create([
            'name' => 'Test Provider', 'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key', 'default_model' => 'gpt-test', 'is_active' => true,
        ]);
        $project = SeoProject::factory()->create();

        $content = json_encode([
            ['keyword' => 'ایده جدید', 'intent' => 'informational', 'topic' => 'موضوع', 'suggested_content_type' => 'مقاله', 'reason' => 'تست'],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);

        (new DiscoverSeoKeywordsJob($project->id, 5))->handle(app(AiKeywordDiscoveryService::class));

        $this->assertSame(1, SeoKeywordSuggestion::where('seo_project_id', $project->id)->count());
    }
}
