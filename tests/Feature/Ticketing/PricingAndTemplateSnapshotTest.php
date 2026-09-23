<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PrintType;
use App\Enums\TicketSourceType;
use App\Enums\TicketTemplateStatus;
use App\Models\AdmissionTicket;
use App\Models\PrintLog;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateElement;
use App\Models\TicketTemplateVersion;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers section 73 items 15-21: neither a price change nor a template
 * edit may ever alter an already-issued ticket, and reprinting never
 * creates a new ticket.
 */
class PricingAndTemplateSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function issue(TicketVariant $variant)
    {
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        return $order->items->first()->tickets->first();
    }

    public function test_a_later_price_change_does_not_alter_an_already_issued_tickets_snapshot(): void
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);

        $ticket = $this->issue($variant);
        $originalUnitPrice = $ticket->orderItem->unit_price;

        // A price "change" is a new price row starting now, after closing
        // the old one - never an update to the old row's own amount.
        $variant->prices()->first()->update(['effective_until' => now()]);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 999999, 'effective_from' => now()]);

        $this->assertSame($originalUnitPrice, $ticket->fresh()->orderItem->unit_price);
        $this->assertSame(100000, $originalUnitPrice);
        $this->assertSame(999999, $variant->currentPrice()->amount);
    }

    public function test_editing_the_template_does_not_alter_an_already_issued_tickets_snapshot(): void
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);

        $template = TicketTemplate::factory()->create();
        $versionOne = TicketTemplateVersion::factory()->create([
            'ticket_template_id' => $template->id,
            'status' => TicketTemplateStatus::Active,
        ]);
        TicketTemplateElement::factory()->create(['ticket_template_version_id' => $versionOne->id, 'content' => 'نسخه‌ی قدیم']);
        $variant->update(['ticket_template_id' => $template->id]);

        $ticket = $this->issue($variant);
        $this->assertSame($versionOne->id, $ticket->ticket_template_version_id);

        // Publishing a new version must never mutate the old one, and the
        // already-issued ticket must keep pointing at the old version.
        $versionTwo = TicketTemplateVersion::factory()->create(['ticket_template_id' => $template->id, 'status' => TicketTemplateStatus::Draft]);
        TicketTemplateElement::factory()->create(['ticket_template_version_id' => $versionTwo->id, 'content' => 'نسخه‌ی جدید']);
        $versionTwo->publish();

        $this->assertSame($versionOne->id, $ticket->fresh()->ticket_template_version_id);
        $this->assertSame(TicketTemplateStatus::Archived, $versionOne->fresh()->status);
        $this->assertSame(TicketTemplateStatus::Active, $versionTwo->fresh()->status);

        // A brand new ticket issued after publishing must use the new version.
        $secondTicket = $this->issue($variant->fresh());
        $this->assertSame($versionTwo->id, $secondTicket->ticket_template_version_id);
    }

    public function test_only_one_template_version_is_ever_active_at_a_time(): void
    {
        $template = TicketTemplate::factory()->create();
        $versionOne = TicketTemplateVersion::factory()->create(['ticket_template_id' => $template->id, 'status' => TicketTemplateStatus::Active]);
        $versionTwo = TicketTemplateVersion::factory()->create(['ticket_template_id' => $template->id, 'status' => TicketTemplateStatus::Draft]);

        $versionTwo->publish();

        $this->assertSame(TicketTemplateStatus::Archived, $versionOne->fresh()->status);
        $this->assertSame($versionTwo->id, $template->fresh()->activeVersion()->id);
    }

    public function test_viewing_the_print_page_the_first_time_logs_an_original_print(): void
    {
        $admin = User::factory()->create();
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $ticket = $this->issue($variant);

        $this->actingAs($admin)->get(route('admission-tickets.print', $ticket))->assertSuccessful();

        $this->assertSame(1, PrintLog::count());
        $this->assertSame(PrintType::Original, PrintLog::first()->print_type);
    }

    public function test_reprinting_never_creates_a_new_ticket_and_logs_it_as_a_reprint(): void
    {
        $admin = User::factory()->create();
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $ticket = $this->issue($variant);
        $ticketNumber = $ticket->ticket_number;

        $this->actingAs($admin)->get(route('admission-tickets.print', $ticket))->assertSuccessful();
        $this->actingAs($admin)->get(route('admission-tickets.print', $ticket))->assertSuccessful();

        $this->assertSame(1, AdmissionTicket::where('ticket_number', $ticketNumber)->count());
        $this->assertSame(2, PrintLog::count());
        $this->assertSame(PrintType::Reprint, PrintLog::latest('id')->first()->print_type);
    }
}
