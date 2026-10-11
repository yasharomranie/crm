<?php

namespace Tests\Feature;

use App\Domain\Ai\ScheduledPromptRunner;
use App\Enums\AiKnowledgeEntryStatus;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantScheduledPrompt;
use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduledPromptRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_run_records_a_pending_knowledge_entry_and_stamps_last_run_at(): void
    {
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['description' => 'دستیار پذیرش']);
        $scheduledPrompt = AiAssistantScheduledPrompt::create([
            'ai_assistant_profile_id' => $profile->id,
            'prompt' => 'مهمان‌های امروز رو چک کن',
            'run_at_time' => '08:00',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'همه چیز مرتب است.']]],
            ]),
        ]);

        app(ScheduledPromptRunner::class)->run($scheduledPrompt);

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertStringContainsString('همه چیز مرتب است.', $entry->content);
        $this->assertSame(AiKnowledgeEntryStatus::Pending, $entry->status);
        $this->assertNotNull($scheduledPrompt->fresh()->last_run_at);

        // No tools are ever offered to a scheduled run - see the class
        // docblock on why this must stay structurally impossible to change.
        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data()));
    }

    public function test_a_run_without_an_active_provider_still_stamps_last_run_at_without_crashing(): void
    {
        $profile = AiAssistantProfile::factory()->create();
        $scheduledPrompt = AiAssistantScheduledPrompt::create([
            'ai_assistant_profile_id' => $profile->id,
            'prompt' => 'چیزی را چک کن',
            'run_at_time' => '08:00',
        ]);

        app(ScheduledPromptRunner::class)->run($scheduledPrompt);

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        $this->assertNotNull($scheduledPrompt->fresh()->last_run_at);
    }

    public function test_a_provider_error_is_handled_without_crashing(): void
    {
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create();
        $scheduledPrompt = AiAssistantScheduledPrompt::create([
            'ai_assistant_profile_id' => $profile->id,
            'prompt' => 'چیزی را چک کن',
            'run_at_time' => '08:00',
        ]);

        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        app(ScheduledPromptRunner::class)->run($scheduledPrompt);

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        $this->assertNotNull($scheduledPrompt->fresh()->last_run_at);
    }
}
