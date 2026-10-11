<?php

namespace Tests\Feature;

use App\Jobs\RunAiAssistantScheduledPromptJob;
use App\Models\AiAssistantScheduledPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RunAiAssistantScheduledPromptsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_dispatches_a_due_prompt_that_has_not_run_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 08:15', 'Asia/Tehran'));
        Bus::fake();

        $due = AiAssistantScheduledPrompt::factory()->create(['run_at_time' => '08:00']);

        $this->artisan('ai-assistants:run-scheduled-prompts')->assertSuccessful();

        Bus::assertDispatched(RunAiAssistantScheduledPromptJob::class, fn (RunAiAssistantScheduledPromptJob $job) => $job->scheduledPromptId === $due->id);
    }

    public function test_it_skips_a_prompt_whose_time_has_not_arrived_yet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 07:00', 'Asia/Tehran'));
        Bus::fake();

        AiAssistantScheduledPrompt::factory()->create(['run_at_time' => '08:00']);

        $this->artisan('ai-assistants:run-scheduled-prompts')->assertSuccessful();

        Bus::assertNotDispatched(RunAiAssistantScheduledPromptJob::class);
    }

    public function test_it_skips_a_prompt_that_already_ran_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00', 'Asia/Tehran'));
        Bus::fake();

        AiAssistantScheduledPrompt::factory()->create([
            'run_at_time' => '08:00',
            'last_run_at' => Carbon::parse('2026-10-11 08:05', 'Asia/Tehran'),
        ]);

        $this->artisan('ai-assistants:run-scheduled-prompts')->assertSuccessful();

        Bus::assertNotDispatched(RunAiAssistantScheduledPromptJob::class);
    }

    public function test_it_runs_again_on_the_next_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00', 'Asia/Tehran'));
        Bus::fake();

        $prompt = AiAssistantScheduledPrompt::factory()->create([
            'run_at_time' => '08:00',
            'last_run_at' => Carbon::parse('2026-10-10 08:05', 'Asia/Tehran'),
        ]);

        $this->artisan('ai-assistants:run-scheduled-prompts')->assertSuccessful();

        Bus::assertDispatched(RunAiAssistantScheduledPromptJob::class, fn (RunAiAssistantScheduledPromptJob $job) => $job->scheduledPromptId === $prompt->id);
    }

    public function test_it_skips_an_inactive_prompt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00', 'Asia/Tehran'));
        Bus::fake();

        AiAssistantScheduledPrompt::factory()->create(['run_at_time' => '08:00', 'is_active' => false]);

        $this->artisan('ai-assistants:run-scheduled-prompts')->assertSuccessful();

        Bus::assertNotDispatched(RunAiAssistantScheduledPromptJob::class);
    }
}
