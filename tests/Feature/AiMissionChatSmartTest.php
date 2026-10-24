<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionChat;
use App\Domain\Ai\Missions\MissionChatStats;
use App\Enums\AiMissionMessageKind;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionThreadStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionMessage;
use App\Models\AiMissionPin;
use App\Models\AiMissionRole;
use App\Models\AiMissionThread;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\MissionAttentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The team chat's third round: a clarifying question before spending, a cap
 * and an estimate per conversation, a notification when a long answer is
 * ready, ready-made questions, a one-off attachment, a better directive from
 * thumbs-down answers, parallel specialists and a one-sentence decision.
 */
class AiMissionChatSmartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<array{kind: string, data: array<string, mixed>}> */
    private array $calls = [];

    /** @var array<string, string> */
    private array $answers = [];

    private bool $faked = false;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    /**
     * @return array{mission: AiMission, specialists: list<AiAssistantProfile>}
     */
    private function team(int $specialists = 2): array
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'پر کردن اتاق‌ها']);
        $profiles = [];

        foreach (range(1, $specialists) as $number) {
            $profile = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => "متخصص شمارهٔ {$number}"]);
            AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $profile->id, 'role' => AiMissionRoleType::Specialist, 'directive' => "تخصص {$number}"]);
            $profiles[] = $profile;
        }

        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'هماهنگ‌کنندهٔ تیم']);
        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $coordinator->id, 'role' => AiMissionRoleType::Coordinator]);

        return ['mission' => $mission, 'specialists' => $profiles];
    }

    /**
     * @param  array<string, string>  $answers  kind (triage|specialist|discuss|critic|final) => content
     */
    private function fakeChat(array $answers): void
    {
        $this->calls = [];
        $this->answers = $answers;

        if ($this->faked) {
            return;
        }

        $this->faked = true;

        Http::fake(['*/chat/completions' => function (Request $request) {
            $system = $request->data()['messages'][0]['content'];
            $kind = match (true) {
                str_contains($system, 'فقط تصمیم بگیر کدام متخصص‌ها') => 'triage',
                str_contains($system, 'جواب نهایی را کامل و مستقیم') => 'final',
                str_contains($system, 'تو منتقد این تیم هستی') => 'critic',
                str_contains($system, 'حالا نظر همکارانت را دیده‌ای') => 'discuss',
                default => 'specialist',
            };
            $this->calls[] = ['kind' => $kind, 'data' => $request->data()];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $this->answers[$kind] ?? 'جواب']]], 'usage' => ['total_tokens' => 10]]);
        }]);
    }

    /**
     * @return list<string>
     */
    private function kinds(): array
    {
        return array_column($this->calls, 'kind');
    }

    private function triageBoth(AiAssistantProfile $first, AiAssistantProfile $second): string
    {
        return json_encode(['ask' => [['id' => $first->id, 'question' => 'الف؟'], ['id' => $second->id, 'question' => 'ب؟']]], JSON_UNESCAPED_UNICODE);
    }

    private function send(AiMission $mission, string $text, string $depth = 'normal', ?AiMissionThread $thread = null): void
    {
        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('threadId', $thread?->id)
            ->set('chatMessage', $text)
            ->set('chatDepth', $depth)
            ->call('sendChat');
    }

    public function test_the_coordinator_asks_one_clarifying_question_and_the_reply_reruns_the_team(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team();
        $this->fakeChat(['triage' => json_encode(['ask' => [], 'answer' => '', 'clarify' => 'برای چه بازه‌ای؟'], JSON_UNESCAPED_UNICODE)]);

        $this->send($mission, 'یک سناریو بنویس');

        $this->assertSame(['triage'], $this->kinds());
        $this->assertStringContainsString('clarify', $this->calls[0]['data']['messages'][0]['content']);
        $thread = AiMissionThread::sole();
        $question = $thread->messages->last();
        $this->assertSame(AiMissionMessageKind::Final, $question->kind);
        $this->assertSame('برای چه بازه‌ای؟', $question->body);
        $this->assertTrue($question->meta['clarify']);
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->status);

        $this->fakeChat(['triage' => $this->triageBoth($first, $second), 'final' => 'سناریوی کامل']);
        $this->send($mission, 'هفتهٔ آینده', thread: $thread);

        $this->assertSame(['triage', 'specialist', 'specialist', 'discuss', 'discuss', 'final'], $this->kinds());
        $this->assertStringContainsString('درخواست اولیه: یک سناریو بنویس', $this->calls[0]['data']['messages'][1]['content']);
        $this->assertStringContainsString('توضیح مدیر: هفتهٔ آینده', $this->calls[0]['data']['messages'][1]['content']);
        $this->assertStringNotContainsString('clarify', $this->calls[0]['data']['messages'][0]['content']);
    }

    public function test_the_cheap_mode_never_stops_to_ask_a_question(): void
    {
        ['mission' => $mission] = $this->team();
        $this->fakeChat(['triage' => json_encode(['ask' => [], 'answer' => '', 'clarify' => 'کدام هفته؟'], JSON_UNESCAPED_UNICODE)]);

        $this->send($mission, 'یک سناریو بنویس', depth: 'fast');

        $this->assertSame(['triage', 'specialist', 'final'], $this->kinds());
        $this->assertFalse(AiMissionMessage::all()->contains(fn ($message) => $message->meta['clarify'] ?? false));
    }

    public function test_a_conversation_stops_at_its_token_cap_and_continues_after_the_cap_is_raised(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $mission->update(['thread_token_cap' => 10]);
        $this->fakeChat(['specialist' => 'نظر', 'final' => 'جواب']);

        $this->send($mission, 'سؤال');

        $thread = AiMissionThread::sole();
        $this->assertSame(AiMissionThreadStatus::Failed, $thread->status);
        $this->assertStringContainsString('سقف توکن این گفت‌وگو', $thread->last_error);
        $this->assertSame(['specialist'], $this->kinds());

        $mission->update(['thread_token_cap' => 5000]);
        $chat = app(MissionChat::class);
        $this->assertTrue($chat->resume($thread->refresh(), $this->admin)['ok']);

        do {
            $step = $chat->runStep($thread, $this->admin);
        } while ($step['ok'] && ! $step['done']);

        $this->assertSame(['specialist', 'final'], $this->kinds());
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->refresh()->status);

        $mission->update(['thread_token_cap' => 15]);
        $sent = $chat->send($mission->refresh(), $thread, 'ادامه', false, $this->admin);

        $this->assertFalse($sent['ok']);
        $this->assertStringContainsString('سقف توکن این گفت‌وگو', $sent['error']);
    }

    public function test_the_thread_cap_is_saved_from_the_page_or_removed_when_empty(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('saveThreadCap', '40000');
        $this->assertSame(40000, $mission->refresh()->thread_token_cap);

        $component->call('saveThreadCap', '100');
        $this->assertSame(40000, $mission->refresh()->thread_token_cap);

        $component->call('saveThreadCap', '');
        $this->assertNull($mission->refresh()->thread_token_cap);
    }

    public function test_the_estimate_averages_earlier_conversations_of_the_same_depth(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        $add = fn (AiMissionMessageKind $kind, int $tokens = 0, array $meta = []) => AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => $kind, 'tokens' => $tokens, 'meta' => $meta ?: null]);

        $add(AiMissionMessageKind::User, 0, ['depth' => 'normal', 'follow_up' => false]);
        $add(AiMissionMessageKind::Answer, 100);
        $add(AiMissionMessageKind::Final, 50);
        $add(AiMissionMessageKind::User, 0, ['depth' => 'normal', 'follow_up' => false]);
        $add(AiMissionMessageKind::Answer, 250);
        $add(AiMissionMessageKind::Final, 50);
        $add(AiMissionMessageKind::User, 0, ['depth' => 'normal', 'follow_up' => true]);
        $add(AiMissionMessageKind::Final, 80);
        $stats = app(MissionChatStats::class);

        $this->assertSame(['tokens' => 225, 'samples' => 2], $stats->estimate($mission, 'normal', false));
        $this->assertSame(['tokens' => 80, 'samples' => 1], $stats->estimate($mission, 'fast', true));
        $this->assertNull($stats->estimate($mission, 'deep', false));
    }

    public function test_a_long_conversation_notifies_the_manager_and_a_quick_one_stays_silent(): void
    {
        Notification::fake();
        ['mission' => $mission] = $this->team(specialists: 1);
        $this->fakeChat(['specialist' => 'نظر', 'final' => 'جواب']);
        $chat = app(MissionChat::class);

        $this->send($mission, 'سؤال سریع');
        Notification::assertNothingSent();

        $sent = $chat->send($mission, null, 'سؤال طولانی', false, $this->admin);
        Carbon::setTestNow(now()->addMinute());

        do {
            $step = $chat->runStep($sent['thread'], $this->admin);
        } while ($step['ok'] && ! $step['done']);

        Carbon::setTestNow();
        Notification::assertSentTo($this->admin, MissionAttentionNotification::class);
    }

    public function test_quick_prompts_are_saved_reused_removed_and_capped(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->set('chatMessage', 'برنامهٔ هفتهٔ آینده')
            ->call('addQuickPrompt')
            ->call('addQuickPrompt');

        $this->assertSame(['برنامهٔ هفتهٔ آینده'], $mission->refresh()->quick_prompts);

        $component->set('chatMessage', '')->call('useQuickPrompt', 0)->assertSet('chatMessage', 'برنامهٔ هفتهٔ آینده');
        $component->call('removeQuickPrompt', 0);
        $this->assertSame([], $mission->refresh()->quick_prompts);

        $mission->update(['quick_prompts' => array_map(fn (int $number) => "پرسش {$number}", range(1, 8))]);
        $component->set('chatMessage', 'پرسش نهم')->call('addQuickPrompt');
        $this->assertCount(8, $mission->refresh()->quick_prompts);
    }

    public function test_a_one_off_attachment_is_read_shortened_and_given_to_the_specialist(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $this->fakeChat(['specialist' => 'نظر', 'final' => 'جواب']);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->set('chatMessage', 'این فایل را ببین')
            ->set('chatAttachment', UploadedFile::fake()->createWithContent('plan.txt', str_repeat('الف', 7000)))
            ->call('sendChat')
            ->assertSet('chatAttachment', null);

        $user = AiMissionMessage::where('kind', AiMissionMessageKind::User)->sole();
        $this->assertSame('plan.txt', $user->meta['attachment']);
        $this->assertSame(MissionChat::MAX_ATTACHMENT_CHARS, mb_strlen($user->attachment_text));
        $this->assertStringContainsString('پیوست مدیر', $this->calls[0]['data']['messages'][1]['content']);
        $this->assertSame(0, $mission->sources()->count());
    }

    public function test_an_unsupported_attachment_is_refused(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->set('chatMessage', 'این را ببین')
            ->set('chatAttachment', UploadedFile::fake()->createWithContent('virus.exe', 'MZ'))
            ->call('sendChat')
            ->assertHasErrors('chatAttachment');

        $this->assertSame(0, AiMissionThread::count());
    }

    public function test_a_better_directive_is_proposed_from_thumbs_down_answers_and_applied_only_on_approval(): void
    {
        ['mission' => $mission, 'specialists' => [$first]] = $this->team(specialists: 2);
        $role = AiMissionRole::where('ai_mission_id', $mission->id)->where('ai_assistant_profile_id', $first->id)->sole();
        $other = AiMissionRole::where('ai_mission_id', $mission->id)->where('role', AiMissionRoleType::Specialist)->where('id', '!=', $role->id)->sole();
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'ai_assistant_profile_id' => $first->id, 'role' => AiMissionRoleType::Specialist, 'kind' => AiMissionMessageKind::Answer, 'body' => 'عدد حدسی', 'rating' => -1]);
        $this->fakeChat(['specialist' => '{"directive":"فقط عددهای تأییدشده را بگو و منبع بیاور","reason":"جواب‌های ضعیف عدد حدسی داشتند"}']);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('suggestDirective', $other->id)->assertSet('suggestedDirectives', []);

        $component->call('suggestDirective', $role->id)
            ->assertSet('suggestedDirectives.'.$role->id.'.directive', 'فقط عددهای تأییدشده را بگو و منبع بیاور');
        $this->assertSame('تخصص 1', $role->refresh()->directive);

        $component->call('applyDirective', $role->id)->assertSet('suggestedDirectives', []);
        $this->assertSame('فقط عددهای تأییدشده را بگو و منبع بیاور', $role->refresh()->directive);
        $this->assertSame(10, $mission->refresh()->tokens_used);
    }

    public function test_parallel_specialists_answer_as_separate_jobs_and_the_conversation_still_completes(): void
    {
        config(['ai_assistant.team_chat_parallel' => true]);
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team();
        $this->fakeChat(['triage' => $this->triageBoth($first, $second), 'specialist' => 'نظر', 'final' => 'جواب']);

        $this->send($mission, 'بررسی کن');

        $this->assertSame(['triage', 'specialist', 'specialist', 'discuss', 'discuss', 'final'], $this->kinds());
        $thread = AiMissionThread::sole();
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->status);
        $this->assertSame(4, $thread->messages->where('kind', AiMissionMessageKind::Answer)->count());
        $this->assertSame(AiMissionMessageKind::Final, $thread->messages->last()->kind);
    }

    public function test_a_marked_decision_is_hidden_from_the_text_and_pinned_in_one_click(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $this->fakeChat(['specialist' => 'نظر', 'final' => "جواب کامل.\n[[decision]]قیمت سه‌شنبه را ۱۰٪ بالا ببر[[/decision]]"]);

        $this->send($mission, 'قیمت چه شود؟');

        $final = AiMissionMessage::where('kind', AiMissionMessageKind::Final)->sole();
        $this->assertSame('جواب کامل.', $final->body);
        $this->assertSame('قیمت سه‌شنبه را ۱۰٪ بالا ببر', $final->meta['decision']);

        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('openThread', $final->ai_mission_thread_id);
        $component->assertSee('تصمیم پیشنهادی')->call('pinDecision', $final->id)->call('pinDecision', $final->id);

        $this->assertSame('قیمت سه‌شنبه را ۱۰٪ بالا ببر', AiMissionPin::sole()->text);
    }

    public function test_the_page_shows_the_attachment_cap_and_estimate_controls(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->assertSee('پیوست یک‌بارمصرف')
            ->assertSee('سقف هر گفت‌وگو')
            ->assertSee('هنوز برای این حالت تخمینی نداریم');
    }
}
