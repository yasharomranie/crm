<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\AiKeywordClassifier;
use App\Jobs\ClassifySeoKeywordsJob;
use App\Models\AiProvider;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClassifySeoKeywordsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_the_projects_keywords(): void
    {
        AiProvider::create([
            'name' => 'Test Provider', 'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key', 'default_model' => 'gpt-test', 'is_active' => true,
        ]);
        $project = SeoProject::factory()->create();
        $profile = SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'کلمه تست']);

        $content = json_encode([
            ['keyword' => 'کلمه تست', 'intent' => 'informational', 'business_value' => 'low', 'topic' => 'موضوع'],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);

        (new ClassifySeoKeywordsJob($project->id))->handle(app(AiKeywordClassifier::class));

        $this->assertNotNull($profile->fresh()->classified_at);
    }
}
