<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\ContentGapService;
use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
use App\Domain\Seo\UrlOpportunityEngine;
use App\Enums\SeoOpportunityType;
use App\Jobs\AnalyzeSeoProjectJob;
use App\Jobs\ClassifySeoKeywordsJob;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeSeoProjectJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rebuilds_profiles_and_scans_for_opportunities_end_to_end(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'تله کابین گنجنامه قیمت';

        for ($i = 0; $i < 10; $i++) {
            $date = Carbon::parse('2026-09-28')->subDays($i);
            SeoQueryMetric::create([
                'seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query,
                'query_hash' => sha1($query), 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 8.0,
            ]);
        }

        (new AnalyzeSeoProjectJob($project->id))->handle(
            app(KeywordIntelligenceService::class),
            app(OpportunityEngine::class),
            app(UrlOpportunityEngine::class),
            app(ContentGapService::class),
        );

        $this->assertSame(1, $project->keywordProfiles()->count());
        $this->assertSame(1, $project->opportunities()->where('type', SeoOpportunityType::CtrOpportunity->value)->count());
    }

    public function test_it_chains_a_classification_job(): void
    {
        Queue::fake();

        $project = SeoProject::factory()->create();

        (new AnalyzeSeoProjectJob($project->id))->handle(
            app(KeywordIntelligenceService::class),
            app(OpportunityEngine::class),
            app(UrlOpportunityEngine::class),
            app(ContentGapService::class),
        );

        Queue::assertPushed(ClassifySeoKeywordsJob::class, fn ($job) => $job->seoProjectId === $project->id);
    }
}
