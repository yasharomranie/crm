<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionBrief;
use App\Domain\Ai\Missions\MissionCompliance;
use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionBriefStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\MissionAttentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * After a round, a second member weighs the decisions and proposed actions
 * against the approved strategy brief and notes which goals were touched. It
 * only flags; the manager still decides.
 */
class AiMissionComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'مدیر آزمایشی']);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    private function mission(bool $withCritic = true, bool $withBrief = true): AiMission
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'برنامهٔ هفتهٔ آینده']);
        $roles = [AiMissionRoleType::Specialist, AiMissionRoleType::Coordinator, ...($withCritic ? [AiMissionRoleType::Critic] : [])];

        foreach ($roles as $type) {
            AiMissionRole::factory()->create([
                'ai_mission_id' => $mission->id,
                'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'عضو '.$type->value])->id,
                'role' => $type,
            ]);
        }

        AiMissionSource::factory()->create(['ai_mission_id' => $mission->id]);

        if ($withBrief) {
            $mission->update([
                'brief' => ['summary' => 'خلاصه', 'goals' => ['افزایش اشغال', 'کاهش لغو'], 'red_lines' => ['زیر قیمت تمام‌شده نفروش']],
                'brief_status' => AiMissionBriefStatus::Approved,
                'brief_sources_hash' => MissionBrief::sourcesHash($mission),
                'brief_approved_at' => now(),
            ]);
        }

        return $mission;
    }

    /**
     * Fakes the whole team; $compliance is what the checking member answers
     * (a callable, or null to answer with an error).
     *
     * @param  callable(): array<string, mixed>  $compliance
     * @param  list<string>  $checks  filled with the user message of every compliance request
     */
    private function fakeTeam(?callable $compliance, array &$checks = []): void
    {
        Http::fake(['*/chat/completions' => function (Request $request) use ($compliance, &$checks) {
            $messages = $request->data()['messages'];
            $system = $messages[0]['content'];
            $user = $messages[1]['content'];
            $reply = fn (array $content) => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]]]);

            if (str_contains($user, 'بررسی انطباق')) {
                $checks[] = $user;

                return $compliance ? $reply($compliance()) : Http::response(['error' => ['message' => 'quota exceeded']], 429);
            }

            if (str_contains($system, 'نقش «هماهنگ‌کننده»')) {
                return $reply([
                    'summary' => 'خلاصهٔ دور',
                    'decisions' => [['title' => 'قیمت را زیر هزینه بیاور', 'detail' => 'برای پر کردن اتاق‌ها'], ['title' => 'با مهمانان گروهی تماس بگیر', 'detail' => '']],
                    'actions' => [['tool' => 'create_task', 'title' => 'کمپین تخفیف شدید', 'reason' => 'پر کردن اتاق‌ها']],
                ]);
            }

            if (str_contains($system, 'نقش «منتقد»')) {
                return $reply(['critiques' => []]);
            }

            return $reply(['findings' => [['claim' => 'اتاق خالی زیاد است', 'source' => 'گزارش', 'confidence' => 'high']]]);
        }]);
    }

    private function verdicts(): callable
    {
        return fn () => [
            'decisions' => [['n' => 1, 'verdict' => 'conflict', 'note' => 'زیر قیمت تمام‌شده است'], ['n' => 2, 'verdict' => 'ok', 'note' => '']],
            'actions' => [['id' => AiMissionAction::firstOrFail()->id, 'verdict' => 'conflict', 'note' => 'تخفیف شدید']],
            'goals' => [['g' => 1, 'covered' => true, 'note' => 'تماس با گروه‌ها'], ['g' => 2, 'covered' => false, 'note' => '']],
        ];
    }

    public function test_the_round_is_weighed_against_the_approved_brief_by_the_critic(): void
    {
        $mission = $this->mission();
        $checks = [];
        $this->fakeTeam($this->verdicts(), $checks);

        $this->assertTrue(app(MissionRunner::class)->runRound($mission, $this->admin)['ok']);

        $this->assertCount(1, $checks);
        $this->assertStringContainsString('قیمت را زیر هزینه بیاور', $checks[0]);
        $this->assertStringContainsString('کمپین تخفیف شدید', $checks[0]);
        $this->assertStringContainsString('G1: افزایش اشغال', $checks[0]);

        $report = $mission->fresh()->report;
        $this->assertSame('conflict', $report['decisions'][0]['compliance']['verdict']);
        $this->assertSame('زیر قیمت تمام‌شده است', $report['decisions'][0]['compliance']['note']);
        $this->assertSame('ok', $report['decisions'][1]['compliance']['verdict']);
        $this->assertSame([['goal' => 'افزایش اشغال', 'covered' => true, 'note' => 'تماس با گروه‌ها'], ['goal' => 'کاهش لغو', 'covered' => false, 'note' => '']], $report['coverage']);
        $this->assertSame('عضو critic', $report['compliance_by']);
        $this->assertSame('conflict', AiMissionAction::sole()->compliance['verdict']);
        $this->assertSame(AiMissionStatus::WaitingApproval, $mission->fresh()->status);
    }

    public function test_without_a_critic_the_coordinator_checks_its_own_round(): void
    {
        $mission = $this->mission(withCritic: false);
        $this->fakeTeam($this->verdicts());

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertSame('عضو coordinator', $mission->fresh()->report['compliance_by']);
    }

    public function test_nothing_is_checked_without_a_current_approved_brief(): void
    {
        $withoutBrief = $this->mission(withBrief: false);
        $checks = [];
        $this->fakeTeam($this->verdicts(), $checks);

        $this->assertTrue(app(MissionRunner::class)->runRound($withoutBrief, $this->admin)['ok']);
        $this->assertSame([], $checks);
        $this->assertArrayNotHasKey('coverage', $withoutBrief->fresh()->report);

        $stale = $this->mission();
        $stale->sources()->first()->update(['content_hash' => sha1('changed')]);
        AiMissionAction::query()->delete();

        app(MissionRunner::class)->runRound($stale->fresh(), $this->admin);
        $this->assertSame([], $checks, 'a brief approved for other files is not used');
    }

    public function test_a_failed_check_never_fails_the_round(): void
    {
        $mission = $this->mission();
        $this->fakeTeam(null);

        $result = app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertTrue($result['ok']);
        $mission->refresh();
        $this->assertSame(AiMissionStatus::WaitingApproval, $mission->status);
        $this->assertStringContainsString('quota exceeded', $mission->report['compliance_error']);
        $this->assertCount(2, $mission->report['decisions']);
        $this->assertArrayNotHasKey('compliance', $mission->report['decisions'][0]);
    }

    public function test_the_alert_says_how_many_items_clash_with_the_red_lines(): void
    {
        Notification::fake();
        $mission = $this->mission();
        $this->fakeTeam($this->verdicts());

        app(MissionRunner::class)->runRound($mission, $this->admin);

        Notification::assertSentTo($this->admin, MissionAttentionNotification::class, fn ($notification) => str_contains($notification->toDatabase($this->admin)['message'], '2 مورد با خط قرمز استراتژی در تضاد'));
    }

    public function test_the_page_flags_conflicts_shows_coverage_and_asks_before_approving_a_clashing_action(): void
    {
        $mission = $this->mission();
        $this->fakeTeam($this->verdicts());
        app(MissionRunner::class)->runRound($mission, $this->admin);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->assertSee('با خط قرمز یا اصل استراتژی در تضاد است')
            ->assertSee('زیر قیمت تمام‌شده است')
            ->assertSee('با استراتژی سازگار است')
            ->assertSee('پوشش اهداف استراتژی')
            ->assertSee('۱ از ۲ هدف در این دور پوشش داده شد')
            ->assertSeeHtml('wire:confirm="این اقدام با خط قرمز استراتژی در تضاد دانسته شده است');

        Livewire::test(AiMissionRoom::class)
            ->assertSee('صف تأیید اقدام‌ها')
            ->assertSee('با خط قرمز یا اصل استراتژی در تضاد است')
            ->assertSeeHtml('wire:confirm="این اقدام با خط قرمز استراتژی در تضاد دانسته شده است');
    }

    public function test_a_clashing_action_can_still_be_approved_because_the_manager_decides(): void
    {
        $mission = $this->mission();
        $this->fakeTeam($this->verdicts());
        app(MissionRunner::class)->runRound($mission, $this->admin);
        $action = AiMissionAction::sole();

        Livewire::test(AiMissionRoom::class)->call('approveAction', $action->id);

        $this->assertSame('executed', $action->fresh()->status->value);
    }

    public function test_garbled_answers_are_cleaned_up(): void
    {
        $parsed = MissionCompliance::parse('متن اضافه {"decisions":[{"n":1,"verdict":"maybe","note":"x"},{"verdict":"ok"}],"actions":[{"id":"7","verdict":"ok"}],"goals":[{"g":1,"covered":"false"},{"g":2}]} پایان');

        $this->assertSame([1 => ['verdict' => 'unclear', 'note' => 'x']], $parsed['decisions']);
        $this->assertSame([7 => ['verdict' => 'ok', 'note' => '']], $parsed['actions']);
        $this->assertSame([1 => ['covered' => false, 'note' => '']], $parsed['goals']);
        $this->assertSame(['decisions' => [], 'actions' => [], 'goals' => []], MissionCompliance::parse('اصلاً JSON نیست'));
    }
}
