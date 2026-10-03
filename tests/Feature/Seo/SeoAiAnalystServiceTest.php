<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SeoActionItem;
use App\Domain\Seo\SeoAiAnalystService;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoAiAnalystServiceTest extends TestCase
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

        app(SeoAiAnalystService::class)->narrativeFor($project, $this->emptySearchPerformance(), $this->emptyOpportunitySummary(), collect());
    }

    public function test_it_returns_the_providers_response_as_the_narrative(): void
    {
        $this->activeProvider();

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => '  در این دوره ترافیک رشد کرد.  ']]],
            ]),
        ]);

        $project = SeoProject::factory()->create();

        $narrative = app(SeoAiAnalystService::class)->narrativeFor($project, $this->emptySearchPerformance(), $this->emptyOpportunitySummary(), collect());

        $this->assertSame('در این دوره ترافیک رشد کرد.', $narrative);
    }

    public function test_it_sends_the_projects_business_context_and_top_action_items_in_the_prompt(): void
    {
        $this->activeProvider();

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => 'گزارش']]],
            ]),
        ]);

        $project = SeoProject::factory()->create(['brand' => 'گنجنامه']);
        $actionItems = collect([
            new SeoActionItem('opportunity', 'بهبود عنوان صفحه', null, null, 3.0, now()),
        ]);

        app(SeoAiAnalystService::class)->narrativeFor($project, $this->emptySearchPerformance(), $this->emptyOpportunitySummary(), $actionItems);

        Http::assertSent(function ($request) {
            $systemMessage = $request['messages'][0]['content'];
            $userMessage = $request['messages'][1]['content'];

            return str_contains($systemMessage, 'گنجنامه')
                && str_contains($userMessage, 'بهبود عنوان صفحه');
        });
    }

    /**
     * @return array{clicks: int, impressions: int, avg_position: ?float, previous_clicks: int, percent_change: ?float}
     */
    private function emptySearchPerformance(): array
    {
        return ['clicks' => 0, 'impressions' => 0, 'avg_position' => null, 'previous_clicks' => 0, 'percent_change' => null];
    }

    /**
     * @return array{detected: int, resolved: int, improved: int, unchanged: int, declined: int}
     */
    private function emptyOpportunitySummary(): array
    {
        return ['detected' => 0, 'resolved' => 0, 'improved' => 0, 'unchanged' => 0, 'declined' => 0];
    }
}
