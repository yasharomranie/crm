<?php

namespace Tests\Feature\Seo;

use App\Jobs\CheckKeywordRankJob;
use App\Models\SeoTrackedKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CheckTrackedKeywordRanksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_check_job_only_for_active_keywords(): void
    {
        Bus::fake();

        $active = SeoTrackedKeyword::factory()->create(['is_active' => true]);
        $inactive = SeoTrackedKeyword::factory()->create(['is_active' => false]);

        $this->artisan('seo:check-tracked-keyword-ranks')->assertSuccessful();

        Bus::assertDispatched(CheckKeywordRankJob::class, fn (CheckKeywordRankJob $job) => $job->seoTrackedKeywordId === $active->id);
        Bus::assertNotDispatched(CheckKeywordRankJob::class, fn (CheckKeywordRankJob $job) => $job->seoTrackedKeywordId === $inactive->id);
    }
}
