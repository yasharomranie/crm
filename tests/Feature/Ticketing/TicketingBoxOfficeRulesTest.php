<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\Guest;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TicketingBoxOfficeRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function variant(array $attributes = [], int $amount = 100_000): TicketVariant
    {
        $variant = TicketVariant::factory()->create($attributes);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    private function sell(TicketVariant $variant, int $quantity = 1, array $extra = [])
    {
        return app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $quantity]],
            ...$extra,
        ]);
    }

    public function test_orders_are_tagged_with_the_configured_currency_toman_by_default(): void
    {
        $this->assertSame('IRT', $this->sell($this->variant())->currency);
    }

    public function test_an_end_of_day_ticket_lasts_until_midnight_of_its_last_day(): void
    {
        Carbon::setTestNow('2026-11-04 15:00:00');
        $variant = $this->variant(['validity_end_of_day' => true, 'default_validity_days' => 1]);

        $ticket = $this->sell($variant)->items->first()->tickets->first();

        $this->assertSame('2026-11-04 23:59:59', $ticket->valid_until->format('Y-m-d H:i:s'));
    }

    public function test_a_multi_day_end_of_day_ticket_covers_whole_days(): void
    {
        Carbon::setTestNow('2026-11-04 15:00:00');
        $variant = $this->variant(['validity_end_of_day' => true, 'default_validity_days' => 3]);

        $this->assertSame('2026-11-06 23:59:59', $this->sell($variant)->items->first()->tickets->first()->valid_until->format('Y-m-d H:i:s'));
    }

    public function test_a_plain_ticket_still_lasts_exactly_n_times_24_hours(): void
    {
        Carbon::setTestNow('2026-11-04 15:00:00');
        $variant = $this->variant(['validity_end_of_day' => false, 'default_validity_days' => 1]);

        $this->assertSame('2026-11-05 15:00:00', $this->sell($variant)->items->first()->tickets->first()->valid_until->format('Y-m-d H:i:s'));
    }

    public function test_the_daily_capacity_stops_sales_when_full_and_a_refund_frees_a_place(): void
    {
        $variant = $this->variant(['daily_capacity' => 3]);
        $this->sell($variant, 2);

        $this->assertSame(1, $variant->remainingToday());

        try {
            $this->sell($variant, 2);
            $this->fail('Expected the sale to exceed the capacity.');
        } catch (TicketingException $exception) {
            $this->assertStringContainsString('باقی‌مانده: 1', $exception->getMessage());
        }

        AdmissionTicket::first()->update(['status' => AdmissionTicketStatus::Refunded]);

        $this->assertSame(2, $variant->remainingToday());
        $this->sell($variant, 2);
        $this->assertSame(0, $variant->remainingToday());
    }

    public function test_yesterdays_sales_do_not_count_against_todays_capacity(): void
    {
        $variant = $this->variant(['daily_capacity' => 1]);
        $this->sell($variant);
        AdmissionTicket::query()->update(['created_at' => now()->subDay()]);

        $this->sell($variant);

        $this->assertSame(2, AdmissionTicket::count());
    }

    public function test_capacity_counts_several_lines_of_the_same_type_together(): void
    {
        $variant = $this->variant(['daily_capacity' => 3]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [
                ['ticket_variant_id' => $variant->id, 'quantity' => 2],
                ['ticket_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ]);
    }

    public function test_a_type_that_needs_buyer_details_refuses_an_anonymous_sale(): void
    {
        $variant = $this->variant(['requires_customer_info' => true]);

        $this->expectException(TicketingException::class);

        $this->sell($variant);
    }

    public function test_weekend_prices_apply_only_on_thursday_and_friday(): void
    {
        $variant = $this->variant([], 100_000);
        TicketVariantPrice::factory()->create([
            'ticket_variant_id' => $variant->id, 'amount' => 150_000, 'applies_to' => 'weekend',
            'effective_from' => now()->subYear(),
        ]);

        Carbon::setTestNow('2026-11-05 12:00:00'); // Thursday
        $this->assertSame(150_000, (int) $variant->currentPrice()->amount);

        Carbon::setTestNow('2026-11-06 12:00:00'); // Friday
        $this->assertSame(150_000, (int) $variant->currentPrice()->amount);

        Carbon::setTestNow('2026-11-07 12:00:00'); // Saturday
        $this->assertSame(100_000, (int) $variant->currentPrice()->amount);
    }

    public function test_a_weekday_only_price_is_ignored_on_the_weekend(): void
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 80_000, 'applies_to' => 'weekday', 'effective_from' => now()->subYear()]);

        Carbon::setTestNow('2026-11-06 12:00:00'); // Friday
        $this->assertNull($variant->currentPrice());

        Carbon::setTestNow('2026-11-07 12:00:00');
        $this->assertSame(80_000, (int) $variant->currentPrice()->amount);
    }

    public function test_a_sale_with_a_phone_number_lands_on_the_customers_timeline(): void
    {
        $order = $this->sell($this->variant(), 1, ['customer_phone' => '+989121234567', 'customer_name' => 'علی']);

        $guest = Guest::where('mobile', '09121234567')->sole();
        $this->assertSame('علی', $guest->name);
        $activity = Activity::where('event', 'ticket_purchased')->sole();
        $this->assertSame($guest->id, $activity->subject_id);
        $this->assertSame($order->id, $activity->properties['order_id']);
    }

    public function test_an_existing_guest_is_reused_and_anonymous_or_free_sales_are_not_linked(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09121234567']);
        $this->sell($this->variant(), 1, ['customer_phone' => '09121234567']);
        $this->sell($this->variant());

        $this->assertSame(1, Guest::count());
        $this->assertSame(1, Activity::where('event', 'ticket_purchased')->where('subject_id', $guest->id)->count());
        $this->assertSame(1, Activity::where('event', 'ticket_purchased')->count());
    }

    public function test_price_and_catalog_changes_are_written_to_the_activity_log(): void
    {
        $variant = $this->variant();
        $variant->update(['name' => 'نام جدید']);

        $this->assertTrue(Activity::where('log_name', 'ticket_variant')->exists());
        $this->assertTrue(Activity::where('log_name', 'ticket_variant_price')->exists());
    }
}
