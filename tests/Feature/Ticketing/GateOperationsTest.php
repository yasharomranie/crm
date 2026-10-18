<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\GateAnomalyDetector;
use App\Domain\Ticketing\GateOccupancy;
use App\Domain\Ticketing\GateValidationService;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\TicketSourceType;
use App\Filament\Pages\GateScanner;
use App\Models\AdmissionTicket;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use App\Notifications\SuspiciousTicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function variant(): TicketVariant
    {
        $variant = TicketVariant::factory()->create(['allowed_uses' => 10]);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 10_000]);

        return $variant;
    }

    /**
     * @return array<int, AdmissionTicket>
     */
    private function tickets(TicketVariant $variant, int $quantity): array
    {
        return app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $quantity]],
        ])->items->first()->tickets->all();
    }

    private function gate(TicketVariant $variant, string $kind): Gate
    {
        $gate = Gate::factory()->create(['kind' => $kind]);
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);

        return $gate;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_inside_count_is_entries_minus_exits_and_ignores_two_way_gates(): void
    {
        $variant = $this->variant();
        $entry = $this->gate($variant, 'entry');
        $exit = $this->gate($variant, 'exit');
        $both = $this->gate($variant, 'both');
        [$a, $b, $c] = $this->tickets($variant, 3);
        $scanner = app(GateValidationService::class);

        $scanner->scan($a->qrToken->token, $entry, null);
        $scanner->scan($b->qrToken->token, $entry, null);
        $scanner->scan($c->qrToken->token, $entry, null);
        $scanner->scan($a->qrToken->token, $exit, null);
        $scanner->scan($b->qrToken->token, $both, null);

        $today = app(GateOccupancy::class)->today();

        $this->assertSame(2, $today['inside']);
        $this->assertSame(3, $today['entries']);
        $this->assertSame(1, $today['exits']);
        $this->assertSame(5, $today['total_passes']);
    }

    public function test_inside_never_goes_below_zero(): void
    {
        $variant = $this->variant();
        $exit = $this->gate($variant, 'exit');
        [$ticket] = $this->tickets($variant, 1);
        app(GateValidationService::class)->scan($ticket->qrToken->token, $exit, null);

        $this->assertSame(0, app(GateOccupancy::class)->today()['inside']);
    }

    public function test_the_scanner_page_shows_the_live_counter(): void
    {
        $variant = $this->variant();
        $entry = $this->gate($variant, 'entry');
        [$ticket] = $this->tickets($variant, 1);
        app(GateValidationService::class)->scan($ticket->qrToken->token, $entry, null);

        Livewire::actingAs($this->admin())->test(GateScanner::class)->assertSee('داخل مجموعه');
    }

    public function test_repeated_rejections_of_one_ticket_are_flagged_and_reported_once_an_hour(): void
    {
        Notification::fake();
        Cache::flush();
        $variant = $this->variant();
        $gate = $this->gate($variant, 'both');
        [$copied, $fine] = $this->tickets($variant, 2);
        $copied->update(['status' => 'blocked']);
        $scanner = app(GateValidationService::class);

        foreach (range(1, GateAnomalyDetector::MIN_REJECTIONS) as $_) {
            $scanner->scan($copied->qrToken->token, $gate, null);
        }
        $scanner->scan($fine->qrToken->token, $gate, null);
        $admin = $this->admin();

        $detector = app(GateAnomalyDetector::class);

        $this->assertSame([$copied->id], array_map(fn ($suspect) => $suspect['ticket']->id, $detector->suspiciousTickets()));
        $this->assertSame(1, $detector->notifyAdmins());
        $this->assertSame(0, $detector->notifyAdmins());
        Notification::assertSentToTimes($admin, SuspiciousTicketNotification::class, 1);
    }

    public function test_a_few_rejections_are_not_suspicious(): void
    {
        $variant = $this->variant();
        $gate = $this->gate($variant, 'both');
        [$ticket] = $this->tickets($variant, 1);
        $ticket->update(['status' => 'blocked']);

        foreach (range(1, GateAnomalyDetector::MIN_REJECTIONS - 1) as $_) {
            app(GateValidationService::class)->scan($ticket->qrToken->token, $gate, null);
        }

        $this->assertSame([], app(GateAnomalyDetector::class)->suspiciousTickets());
    }

    public function test_the_offline_package_is_delivered_in_pages(): void
    {
        $variant = $this->variant();
        $gate = $this->gate($variant, 'both');
        $this->tickets($variant, 105);

        $operator = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'gate-operator']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'gate-scanner.access', 'guard_name' => 'web']));
        $operator->assignRole($role);

        $first = $this->actingAs($operator)->getJson(route('gate-scanner.sync-package', [$gate, 'limit' => 100]))->assertSuccessful();

        $this->assertCount(100, $first->json('tickets'));
        $this->assertNotNull($first->json('next_after_id'));

        $second = $this->actingAs($operator)->getJson(route('gate-scanner.sync-package', [$gate, 'limit' => 100, 'after_id' => $first->json('next_after_id')]))->assertSuccessful();

        $this->assertCount(5, $second->json('tickets'));
        $this->assertNull($second->json('next_after_id'));
        $this->assertSame(105, collect($first->json('tickets'))->merge($second->json('tickets'))->pluck('token')->unique()->count());
    }
}
