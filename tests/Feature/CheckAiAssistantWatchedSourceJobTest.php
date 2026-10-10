<?php

namespace Tests\Feature;

use App\Domain\Ai\SafeUrlFetcher;
use App\Domain\Ai\WatchedSourceChecker;
use App\Jobs\CheckAiAssistantWatchedSourceJob;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckAiAssistantWatchedSourceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_knowledge_entry_when_the_watched_page_changed(): void
    {
        $profile = AiAssistantProfile::factory()->create();
        $source = AiAssistantWatchedSource::factory()->alreadyChecked('old-hash')->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
        ]);

        Http::fake(['http://8.8.8.8/prices' => Http::response('<p>محتوای تازه.</p>')]);

        (new CheckAiAssistantWatchedSourceJob($source->id))->handle(app(WatchedSourceChecker::class));

        $this->assertSame(1, AiAssistantKnowledgeEntry::count());
    }

    public function test_an_unexpected_failure_is_logged_and_not_thrown(): void
    {
        Log::spy();

        $profile = AiAssistantProfile::factory()->create();
        $source = AiAssistantWatchedSource::factory()->alreadyChecked('old-hash')->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
        ]);

        $this->mock(SafeUrlFetcher::class, function ($mock) {
            $mock->shouldReceive('fetch')->andThrow(new \RuntimeException('خطای غیرمنتظره'));
        });

        (new CheckAiAssistantWatchedSourceJob($source->id))->handle(app(WatchedSourceChecker::class));

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_does_nothing_for_a_watched_source_that_no_longer_exists(): void
    {
        (new CheckAiAssistantWatchedSourceJob(999999))->handle(app(WatchedSourceChecker::class));

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
    }
}
