<?php

namespace Tests\Feature\Crm;

use App\Enums\DealStage;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Resources\DealAutomationRules\Pages\ManageDealAutomationRules;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_a_deal_created_in_a_rules_stage_gets_the_follow_up(): void
    {
        $rep = User::factory()->create();
        DealAutomationRule::factory()->create([
            'trigger_stage' => DealStage::NewLead,
            'task_title' => 'تماس برای {deal}',
            'due_after_hours' => 2,
        ]);

        $deal = Deal::create(['title' => 'تور شمال', 'stage' => DealStage::NewLead, 'assigned_to' => $rep->id]);

        $task = $deal->followUpTasks()->firstOrFail();

        $this->assertSame('تماس برای تور شمال', $task->title);
        $this->assertSame(TaskType::CrmFollowUp, $task->task_type);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame($rep->id, $task->assigned_to);
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $task->due_at->timestamp, 5);
    }

    public function test_moving_a_deal_into_a_rules_stage_triggers_it(): void
    {
        DealAutomationRule::factory()->create(['trigger_stage' => DealStage::Negotiation]);
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead]);

        $this->assertSame(0, $deal->followUpTasks()->count());

        $deal->update(['stage' => DealStage::Negotiation]);

        $this->assertSame(1, $deal->followUpTasks()->count());
    }

    public function test_a_rules_own_assignee_wins_over_the_deals(): void
    {
        $rep = User::factory()->create();
        $specialist = User::factory()->create();
        DealAutomationRule::factory()->create(['trigger_stage' => DealStage::NewLead, 'assigned_to' => $specialist->id]);

        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead, 'assigned_to' => $rep->id]);

        $this->assertSame($specialist->id, $deal->followUpTasks()->firstOrFail()->assigned_to);
    }

    public function test_inactive_rules_and_rules_for_other_stages_do_nothing(): void
    {
        DealAutomationRule::factory()->create(['trigger_stage' => DealStage::NewLead, 'is_active' => false]);
        DealAutomationRule::factory()->create(['trigger_stage' => DealStage::Won]);

        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead]);

        $this->assertSame(0, Task::count());
        $this->assertSame(0, $deal->followUpTasks()->count());
    }

    public function test_editing_other_fields_or_bouncing_between_stages_does_not_stack_duplicates(): void
    {
        DealAutomationRule::factory()->create(['trigger_stage' => DealStage::NewLead]);
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead]);

        $deal->update(['notes' => 'یادداشت']);
        $deal->update(['stage' => DealStage::ProposalSent]);
        $deal->update(['stage' => DealStage::NewLead]);

        $this->assertSame(1, $deal->followUpTasks()->count());
    }

    public function test_admins_can_manage_rules_from_the_panel(): void
    {
        $rule = DealAutomationRule::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageDealAutomationRules::class)
            ->assertCanSeeTableRecords([$rule])
            ->mountAction('create')
            ->setActionData([
                'name' => 'قانون تازه',
                'trigger_stage' => DealStage::ProposalSent->value,
                'task_title' => 'پیگیری پیش‌فاکتور {deal}',
                'due_after_hours' => 48,
                'is_active' => true,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('deal_automation_rules', ['name' => 'قانون تازه', 'due_after_hours' => 48]);
    }
}
