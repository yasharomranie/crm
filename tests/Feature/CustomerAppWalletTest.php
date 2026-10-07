<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_the_wallet_page_without_logging_in(): void
    {
        $this->get(route('customer-app.wallet'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_the_wallet_page_shows_the_guests_balance(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 150000]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'));

        $response->assertOk();
        $response->assertSee(number_format(150000));
    }

    public function test_the_wallet_page_lists_the_guests_own_transactions(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();

        $guest->creditWallet(50000, 'کش‌بک رزرو آزمایشی');
        $otherGuest->creditWallet(99999, 'تراکنش مشتری دیگر');

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'));

        $response->assertSee('کش‌بک رزرو آزمایشی');
        $response->assertDontSee('تراکنش مشتری دیگر');
    }

    public function test_a_guest_with_no_completed_stays_is_bronze_tier(): void
    {
        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'));

        $response->assertSee('برنزی');
    }

    public function test_a_guest_with_completed_stays_sees_a_higher_tier(): void
    {
        $guest = Guest::factory()->create();

        Booking::factory()->count(3)->create([
            'guest_id' => $guest->id,
            'status' => BookingStatus::CheckedOut,
            'actual_check_out_at' => now(),
        ]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'));

        $response->assertSee('طلایی');
    }
}
