<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\FollowUpReminderService;
use App\Enums\DealStage;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Tasks\RelationManagers\FollowUpTasksRelationManager;
use App\Filament\Widgets\MyFollowUpsWidget;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\Task;
use App\Models\User;
use App\Notifications\FollowUpDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FollowUpTasksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function followUp(Guest|Deal $subject, User $assignee, array $overrides = []): Task
    {
        return Task::factory()->create([
            'task_type' => TaskType::CrmFollowUp,
            'source_type' => $subject::class,
            'source_id' => $subject->id,
            'assigned_to' => $assignee->id,
            'due_at' => now()->subHour(),
            ...$overrides,
        ]);
    }

    public function test_a_guest_or_deal_only_lists_its_own_crm_follow_ups(): void
    {
        $user = User::factory()->create();
        $guest = Guest::factory()->create();
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead]);

        $guestTask = $this->followUp($guest, $user);
        $dealTask = $this->followUp($deal, $user);
        // Same guest, but an unrelated (non-CRM) task type.
        Task::factory()->create(['source_type' => Guest::class, 'source_id' => $guest->id]);

        $this->assertSame([$guestTask->id], $guest->followUpTasks()->pluck('id')->all());
        $this->assertSame([$dealTask->id], $deal->followUpTasks()->pluck('id')->all());
    }

    public function test_a_follow_up_can_be_scheduled_from_the_guest_page(): void
    {
        $admin = $this->admin();
        $assignee = User::factory()->create();
        $guest = Guest::factory()->create();

        Livewire::actingAs($admin)
            ->test(FollowUpTasksRelationManager::class, [
                'ownerRecord' => $guest,
                'pageClass' => ViewGuest::class,
            ])
            ->mountTableAction('create')
            ->setTableActionData([
                'title' => 'تماس برای قیمت نهایی',
                'due_at' => now()->addDay()->toDateTimeString(),
                'assigned_to' => $assignee->id,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $task = $guest->followUpTasks()->firstOrFail();

        $this->assertSame('تماس برای قیمت نهایی', $task->title);
        $this->assertSame(TaskType::CrmFollowUp, $task->task_type);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertSame($admin->id, $task->assigned_by);
        $this->assertSame(TaskStatus::Todo, $task->status);
    }

    public function test_marking_a_follow_up_done_records_when(): void
    {
        $admin = $this->admin();
        $guest = Guest::factory()->create();
        $task = $this->followUp($guest, $admin);

        Livewire::actingAs($admin)
            ->test(FollowUpTasksRelationManager::class, [
                'ownerRecord' => $guest,
                'pageClass' => ViewGuest::class,
            ])
            ->callTableAction('markDone', $task);

        $task->refresh();

        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_the_dashboard_widget_shows_only_my_due_open_follow_ups(): void
    {
        $me = $this->admin();
        $other = User::factory()->create();
        $guest = Guest::factory()->create();

        $dueMine = $this->followUp($guest, $me);
        $laterMine = $this->followUp($guest, $me, ['due_at' => now()->addDays(3)]);
        $doneMine = $this->followUp($guest, $me, ['status' => TaskStatus::Done]);
        $dueTheirs = $this->followUp($guest, $other);

        Livewire::actingAs($me)
            ->test(MyFollowUpsWidget::class)
            ->assertCanSeeTableRecords([$dueMine])
            ->assertCanNotSeeTableRecords([$laterMine, $doneMine, $dueTheirs]);
    }

    public function test_due_follow_ups_are_notified_once_and_only_when_due_and_open(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $guest = Guest::factory()->create();

        $due = $this->followUp($guest, $user);
        $future = $this->followUp($guest, $user, ['due_at' => now()->addDay()]);
        $done = $this->followUp($guest, $user, ['status' => TaskStatus::Done]);

        $service = app(FollowUpReminderService::class);

        $this->assertSame(1, $service->sendDueReminders());
        Notification::assertSentToTimes($user, FollowUpDueNotification::class, 1);
        $this->assertNotNull($due->fresh()->reminder_sent_at);
        $this->assertNull($future->fresh()->reminder_sent_at);
        $this->assertNull($done->fresh()->reminder_sent_at);

        // A second sweep must not notify the same follow-up again.
        $this->assertSame(0, $service->sendDueReminders());
        Notification::assertSentToTimes($user, FollowUpDueNotification::class, 1);
    }
}
