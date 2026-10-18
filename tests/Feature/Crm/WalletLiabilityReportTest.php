<?php

namespace Tests\Feature\Crm;

use App\Filament\Pages\WalletLiabilityReport;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletLiabilityReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_summary_adds_up_balances_and_recent_activity(): void
    {
        $a = Guest::factory()->create(['wallet_balance' => 0]);
        $a->creditWallet(100_000, 'شارژ');
        $a->debitWallet(30_000, 'خرید');
        $b = Guest::factory()->create(['wallet_balance' => 0]);
        $b->creditWallet(50_000, 'شارژ');
        Guest::factory()->create(['wallet_balance' => 0]);

        $summary = Livewire::actingAs($this->admin())->test(WalletLiabilityReport::class)->instance()->summary();

        $this->assertSame(120_000, $summary['total_liability']);
        $this->assertSame(2, $summary['guests_with_balance']);
        $this->assertSame(150_000, $summary['credited_30d']);
        $this->assertSame(30_000, $summary['debited_30d']);
        $this->assertSame(0, $summary['mismatched']);
    }

    public function test_expiring_credit_is_counted_by_its_unspent_part(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $guest->creditWallet(100_000, 'کش‌بک', null, now()->addDays(10));
        $guest->debitWallet(40_000, 'خرید');

        $summary = Livewire::actingAs($this->admin())->test(WalletLiabilityReport::class)->instance()->summary();

        $this->assertSame(60_000, $summary['expiring_30d']);
    }

    public function test_a_balance_changed_outside_the_ledger_is_flagged_and_filterable(): void
    {
        $healthy = Guest::factory()->create(['wallet_balance' => 0]);
        $healthy->creditWallet(10_000, 'شارژ');
        $tampered = Guest::factory()->create(['wallet_balance' => 0]);
        $tampered->creditWallet(10_000, 'شارژ');
        Guest::whereKey($tampered->id)->update(['wallet_balance' => 999_000]);

        $page = Livewire::actingAs($this->admin())->test(WalletLiabilityReport::class);

        $this->assertSame(1, $page->instance()->summary()['mismatched']);

        $page->filterTable('mismatch')
            ->assertCanSeeTableRecords([$tampered])
            ->assertCanNotSeeTableRecords([$healthy]);
    }
}
