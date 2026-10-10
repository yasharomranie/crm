<?php

namespace Tests\Feature;

use App\Domain\Ai\WatchedSourceChecker;
use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WatchedSourceCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_check_only_records_a_baseline_and_creates_no_entry(): void
    {
        $profile = AiAssistantProfile::factory()->create();
        $source = AiAssistantWatchedSource::factory()->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
        ]);

        Http::fake(['http://8.8.8.8/prices' => Http::response('<p>قیمت پایه ۱ میلیون تومان است.</p>')]);

        app(WatchedSourceChecker::class)->check($source);

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        $this->assertNotNull($source->fresh()->last_content_hash);
        $this->assertNotNull($source->fresh()->last_checked_at);
    }

    public function test_unchanged_content_creates_no_entry(): void
    {
        $profile = AiAssistantProfile::factory()->create();
        $hash = hash('sha256', 'قیمت پایه ۱ میلیون تومان است.');
        $source = AiAssistantWatchedSource::factory()->alreadyChecked($hash)->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
        ]);

        Http::fake(['http://8.8.8.8/prices' => Http::response('<p>قیمت پایه ۱ میلیون تومان است.</p>')]);

        app(WatchedSourceChecker::class)->check($source);

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
    }

    public function test_changed_content_creates_a_pending_knowledge_entry(): void
    {
        $profile = AiAssistantProfile::factory()->create(['knowledge_auto_save' => false]);
        $source = AiAssistantWatchedSource::factory()->alreadyChecked('old-hash')->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
            'label' => 'قیمت رقیب',
        ]);

        Http::fake(['http://8.8.8.8/prices' => Http::response('<p>قیمت پایه اکنون ۲ میلیون تومان است.</p>')]);

        app(WatchedSourceChecker::class)->check($source);

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertStringContainsString('قیمت رقیب', $entry->content);
        $this->assertStringContainsString('۲ میلیون تومان', $entry->content);
        $this->assertSame(AiKnowledgeEntryStatus::Pending, $entry->status);
        $this->assertSame(AiKnowledgeEntrySource::AiSuggested, $entry->source);
        $this->assertNotSame('old-hash', $source->fresh()->last_content_hash);
    }

    public function test_changed_content_is_approved_immediately_when_auto_save_is_on(): void
    {
        $profile = AiAssistantProfile::factory()->create(['knowledge_auto_save' => true]);
        $source = AiAssistantWatchedSource::factory()->alreadyChecked('old-hash')->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/prices',
        ]);

        Http::fake(['http://8.8.8.8/prices' => Http::response('<p>محتوای تازه.</p>')]);

        app(WatchedSourceChecker::class)->check($source);

        $this->assertSame(AiKnowledgeEntryStatus::Approved, AiAssistantKnowledgeEntry::sole()->status);
    }

    public function test_a_fetch_failure_only_updates_last_checked_at(): void
    {
        $profile = AiAssistantProfile::factory()->create();
        $source = AiAssistantWatchedSource::factory()->alreadyChecked('old-hash')->for($profile, 'profile')->create([
            'url' => 'http://8.8.8.8/missing',
        ]);

        Http::fake(['http://8.8.8.8/missing' => Http::response('not found', 404)]);

        app(WatchedSourceChecker::class)->check($source);

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        $this->assertSame('old-hash', $source->fresh()->last_content_hash);
        $this->assertNotNull($source->fresh()->last_checked_at);
    }
}
