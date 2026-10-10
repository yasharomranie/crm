<?php

namespace Tests\Feature;

use App\Jobs\CheckAiAssistantWatchedSourceJob;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CheckAiAssistantWatchedSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_check_job_for_every_watched_source(): void
    {
        Bus::fake();

        $first = AiAssistantWatchedSource::factory()->create();
        $second = AiAssistantWatchedSource::factory()->create();

        $this->artisan('ai-assistants:check-watched-sources')->assertSuccessful();

        Bus::assertDispatched(CheckAiAssistantWatchedSourceJob::class, fn (CheckAiAssistantWatchedSourceJob $job) => $job->watchedSourceId === $first->id);
        Bus::assertDispatched(CheckAiAssistantWatchedSourceJob::class, fn (CheckAiAssistantWatchedSourceJob $job) => $job->watchedSourceId === $second->id);
    }
}
