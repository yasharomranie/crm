<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\RankCheckService;
use App\Jobs\CheckKeywordRankJob;
use App\Models\SeoKeywordRankCheck;
use App\Models\SeoProject;
use App\Models\SeoRankTrackerSetting;
use App\Models\SeoTrackedKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckKeywordRankJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_rank_check_when_credentials_are_configured(): void
    {
        SeoRankTrackerSetting::current()->update([
            'api_key' => 'test-api-key',
            'search_engine_id' => 'test-cx',
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://example.com']);
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id]);

        Http::fake([
            'www.googleapis.com/customsearch/*' => Http::response([
                'items' => [['link' => 'https://example.com/page']],
            ]),
        ]);

        (new CheckKeywordRankJob($keyword->id))->handle(app(RankCheckService::class));

        $this->assertDatabaseHas(SeoKeywordRankCheck::class, [
            'seo_tracked_keyword_id' => $keyword->id,
            'position' => 1,
        ]);
    }

    public function test_a_rank_tracker_not_configured_failure_is_logged_and_not_thrown(): void
    {
        Log::spy();

        $keyword = SeoTrackedKeyword::factory()->create();

        (new CheckKeywordRankJob($keyword->id))->handle(app(RankCheckService::class));

        $this->assertDatabaseMissing(SeoKeywordRankCheck::class, [
            'seo_tracked_keyword_id' => $keyword->id,
        ]);

        Log::shouldHaveReceived('warning')->once();
    }
}
