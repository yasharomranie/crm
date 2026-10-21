<?php

namespace Tests\Feature;

use App\Domain\Ai\AiTeamMapData;
use App\Enums\AiMessageRole;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiTeamMap;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiTeamMapTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMapViewer(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'ناظر نقشهٔ تیم']);
        $role->givePermissionTo(Permission::where('name', 'ai-team-map.access')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_map_lists_the_manager_and_visible_assistants_with_their_exchanges(): void
    {
        $user = $this->actingAsMapViewer();
        $legal = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $finance = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        AiAssistantConsultation::factory()->count(2)->create([
            'consulting_ai_assistant_profile_id' => $legal->id,
            'consulted_ai_assistant_profile_id' => $finance->id,
        ]);

        $map = app(AiTeamMapData::class)->build($user);

        $this->assertSame([AiTeamMapData::MANAGER_ID, (string) $legal->id, (string) $finance->id], collect($map['nodes'])->pluck('id')->all());

        $edge = collect($map['edges'])->sole();
        $this->assertSame([(string) $legal->id, (string) $finance->id, 'consult', 2, true], [$edge['source'], $edge['target'], $edge['kind'], $edge['count'], $edge['live']]);
        $this->assertSame(2, collect($map['nodes'])->firstWhere('id', (string) $finance->id)['consulted']);
        $this->assertSame(2, $map['totals']['consultations']);
        $this->assertCount(2, $map['feed']);
    }

    public function test_someone_elses_private_assistant_and_its_exchanges_are_left_out(): void
    {
        $user = $this->actingAsMapViewer();
        $mine = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $private = AiAssistantProfile::factory()->create(['created_by' => User::factory()->create()->id, 'is_shared' => false]);
        AiAssistantConsultation::factory()->create([
            'consulting_ai_assistant_profile_id' => $mine->id,
            'consulted_ai_assistant_profile_id' => $private->id,
        ]);

        $map = app(AiTeamMapData::class)->build($user);

        $this->assertNotContains((string) $private->id, collect($map['nodes'])->pluck('id')->all());
        $this->assertSame([], $map['edges']);
    }

    public function test_an_old_exchange_no_longer_glows(): void
    {
        $user = $this->actingAsMapViewer();
        $a = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $b = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        AiAssistantConsultation::factory()->create([
            'consulting_ai_assistant_profile_id' => $a->id,
            'consulted_ai_assistant_profile_id' => $b->id,
            'created_at' => now()->subHour(),
        ]);

        $map = app(AiTeamMapData::class)->build($user);

        $this->assertFalse(collect($map['edges'])->sole()['live']);
        $this->assertSame(0, $map['totals']['live']);
    }

    public function test_a_handoff_is_drawn_from_the_assistant_that_handed_over(): void
    {
        $user = $this->actingAsMapViewer();
        $first = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $second = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $first->id]);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => null, 'ai_assistant_profile_id' => $first->id, 'tool_calls' => [['id' => 'c1']]]);
        $conversation->messages()->create(['role' => AiMessageRole::Tool, 'tool_name' => 'handoff_to_assistant', 'tool_call_id' => 'c1', 'content' => json_encode(['handed_off_to' => $second->displayName(), 'handed_off_profile_id' => $second->id, 'summary' => 'x'])]);

        $edge = collect(app(AiTeamMapData::class)->build($user)['edges'])->sole();

        $this->assertSame([(string) $first->id, (string) $second->id, 'handoff'], [$edge['source'], $edge['target'], $edge['kind']]);
    }

    public function test_the_management_assistant_asking_a_specialist_is_drawn_from_the_manager(): void
    {
        $user = $this->actingAsMapViewer();
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        activity('ai_assistant')
            ->causedBy($user)
            ->withProperties(['tool' => 'consult_custom_assistant', 'arguments' => ['assistant_id' => $specialist->id, 'question' => 'سؤال']])
            ->event('tool_called')
            ->log('ابزار دستیار هوشمند: consult_custom_assistant');

        $this->assertSame(1, Activity::count());

        $edge = collect(app(AiTeamMapData::class)->build($user)['edges'])->sole();

        $this->assertSame([AiTeamMapData::MANAGER_ID, (string) $specialist->id], [$edge['source'], $edge['target']]);
    }

    public function test_the_page_renders_for_a_permitted_user_and_exposes_the_map(): void
    {
        $user = $this->actingAsMapViewer();
        AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'دستیار حقوقی نمونه']);

        $component = Livewire::test(AiTeamMap::class)->assertSee('نقشهٔ تیم دستیاران');

        $this->assertCount(2, $component->instance()->mapData()['nodes']);
    }

    public function test_a_user_without_the_permission_is_denied(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی نقشهٔ تیم']));

        $this->actingAs($user)->get(route('filament.admin.pages.ai-team-map'))->assertForbidden();
    }

    public function test_each_assistants_last_reply_to_the_user_is_included_but_never_someone_elses(): void
    {
        $user = $this->actingAsMapViewer();
        $other = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'is_shared' => true]);

        $mine = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $mine->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ **اول** من']);
        $mine->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'آخرین پاسخ به من']);

        $theirs = AiConversation::create(['user_id' => $other->id, 'ai_assistant_profile_id' => $profile->id]);
        $theirs->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'راز یک کاربر دیگر']);

        $manager = AiConversation::create(['user_id' => $user->id]);
        $manager->messages()->create(['role' => AiMessageRole::Assistant, 'content' => '| جدول | عدد |']);

        $nodes = collect(app(AiTeamMapData::class)->build($user)['nodes'])->keyBy('id');

        $this->assertSame('آخرین پاسخ به من', $nodes[(string) $profile->id]['reply']['text']);
        $this->assertStringNotContainsString('راز', json_encode($nodes[(string) $profile->id]['reply'], JSON_UNESCAPED_UNICODE));
        $this->assertSame('جدول عدد', $nodes[AiTeamMapData::MANAGER_ID]['reply']['text']);
    }

    public function test_an_assistant_with_no_reply_has_no_bubble(): void
    {
        $user = $this->actingAsMapViewer();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $node = collect(app(AiTeamMapData::class)->build($user)['nodes'])->firstWhere('id', (string) $profile->id);

        $this->assertNull($node['reply']);
    }

    public function test_a_missions_map_shows_only_its_team_with_roles_and_the_flow_of_findings(): void
    {
        $user = $this->actingAsMapViewer();
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $critic = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $outsider = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $mission = AiMission::factory()->create(['created_by' => $user->id, 'status' => AiMissionStatus::Running, 'current_profile_id' => $specialist->id, 'current_step' => '«متخصص» در حال بررسی…']);
        foreach ([[$coordinator, AiMissionRoleType::Coordinator], [$specialist, AiMissionRoleType::Specialist], [$critic, AiMissionRoleType::Critic]] as [$profile, $role]) {
            AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $profile->id, 'role' => $role]);
        }
        $finding = AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'claim' => 'سه‌شنبه پر است.']);
        AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $critic->id, 'role' => AiMissionRoleType::Critic, 'kind' => AiMissionCardKind::Critique, 'target_card_id' => $finding->id, 'claim' => 'منبع روشن نیست.']);

        $map = app(AiTeamMapData::class)->build($user, $mission->id);
        $nodes = collect($map['nodes'])->keyBy('id');

        $this->assertSame($mission->id, $map['mission']['id']);
        $this->assertEqualsCanonicalizing([(string) $coordinator->id, (string) $specialist->id, (string) $critic->id], $nodes->keys()->all());
        $this->assertNotContains((string) $outsider->id, $nodes->keys()->all());
        $this->assertTrue($nodes[(string) $coordinator->id]['hub']);
        $this->assertSame('منتقد', $nodes[(string) $critic->id]['role_label']);
        $this->assertTrue($nodes[(string) $specialist->id]['live']);
        $this->assertFalse($nodes[(string) $critic->id]['live'], 'only the member working right now is live');
        $this->assertSame('«متخصص» در حال بررسی…', $nodes[(string) $specialist->id]['reply']['text']);

        $edges = collect($map['edges'])->keyBy('kind');
        $this->assertSame([(string) $specialist->id, (string) $critic->id], [$edges['report']['source'], $edges['report']['target']]);
        $this->assertSame([(string) $critic->id, (string) $coordinator->id], [$edges['review']['source'], $edges['review']['target']]);
        $this->assertSame([3, 1, 1], [$map['totals']['assistants'], $map['totals']['consultations'], $map['totals']['handoffs']]);
        $this->assertCount(2, $map['feed']);
    }

    public function test_without_a_critic_findings_flow_straight_to_the_coordinator(): void
    {
        $user = $this->actingAsMapViewer();
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $mission = AiMission::factory()->create(['created_by' => $user->id]);
        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $coordinator->id, 'role' => AiMissionRoleType::Coordinator]);
        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'role' => AiMissionRoleType::Specialist]);

        $edge = collect(app(AiTeamMapData::class)->build($user, $mission->id)['edges'])->sole();

        $this->assertSame([(string) $specialist->id, (string) $coordinator->id, 'report', false], [$edge['source'], $edge['target'], $edge['kind'], $edge['live']]);
    }

    public function test_someone_elses_mission_falls_back_to_the_normal_map(): void
    {
        $user = $this->actingAsMapViewer();
        $foreign = AiMission::factory()->create();

        $map = app(AiTeamMapData::class)->build($user, $foreign->id);

        $this->assertNull($map['mission']);
        $this->assertSame([], $map['missions']);
    }

    public function test_the_page_switches_the_map_to_a_chosen_mission(): void
    {
        $user = $this->actingAsMapViewer();
        $mission = AiMission::factory()->create(['created_by' => $user->id, 'title' => 'مأموریت نقشه']);

        $component = Livewire::test(AiTeamMap::class)->assertSet('missionId', null);
        $this->assertNull($component->instance()->mapData()['mission']);
        $this->assertSame('مأموریت نقشه', $component->instance()->mapData()['missions'][0]['title']);

        $component->call('selectMission', $mission->id)->assertSet('missionId', $mission->id);
        $this->assertSame($mission->id, $component->instance()->mapData()['mission']['id']);
    }
}
