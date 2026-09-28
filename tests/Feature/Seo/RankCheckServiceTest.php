<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\RankCheckService;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use App\Models\SeoRankTrackerSetting;
use App\Models\SeoTrackedKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RankCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configureSettings(): void
    {
        SeoRankTrackerSetting::current()->update([
            'api_key' => 'test-api-key',
            'search_engine_id' => 'test-cx',
        ]);
    }

    public function test_it_records_the_position_where_the_project_domain_appears(): void
    {
        $this->configureSettings();

        $project = SeoProject::factory()->create(['website_url' => 'https://example.com']);
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'تور کیش']);

        Http::fake([
            'www.googleapis.com/customsearch/*' => Http::response([
                'items' => [
                    ['link' => 'https://other.com/a'],
                    ['link' => 'https://another.com/b'],
                    ['link' => 'https://www.example.com/page'],
                ],
            ]),
        ]);

        $check = app(RankCheckService::class)->check($keyword);

        $this->assertSame(3, $check->position);
        $this->assertSame('https://www.example.com/page', $check->found_url);
    }

    public function test_a_domain_not_present_in_the_results_is_recorded_as_null_position(): void
    {
        $this->configureSettings();

        $project = SeoProject::factory()->create(['website_url' => 'https://example.com']);
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id]);

        Http::fake([
            'www.googleapis.com/customsearch/*' => Http::response([
                'items' => [
                    ['link' => 'https://other.com/a'],
                    ['link' => 'https://another.com/b'],
                ],
            ]),
        ]);

        $check = app(RankCheckService::class)->check($keyword);

        $this->assertNull($check->position);
        $this->assertNull($check->found_url);
    }

    public function test_checking_the_same_keyword_twice_in_one_day_updates_the_same_row(): void
    {
        $this->configureSettings();

        $project = SeoProject::factory()->create(['website_url' => 'https://example.com']);
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id]);

        Http::fake([
            'www.googleapis.com/customsearch/*' => Http::response([
                'items' => [['link' => 'https://example.com/page']],
            ]),
        ]);

        $service = app(RankCheckService::class);
        $first = $service->check($keyword);
        $second = $service->check($keyword);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $keyword->rankChecks()->count());
    }

    public function test_checking_without_configured_credentials_throws(): void
    {
        $project = SeoProject::factory()->create();
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id]);

        $this->expectException(SeoGoogleException::class);

        app(RankCheckService::class)->check($keyword);
    }
}
