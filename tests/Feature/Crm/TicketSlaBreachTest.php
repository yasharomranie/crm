<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\TicketSlaBreachService;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\User;
use App\Notifications\TicketSlaBreachedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketSlaBreachTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(array $overrides = []): GuestTicket
    {
        return GuestTicket::create([
            'guest_id' => Guest::factory()->create()->id,
            'subject' => 'مشکل در رزرو',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
            'sla_due_at' => now()->subHour(),
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_a_breached_ticket_alerts_its_assignee_once(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $admin = $this->admin();
        $ticket = $this->ticket(['assigned_to' => $assignee->id]);

        $this->assertSame(1, app(TicketSlaBreachService::class)->notifyBreachedTickets());

        Notification::assertSentToTimes($assignee, TicketSlaBreachedNotification::class, 1);
        Notification::assertNotSentTo($admin, TicketSlaBreachedNotification::class);
        $this->assertNotNull($ticket->fresh()->sla_breach_notified_at);

        $this->assertSame(0, app(TicketSlaBreachService::class)->notifyBreachedTickets());
        Notification::assertSentToTimes($assignee, TicketSlaBreachedNotification::class, 1);
    }

    public function test_an_unassigned_breached_ticket_alerts_every_admin(): void
    {
        Notification::fake();

        $adminOne = $this->admin();
        $adminTwo = $this->admin();
        $bystander = User::factory()->create();
        $this->ticket();

        app(TicketSlaBreachService::class)->notifyBreachedTickets();

        Notification::assertSentTo($adminOne, TicketSlaBreachedNotification::class);
        Notification::assertSentTo($adminTwo, TicketSlaBreachedNotification::class);
        Notification::assertNotSentTo($bystander, TicketSlaBreachedNotification::class);
    }

    public function test_tickets_within_their_sla_or_already_resolved_are_not_alerted(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $this->ticket(['sla_due_at' => now()->addHour()]);
        $this->ticket(['status' => TicketStatus::Resolved]);
        $this->ticket(['status' => TicketStatus::Closed]);

        $this->assertSame(0, app(TicketSlaBreachService::class)->notifyBreachedTickets());

        Notification::assertNothingSentTo($admin);
    }

    public function test_an_in_progress_ticket_past_its_deadline_is_still_alerted(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $this->ticket(['status' => TicketStatus::InProgress, 'assigned_to' => $assignee->id]);

        $this->assertSame(1, app(TicketSlaBreachService::class)->notifyBreachedTickets());
        Notification::assertSentTo($assignee, TicketSlaBreachedNotification::class);
    }
}
