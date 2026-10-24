<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionChat;
use App\Domain\Ai\Missions\MissionChatStats;
use App\Enums\AiMissionActionStatus;
use App\Enums\AiMissionMessageKind;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionThreadStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use App\Models\AiMissionMessage;
use App\Models\AiMissionPin;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiMissionSourceChunk;
use App\Models\AiMissionThread;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The team chat's second round of improvements: pinned decisions the team
 * remembers, depth modes, "@name" asks, a debate that continues only when
 * there is something to debate, actions proposed from an answer, ratings and
 * the team-health table.
 */
class AiMissionChatBoostTest extends TestCase
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    /**
     * @return array{mission: AiMission, specialists: list<AiAssistantProfile>, coordinator: AiAssistantProfile}
     */
    private function team(int $specialists = 2, bool $withCritic = false): array
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

        return ['mission' => $mission, 'specialists' => $profiles, 'coordinator' => $coordinator];
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

    private function send(AiMission $mission, string $text, string $depth = 'normal', ?AiMissionThread $thread = null, bool $askTeam = false): void
    {
        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('threadId', $thread?->id)
            ->set('chatMessage', $text)
            ->set('chatDepth', $depth)
            ->set('askWholeTeam', $askTeam)
            ->call('sendChat');
    }

    public function test_a_pinned_decision_reaches_the_members_and_the_coordinator_of_later_conversations(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $this->fakeChat(['specialist' => 'نظر', 'final' => 'جواب اول']);
        $this->send($mission, 'سؤال اول');
        $final = AiMissionMessage::where('kind', AiMissionMessageKind::Final)->sole();

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('pinMessage', $final->id);

        $this->assertSame('جواب اول', AiMissionPin::sole()->text);

        $this->fakeChat(['specialist' => 'نظر', 'final' => 'جواب دوم']);
        $this->send($mission, 'سؤال دوم');

        $this->assertStringContainsString('تصمیم‌های تأییدشدهٔ مدیر', $this->calls[0]['data']['messages'][0]['content']);
        $this->assertStringContainsString('جواب اول', $this->calls[0]['data']['messages'][0]['content']);
        $this->assertStringContainsString('جواب اول', $this->calls[1]['data']['messages'][0]['content']);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('removePin', AiMissionPin::sole()->id);

        $this->assertSame(0, AiMissionPin::count());
    }

    public function test_only_a_final_answer_of_your_own_mission_can_be_pinned_and_the_list_is_capped(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        $answer = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Answer]);
        $final = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Final, 'body' => 'نهایی']);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('pinMessage', $answer->id);
        $this->assertSame(0, AiMissionPin::count());

        AiMissionPin::factory()->count(AiMissionPin::MAX_PER_MISSION)->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('pinMessage', $final->id);

        $this->assertSame(AiMissionPin::MAX_PER_MISSION, AiMissionPin::count());
    }

    public function test_the_fast_mode_asks_one_specialist_and_skips_the_discussion_and_the_critic(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2, withCritic: true);
        $this->fakeChat(['triage' => $this->triageBoth($first, $second)]);

        $this->send($mission, 'بررسی کن', depth: 'fast');

        $this->assertSame(['triage', 'specialist', 'final'], $this->kinds());
    }

    public function test_at_name_asks_that_specialist_directly_without_planning_and_beats_the_follow_up(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2);
        $this->fakeChat(['specialist' => 'نظر دوم', 'final' => 'جواب']);

        $this->send($mission, '@متخصص شمارهٔ 2 قیمت را بررسی کن');

        $this->assertSame(['specialist', 'final'], $this->kinds());
        $thread = AiMissionThread::sole();
        $this->assertSame($second->id, $thread->messages->firstWhere('kind', AiMissionMessageKind::Answer)->ai_assistant_profile_id);
        $this->assertFalse($thread->messages->contains('kind', AiMissionMessageKind::Plan));

        $this->fakeChat(['specialist' => 'نظر اول', 'final' => 'جواب دوم']);
        $this->send($mission, '@متخصص شمارهٔ 1 تو هم بگو', thread: $thread);

        $this->assertSame(['specialist', 'final'], $this->kinds());
        $this->assertSame($first->id, $thread->refresh()->messages->where('kind', AiMissionMessageKind::Answer)->last()->ai_assistant_profile_id);
    }

    public function test_a_deep_conversation_debates_again_only_when_the_critic_objects(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2, withCritic: true);
        $this->fakeChat(['triage' => $this->triageBoth($first, $second), 'critic' => 'عدد حدسی است و منبع ندارد.']);

        $this->send($mission, 'بررسی کن', depth: 'deep');

        $this->assertSame(['triage', 'specialist', 'specialist', 'discuss', 'discuss', 'critic', 'discuss', 'discuss', 'final'], $this->kinds());
        $this->assertStringContainsString('نقد منتقد', $this->calls[6]['data']['messages'][1]['content']);

        $this->fakeChat(['triage' => $this->triageBoth($first, $second), 'critic' => 'ایرادی نیست.']);
        $this->send($mission, 'یک بررسی دیگر', depth: 'deep', thread: AiMissionThread::sole(), askTeam: true);

        $this->assertSame(['triage', 'specialist', 'specialist', 'discuss', 'discuss', 'critic', 'final'], $this->kinds());
    }

    public function test_a_member_who_just_agrees_keeps_the_original_answer_for_the_coordinator(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second]] = $this->team(specialists: 2);
        $this->fakeChat(['triage' => $this->triageBoth($first, $second), 'specialist' => 'تحلیل کامل و دقیق الف', 'discuss' => 'موافقم، دلیلش درست است.']);

        $this->send($mission, 'بررسی کن');

        $finalPrompt = $this->calls[5]['data']['messages'][1]['content'];
        $this->assertSame('final', $this->calls[5]['kind']);
        $this->assertStringContainsString('تحلیل کامل و دقیق الف', $finalPrompt);
        $this->assertStringContainsString('موافقم، دلیلش درست است.', $finalPrompt);
    }

    public function test_the_coordinator_can_propose_a_task_that_waits_for_approval_and_the_block_is_hidden(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $block = '[[actions]][{"tool":"create_task","title":"تماس با مشتریان سه‌شنبه","priority":"urgent_important","reason":"پر کردن اتاق"}][[/actions]]';
        $this->fakeChat(['specialist' => 'نظر', 'final' => "برنامه این است.\n{$block}"]);

        $this->send($mission, 'یک سناریو بنویس');

        $action = AiMissionAction::sole();
        $this->assertSame(AiMissionActionStatus::Pending, $action->status);
        $this->assertSame('تماس با مشتریان سه‌شنبه', $action->arguments['title']);
        $final = AiMissionMessage::where('kind', AiMissionMessageKind::Final)->sole();
        $this->assertSame('برنامه این است.', $final->body);
        $this->assertSame(1, $final->meta['actions']);
    }

    public function test_ratings_toggle_and_the_team_health_table_adds_tokens_and_votes_per_member(): void
    {
        ['mission' => $mission, 'specialists' => [$first]] = $this->team(specialists: 1);
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        $good = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'ai_assistant_profile_id' => $first->id, 'role' => AiMissionRoleType::Specialist, 'kind' => AiMissionMessageKind::Answer, 'tokens' => 300]);
        AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'ai_assistant_profile_id' => $first->id, 'role' => AiMissionRoleType::Specialist, 'kind' => AiMissionMessageKind::Answer, 'tokens' => 100, 'rating' => -1]);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('rateMessage', $good->id, 1);
        $this->assertSame(1, $good->refresh()->rating);

        $component->call('rateMessage', $good->id, 1);
        $this->assertNull($good->refresh()->rating);

        $component->call('rateMessage', $good->id, 1);
        $row = app(MissionChatStats::class)->forMission($mission)->sole();

        $this->assertSame(2, $row['messages']);
        $this->assertSame(400, $row['tokens']);
        $this->assertSame(200, $row['average']);
        $this->assertSame(1, $row['up']);
        $this->assertSame(1, $row['down']);
    }

    public function test_the_working_member_follows_the_stage_of_a_running_conversation(): void
    {
        ['mission' => $mission, 'specialists' => [$first, $second], 'coordinator' => $coordinator] = $this->team(specialists: 2);
        $mission->load('roles.profile');
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id, 'status' => AiMissionThreadStatus::Running, 'stage' => 'ask', 'plan' => ['asks' => [['profile_id' => $first->id, 'question' => 'ا', 'done' => true], ['profile_id' => $second->id, 'question' => 'ب', 'done' => false]]]]);
        $chat = app(MissionChat::class);

        $this->assertSame($second->id, $chat->workingMember($thread, $mission)->ai_assistant_profile_id);

        $thread->update(['stage' => 'final']);
        $this->assertSame($coordinator->id, $chat->workingMember($thread, $mission)->ai_assistant_profile_id);

        $thread->update(['status' => AiMissionThreadStatus::Idle]);
        $this->assertNull($chat->workingMember($thread, $mission));
    }

    public function test_the_page_shows_the_new_controls(): void
    {
        ['mission' => $mission] = $this->team(specialists: 2);
        AiMissionPin::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id, 'text' => 'قیمت سه‌شنبه را بالا ببر']);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->assertSee('قیمت سه‌شنبه را بالا ببر')
            ->assertSee('@متخصص شمارهٔ 1')
            ->assertSee('سریع')
            ->assertSee('عمیق');
    }

    public function test_a_member_that_searched_the_files_gets_source_chips_that_the_final_answer_repeats(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $source = AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'title' => 'استراتژی اشغال', 'content' => 'نرخ اشغال هدف ۷۵ درصد است.']);
        AiMissionSourceChunk::factory()->create(['ai_mission_source_id' => $source->id, 'position' => 1, 'content' => 'نرخ اشغال هدف ۷۵ درصد است.']);
        Http::fake(['*/chat/completions' => function (Request $request) {
            $data = $request->data();
            $searched = collect($data['messages'])->contains('role', 'tool');
            $tools = collect($data['tools'] ?? [])->pluck('function.name')->all();

            if (in_array('search_mission_sources', $tools, true) && ! $searched) {
                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_mission_sources', 'arguments' => json_encode(['query' => 'نرخ اشغال'], JSON_UNESCAPED_UNICODE)]]]]]]]);
            }

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'جواب بر پایهٔ فایل']]]]);
        }]);

        $this->send($mission, 'هدف اشغال چیست؟');

        $answer = AiMissionMessage::where('kind', AiMissionMessageKind::Answer)->sole();
        $final = AiMissionMessage::where('kind', AiMissionMessageKind::Final)->sole();
        $this->assertSame(['استراتژی اشغال'], $answer->meta['sources']);
        $this->assertSame(['استراتژی اشغال'], $final->meta['sources']);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('openThread', $final->ai_mission_thread_id)
            ->assertSee('استراتژی اشغال');
    }

    public function test_a_final_answer_downloads_as_a_markdown_file_and_other_messages_do_not(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id, 'title' => 'سناریوی سه‌شنبه']);
        AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::User, 'body' => 'یک سناریو بنویس']);
        $answer = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Answer]);
        $final = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Final, 'body' => 'متن کامل جواب', 'meta' => ['sources' => ['استراتژی اشغال']]]);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('downloadMessage', $answer->id)->assertNoFileDownloaded();
        $component->call('downloadMessage', $final->id)->assertFileDownloaded('team-answer-'.$final->id.'.md');
    }

    public function test_a_final_answer_can_be_turned_into_tasks_waiting_for_approval_once(): void
    {
        ['mission' => $mission] = $this->team(specialists: 1);
        $thread = AiMissionThread::factory()->create(['ai_mission_id' => $mission->id, 'created_by' => $this->admin->id]);
        $answer = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Answer]);
        $final = AiMissionMessage::factory()->create(['ai_mission_thread_id' => $thread->id, 'kind' => AiMissionMessageKind::Final, 'body' => 'باید با مشتریان سه‌شنبه تماس گرفت.']);
        $this->fakeChat(['specialist' => 'متن اضافه: [{"tool":"create_task","title":"تماس با مشتریان","priority":"urgent_important","reason":"پر کردن اتاق"}]']);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('extractTasks', $answer->id);
        $this->assertSame(0, AiMissionAction::count());

        $component->call('extractTasks', $final->id)->call('extractTasks', $final->id);

        $action = AiMissionAction::sole();
        $this->assertSame(AiMissionActionStatus::Pending, $action->status);
        $this->assertSame('تماس با مشتریان', $action->arguments['title']);
        $this->assertSame(20, $thread->refresh()->tokens_used);
    }
}
