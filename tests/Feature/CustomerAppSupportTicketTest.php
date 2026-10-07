<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_support_without_logging_in(): void
    {
        $this->get(route('customer-app.support'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_a_guest_can_create_a_support_ticket(): void
    {
        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.store'), [
                'subject' => 'مشکل در ورود به اتاق',
                'description' => 'کلید اتاق کار نمی‌کند.',
            ]);

        $response->assertRedirect(route('customer-app.support'));

        $ticket = $guest->tickets()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('مشکل در ورود به اتاق', $ticket->subject);
        $this->assertSame(TicketStatus::Open, $ticket->status);
    }

    public function test_a_guest_only_sees_their_own_support_tickets(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();

        $guest->tickets()->create(['subject' => 'تیکت خودم', 'priority' => TicketPriority::Normal]);
        $otherGuest->tickets()->create(['subject' => 'تیکت دیگری', 'priority' => TicketPriority::Normal]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.support'));

        $response->assertSee('تیکت خودم');
        $response->assertDontSee('تیکت دیگری');
    }
}
