<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionChat;
use App\Enums\AiMissionMessageKind;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionThreadStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Jobs\RunMissionChatStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionRole;
use App\Models\AiMissionThread;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The standing-team chat: a request goes to the specialists the coordinator
 * picks, the critic doubts when two answered, the coordinator answers, and a
 * follow-up costs only one coordinator call.
 */
class AiMissionChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<array{kind: string, data: array<string, mixed>}> */
    private array $calls = [];

    /** @var array<string, string> */
    private array $answers = [];

    private int $tokensPerCall = 10;

    private bool $faked = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    /**
     * @return array{mission: AiMission, specialists: list<AiAssistantProfile>}
     */
    private function team(int $specialists = 1, bool $withCritic = false): array
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'پر کردن اتاق‌ها']);
        $profiles = [];

        foreach (range(1, $specialists) as $number) {
            $profile = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => "متخصص شمارهٔ {$number}"]);
            AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $profile->id, 'role' => AiMissionRoleType::Specialist, 'directive' => "تخصص {$number}"]);
            $profiles[] = $profile;
        }

        if ($withCritic) {
            $critic = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'منتقد تیم']);
            AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $critic->id, 'role' => AiMissionRoleType::Critic]);
        }

        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'هماهنگ‌کنندهٔ تیم']);
        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $coordinator->id, 'role' => AiMissionRoleType::Coordinator]);

        return ['mission' => $mission, 'specialists' => $profiles];
    }

    /**
     * Answers each call by what the system prompt asks for and records it.
     * The HTTP fake is registered once (stubs accumulate and every one runs);
     * calling this again only swaps the answers and clears the record.
     *
     * @param  array<string, string>  $answers  kind (triage|specialist|critic|final) => content
     */
    private function fakeChat(array $answers, int $tokens = 10): void
    {
        $this->calls = [];
        $this->answers = $answers;
        $this->tokensPerCall = $tokens;

        if ($this->faked) {
            return;
        }

        $this->faked = true;

        Http::fake(['*/chat/completions' => function (Request $request) {
            $system = $request->data()['messages'][0]['content'];
            $kind = match (true) {
                str_contains($system, 'فقط تصمیم بگیر کدام متخصص‌ها') => 'triage',
                str_contains($system, 'جواب نهایی را مستقیم') => 'final',
                str_contains($system, 'تو منتقد این تیم هستی') => 'critic',
                default => 'specialist',
            };
            $this->calls[] = ['kind' => $kind, 'data' => $request->data()];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $this->answers[$kind] ?? 'جواب']]], 'usage' => ['total_tokens' => $this->tokensPerCall]]);
        }]);
    }

    /**
     * @return list<string>
     */
    private function callKinds(): array
    {
        return array_column($this->calls, 'kind');
    }

    private function send(AiMission $mission, string $text, bool $askTeam = false, ?AiMissionThread $thread = null): void
    {
        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('threadId', $thread?->id)
            ->set('chatMessage', $text)
            ->set('askWholeTeam', $askTeam)
            ->call('sendChat');
    }

    public function test_a_one_specialist_team_skips_the_planning_call_and_the_coordinator_sends_no_tools(): void
    {
        ['mission' => $mission] = $this->team();
        $this->fakeChat(['specialist' => 'سه‌شنبه ۲ اتاق خالی است.', 'final' => 'سناریو: تخفیف سه‌شنبه.']);

        $this->send($mission, 'یک سناریو بنویس');

        $thread = AiMissionThread::sole();
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->status);
        $this->assertSame(['specialist', 'final'], $this->callKinds());
        $this->assertSame(
            [AiMissionMessageKind::User, AiMissionMessageKind::Answer, AiMissionMessageKind::Final],
            $thread->messages->pluck('kind')->all(),
        );
        $this->assertSame('سناریو: تخفیف سه‌شنبه.', $thread->messages->last()->body);
        $this->assertArrayNotHasKey('tools', $this->calls[1]['data']);
        $this->assertSame(20, $thread->tokens_used);
        $this->assertSame(20, $mission->refresh()->tokens_used);
    }

    public function test_the_coordinator_calls_only_the_specialists_it_needs(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2, withCritic: true);
        $this->fakeChat([
            'triage' => json_encode(['ask' => [['id' => $second->id, 'question' => 'وضعیت قیمت چیست؟']], 'answer' => ''], JSON_UNESCAPED_UNICODE),
            'specialist' => 'قیمت پایین است.',
            'final' => 'قیمت را بالا ببر.',
        ]);

        $this->send($mission, 'قیمت‌ها را بررسی کن');

        $this->assertSame(['triage', 'specialist', 'final'], $this->callKinds());
        $thread = AiMissionThread::sole();
        $plan = $thread->messages->firstWhere('kind', AiMissionMessageKind::Plan);
        $this->assertStringContainsString('متخصص شمارهٔ 2', $plan->body);
        $this->assertStringNotContainsString('متخصص شمارهٔ 1', $plan->body);
        $this->assertSame($second->id, $thread->messages->firstWhere('kind', AiMissionMessageKind::Answer)->ai_assistant_profile_id);
        $this->assertFalse($thread->messages->contains('kind', AiMissionMessageKind::Critique));
    }

    public function test_the_critic_speaks_only_when_two_specialists_answered(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2, withCritic: true);
        $this->fakeChat([
            'triage' => json_encode(['ask' => [['id' => $first->id, 'question' => 'الف؟'], ['id' => $second->id, 'question' => 'ب؟']]], JSON_UNESCAPED_UNICODE),
            'critic' => 'عدد بی‌منبع است.',
        ]);

        $this->send($mission, 'بررسی کن');

        $this->assertSame(['triage', 'specialist', 'specialist', 'specialist', 'specialist', 'critic', 'final'], $this->callKinds());
        $this->assertTrue(AiMissionThread::sole()->messages->contains(fn ($message) => $message->kind === AiMissionMessageKind::Critique && $message->body === 'عدد بی‌منبع است.'));
        $this->assertArrayNotHasKey('tools', $this->calls[5]['data']);
    }

    public function test_members_read_each_others_full_answers_and_discuss_once(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2);
        $this->fakeChat([
            'triage' => json_encode(['ask' => [['id' => $first->id, 'question' => 'الف؟'], ['id' => $second->id, 'question' => 'ب؟']]], JSON_UNESCAPED_UNICODE),
            'specialist' => 'پاسخ کامل همکار با همهٔ جزئیات',
            'final' => 'جواب نهایی',
        ]);

        $this->send($mission, 'بررسی کن');

        $this->assertSame(['triage', 'specialist', 'specialist', 'specialist', 'specialist', 'final'], $this->callKinds());
        $this->assertStringContainsString('نظر همکاران', $this->calls[3]['data']['messages'][1]['content']);
        $this->assertStringContainsString('پاسخ کامل همکار با همهٔ جزئیات', $this->calls[3]['data']['messages'][1]['content']);
        $this->assertSame(4, AiMissionThread::sole()->messages->where('kind', AiMissionMessageKind::Answer)->count());
    }

    public function test_a_request_that_needs_no_specialist_is_answered_by_the_coordinator_in_one_call(): void
    {
        ['mission' => $mission] = $this->team(specialists: 2);
        $this->fakeChat(['triage' => json_encode(['ask' => [], 'answer' => 'سلام! چه کمکی لازم دارید؟'], JSON_UNESCAPED_UNICODE)]);

        $this->send($mission, 'سلام');

        $this->assertSame(['triage'], $this->callKinds());
        $thread = AiMissionThread::sole();
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->status);
        $this->assertSame('سلام! چه کمکی لازم دارید؟', $thread->messages->last()->body);
        $this->assertSame(AiMissionMessageKind::Final, $thread->messages->last()->kind);
    }

    public function test_a_follow_up_is_answered_by_the_coordinator_alone_from_the_last_answer(): void
    {
        ['mission' => $mission] = $this->team();
        $this->fakeChat(['specialist' => 'نظر متخصص', 'final' => 'جواب اول تیم']);
        $this->send($mission, 'سؤال اول');
        $thread = AiMissionThread::sole();

        $this->fakeChat(['final' => 'جواب کوتاه‌تر']);
        $this->send($mission, 'کوتاه‌ترش کن', thread: $thread);

        $this->assertSame(['final'], $this->callKinds());
        $this->assertStringContainsString('جواب اول تیم', $this->calls[0]['data']['messages'][1]['content']);
        $this->assertStringContainsString('کوتاه‌ترش کن', $this->calls[0]['data']['messages'][1]['content']);
        $this->assertSame('جواب کوتاه‌تر', $thread->refresh()->messages->last()->body);

        $this->fakeChat(['specialist' => 'نظر تازه', 'final' => 'جواب تازه']);
        $this->send($mission, 'دوباره از همه بپرس', askTeam: true, thread: $thread);

        $this->assertSame(['specialist', 'final'], $this->callKinds());
    }

    public function test_a_conversation_that_ran_out_of_budget_continues_without_paying_twice(): void
    {
        Queue::fake();
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2);
        $mission->update(['token_budget' => 100]);
        $this->fakeChat([
            'triage' => json_encode(['ask' => [['id' => $first->id, 'question' => 'الف؟'], ['id' => $second->id, 'question' => 'ب؟']]], JSON_UNESCAPED_UNICODE),
            'final' => 'جمع‌بندی',
        ], tokens: 40);
        $chat = app(MissionChat::class);

        $sent = $chat->send($mission, null, 'بررسی کن', false, $this->admin);
        $thread = $sent['thread'];

        foreach (range(1, 5) as $step) {
            if (! $chat->runStep($thread, $this->admin)['ok']) {
                break;
            }
        }

        $thread->refresh();
        $this->assertSame(AiMissionThreadStatus::Failed, $thread->status);
        $this->assertStringContainsString('سقف توکن', $thread->last_error);
        $this->assertSame(['triage', 'specialist', 'specialist'], $this->callKinds());

        $mission->update(['token_budget' => 5000]);
        $this->assertTrue($chat->resume($thread, $this->admin)['ok']);

        do {
            $step = $chat->runStep($thread, $this->admin);
        } while ($step['ok'] && ! $step['done']);

        $this->assertSame(['triage', 'specialist', 'specialist', 'specialist', 'specialist', 'final'], $this->callKinds());
        $this->assertSame(AiMissionThreadStatus::Idle, $thread->refresh()->status);
        $this->assertSame(4, $thread->messages->where('kind', AiMissionMessageKind::Answer)->count());
    }

    public function test_only_the_owner_can_chat_and_an_empty_message_or_missing_coordinator_is_refused(): void
    {
        ['mission' => $mission] = $this->team();
        $chat = app(MissionChat::class);
        $stranger = User::factory()->create();

        $this->assertFalse($chat->send($mission, null, 'سلام', false, $stranger)['ok']);
        $this->assertFalse($chat->send($mission, null, '   ', false, $this->admin)['ok']);

        $bare = AiMission::factory()->create(['created_by' => $this->admin->id]);
        $this->assertFalse($chat->send($bare, null, 'سلام', false, $this->admin)['ok']);
        $this->assertSame(0, AiMissionThread::count());
    }

    public function test_the_page_queues_the_first_step_and_shows_the_conversation(): void
    {
        Queue::fake();
        ['mission' => $mission] = $this->team();

        $this->send($mission, 'یک سناریو بنویس');

        Queue::assertPushed(RunMissionChatStepJob::class);
        $thread = AiMissionThread::sole();
        $this->assertSame(AiMissionThreadStatus::Running, $thread->status);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('openThread', $thread->id)
            ->assertSee('گفت‌وگو با تیم')
            ->assertSee('یک سناریو بنویس')
            ->assertSee('تیم در حال گفت‌وگوست');
    }
}
