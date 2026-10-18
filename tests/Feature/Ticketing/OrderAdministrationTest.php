<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\GateValidationService;
use App\Domain\Ticketing\TicketAdministration;
use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\GateScanResult;
use App\Enums\TicketSourceType;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\TicketsRelationManager;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Order;
use App\Models\Refund;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function order(int $quantity = 2, array $extra = []): Order
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100_000]);

        return app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $quantity]],
            ...$extra,
        ]);
    }

    public function test_an_order_is_found_by_its_number_buyer_phone_or_a_ticket_number(): void
    {
        $target = $this->order(1, ['customer_phone' => '09121110000', 'customer_name' => 'پیدا‌شونده']);
        $other = $this->order(1, ['customer_phone' => '09129990000']);
        $ticketNumber = $target->items->first()->tickets->first()->ticket_number;

        foreach ([$target->order_number, '09121110000', $ticketNumber] as $search) {
            Livewire::actingAs($this->admin())->test(ListOrders::class)
                ->searchTable($search)
                ->assertCanSeeTableRecords([$target])
                ->assertCanNotSeeTableRecords([$other]);
        }
    }

    public function test_the_order_page_refunds_every_unused_ticket_once(): void
    {
        $order = $this->order(3);
        $used = $order->items->first()->tickets->first();
        $used->update(['status' => AdmissionTicketStatus::Used]);

        Livewire::actingAs($this->admin())->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('refundUnused', data: ['reason' => 'انصراف گروه'])
            ->assertNotified();

        $this->assertSame(2, Refund::count());
        $this->assertSame(AdmissionTicketStatus::Used, $used->fresh()->status);
        $this->assertSame(2, $order->tickets()->where('admission_tickets.status', AdmissionTicketStatus::Refunded)->count());
    }

    public function test_a_blocked_ticket_is_rejected_at_the_gate_until_unblocked(): void
    {
        $order = $this->order(1);
        $ticket = $order->items->first()->tickets->first();
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $ticket->ticket_variant_id]);
        $admin = app(TicketAdministration::class);

        $admin->block($ticket, 'کپی QR مشکوک');

        $this->assertSame(AdmissionTicketStatus::Blocked, $ticket->fresh()->status);
        $this->assertSame('کپی QR مشکوک', $ticket->fresh()->blocked_reason);
        $this->assertSame(GateScanResult::Rejected, app(GateValidationService::class)->scan($ticket->qrToken->token, $gate, null)['result']);

        $admin->unblock($ticket);

        $this->assertSame(AdmissionTicketStatus::Active, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->blocked_reason);
        $this->assertSame(GateScanResult::Success, app(GateValidationService::class)->scan($ticket->qrToken->token, $gate, null)['result']);
    }

    public function test_unblocking_restores_the_right_status_for_a_partly_used_ticket(): void
    {
        $ticket = $this->order(1)->items->first()->tickets->first();
        $ticket->update(['allowed_uses' => 2, 'used_uses' => 1, 'status' => AdmissionTicketStatus::PartiallyUsed]);
        $admin = app(TicketAdministration::class);

        $admin->block($ticket, 'x');
        $admin->unblock($ticket);

        $this->assertSame(AdmissionTicketStatus::PartiallyUsed, $ticket->fresh()->status);
    }

    public function test_blocking_a_refunded_ticket_or_unblocking_an_open_one_is_refused(): void
    {
        $ticket = $this->order(1)->items->first()->tickets->first();
        $admin = app(TicketAdministration::class);

        try {
            $admin->unblock($ticket);
            $this->fail('Expected unblock to be refused.');
        } catch (TicketingException) {
        }

        $ticket->update(['status' => AdmissionTicketStatus::Refunded]);

        $this->expectException(TicketingException::class);

        $admin->block($ticket, 'x');
    }

    public function test_the_ticket_row_actions_block_and_refund(): void
    {
        $order = $this->order(2);
        [$first, $second] = $order->items->first()->tickets->all();
        $page = Livewire::actingAs($this->admin())->test(TicketsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class]);

        $page->callTableAction('block', $first, data: ['reason' => 'تست']);
        $page->callTableAction('refund', $second, data: ['reason' => 'انصراف']);

        $this->assertSame(AdmissionTicketStatus::Blocked, $first->fresh()->status);
        $this->assertSame(AdmissionTicketStatus::Refunded, $second->fresh()->status);
    }
}
