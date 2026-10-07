<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Guest;
use App\Models\User;
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

    public function test_a_guest_cannot_view_or_reply_to_a_ticket_without_logging_in(): void
    {
        $owner = Guest::factory()->create();
        $ticket = $owner->tickets()->create(['subject' => 'موضوع', 'priority' => TicketPriority::Normal]);

        $this->get(route('customer-app.support.show', $ticket))
            ->assertRedirect(route('customer-app.auth.login'));

        $this->post(route('customer-app.support.reply', $ticket), ['body' => 'پیام'])
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

    public function test_a_guest_cannot_view_a_ticket_that_isnt_theirs(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();
        $ticket = $otherGuest->tickets()->create(['subject' => 'تیکت دیگری', 'priority' => TicketPriority::Normal]);

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app.support.show', $ticket))
            ->assertNotFound();
    }

    public function test_a_guest_can_view_their_own_ticket_with_its_replies(): void
    {
        $guest = Guest::factory()->create(['name' => 'رضا محمدی']);
        $staff = User::factory()->create(['name' => 'پشتیبان یک']);
        $ticket = $guest->tickets()->create([
            'subject' => 'مشکل در ورود به اتاق',
            'description' => 'کلید اتاق کار نمی‌کند.',
            'priority' => TicketPriority::Normal,
        ]);
        $ticket->replies()->create(['guest_id' => $guest->id, 'body' => 'لطفاً بررسی کنید.']);
        $ticket->replies()->create(['user_id' => $staff->id, 'body' => 'در حال بررسی هستیم.']);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.support.show', $ticket));

        $response->assertOk();
        $response->assertSee('لطفاً بررسی کنید.');
        $response->assertSee('در حال بررسی هستیم.');
        $response->assertSee('رضا محمدی');
        $response->assertSee('پشتیبان یک');
    }

    public function test_a_guest_can_reply_to_their_own_ticket(): void
    {
        $guest = Guest::factory()->create();
        $ticket = $guest->tickets()->create(['subject' => 'موضوع', 'priority' => TicketPriority::Normal]);

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.reply', $ticket), ['body' => 'یک پیام جدید']);

        $response->assertRedirect(route('customer-app.support.show', $ticket));

        $reply = $ticket->replies()->first();
        $this->assertNotNull($reply);
        $this->assertSame('یک پیام جدید', $reply->body);
        $this->assertSame($guest->id, $reply->guest_id);
        $this->assertTrue($reply->isFromGuest());
    }

    public function test_a_guest_cannot_reply_to_a_ticket_that_isnt_theirs(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();
        $ticket = $otherGuest->tickets()->create(['subject' => 'تیکت دیگری', 'priority' => TicketPriority::Normal]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.reply', $ticket), ['body' => 'تلاش برای نفوذ'])
            ->assertNotFound();

        $this->assertSame(0, $ticket->replies()->count());
    }

    public function test_replying_reopens_a_resolved_ticket(): void
    {
        $guest = Guest::factory()->create();
        $ticket = $guest->tickets()->create([
            'subject' => 'موضوع',
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Resolved,
        ]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.support.reply', $ticket), ['body' => 'هنوز حل نشده']);

        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
    }
}
