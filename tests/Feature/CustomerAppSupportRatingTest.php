<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\GuestTickets\Pages\ListGuestTickets;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAppSupportRatingTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(Guest $guest, TicketStatus $status): GuestTicket
    {
        return GuestTicket::create([
            'guest_id' => $guest->id, 'subject' => 'مشکل رزرو', 'status' => $status, 'priority' => TicketPriority::Normal,
        ]);
    }

    public function test_a_resolved_ticket_can_be_rated_with_a_comment(): void
    {
        $guest = Guest::factory()->create();
        $ticket = $this->ticket($guest, TicketStatus::Resolved);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.rate', $ticket), ['csat_score' => 4, 'csat_comment' => 'سریع بود'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(4, $ticket->csat_score);
        $this->assertSame('سریع بود', $ticket->csat_comment);
        $this->assertNotNull($ticket->csat_responded_at);
    }

    public function test_an_open_ticket_cannot_be_rated_yet(): void
    {
        $guest = Guest::factory()->create();
        $ticket = $this->ticket($guest, TicketStatus::Open);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.rate', $ticket), ['csat_score' => 5])
            ->assertSessionHasErrors('csat_score');

        $this->assertNull($ticket->fresh()->csat_score);
    }

    public function test_scores_outside_one_to_five_are_rejected(): void
    {
        $guest = Guest::factory()->create();
        $ticket = $this->ticket($guest, TicketStatus::Closed);

        $this->actingAs($guest, 'customer')->post(route('customer-app.support.rate', $ticket), ['csat_score' => 6])->assertSessionHasErrors('csat_score');
        $this->actingAs($guest, 'customer')->post(route('customer-app.support.rate', $ticket), ['csat_score' => 0])->assertSessionHasErrors('csat_score');
    }

    public function test_another_guests_ticket_cannot_be_rated(): void
    {
        $owner = Guest::factory()->create();
        $other = Guest::factory()->create();
        $ticket = $this->ticket($owner, TicketStatus::Resolved);

        $this->actingAs($other, 'customer')
            ->post(route('customer-app.support.rate', $ticket), ['csat_score' => 1])
            ->assertNotFound();
    }

    public function test_the_rating_form_only_appears_once_the_ticket_is_over(): void
    {
        $guest = Guest::factory()->create();
        $open = $this->ticket($guest, TicketStatus::Open);
        $resolved = $this->ticket($guest, TicketStatus::Resolved);

        $this->actingAs($guest, 'customer')->get(route('customer-app.support.show', $open))->assertDontSee('ثبت امتیاز');
        $this->actingAs($guest, 'customer')->get(route('customer-app.support.show', $resolved))->assertSee('ثبت امتیاز');
    }

    public function test_staff_can_filter_unhappy_customers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $guest = Guest::factory()->create();
        $unhappy = $this->ticket($guest, TicketStatus::Resolved);
        $unhappy->update(['csat_score' => 1]);
        $happy = $this->ticket($guest, TicketStatus::Resolved);
        $happy->update(['csat_score' => 5]);

        Livewire::actingAs($admin)->test(ListGuestTickets::class)
            ->filterTable('unhappy')
            ->assertCanSeeTableRecords([$unhappy])
            ->assertCanNotSeeTableRecords([$happy]);
    }
}
