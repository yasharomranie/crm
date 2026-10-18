<?php

namespace Tests\Feature\Crm;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\WalletTransactionsRelationManager;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestWalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_credit_and_debit_write_the_ledger_and_the_balance_together(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);

        $guest->creditWallet(100_000, 'شارژ');
        $guest->debitWallet(30_000, 'خرید');

        $this->assertSame(70_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(70_000, (int) $guest->wallet_balance);
        $this->assertSame(70_000, $guest->walletLedgerBalance());
        $this->assertSame(2, $guest->walletTransactions()->count());
    }

    public function test_a_debit_larger_than_the_balance_is_refused_and_leaves_no_trace(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 10_000]);

        try {
            $guest->debitWallet(10_001, 'بیشتر از موجودی');
            $this->fail('Expected the debit to be refused.');
        } catch (InsufficientWalletBalanceException $exception) {
            $this->assertSame(10_000, $exception->balance);
            $this->assertSame(10_001, $exception->requested);
        }

        $this->assertSame(10_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(0, $guest->walletTransactions()->count());
    }

    public function test_the_whole_balance_can_be_spent(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 10_000]);

        $guest->debitWallet(10_000, 'خرج کامل');

        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
    }

    public function test_non_positive_amounts_are_rejected(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 10_000]);

        $this->expectException(InvalidArgumentException::class);

        $guest->creditWallet(0, 'صفر');
    }

    public function test_a_stale_model_cannot_overspend_because_the_row_is_re_read_under_lock(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 50_000]);
        $staleCopy = Guest::find($guest->id);

        $guest->debitWallet(40_000, 'اولین خرید');

        $this->expectException(InsufficientWalletBalanceException::class);

        $staleCopy->debitWallet(40_000, 'خرید دوم با نسخهٔ کهنه');
    }

    public function test_staff_cannot_debit_more_than_the_balance_from_the_guest_page(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 5_000]);

        Livewire::actingAs($this->admin())
            ->test(WalletTransactionsRelationManager::class, ['ownerRecord' => $guest, 'pageClass' => ViewGuest::class])
            ->mountTableAction('create')
            ->setTableActionData(['type' => WalletTransactionType::Debit->value, 'amount' => 9_000, 'reason' => 'اصلاح'])
            ->callMountedTableAction();

        $this->assertSame(5_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(0, $guest->walletTransactions()->count());
    }

    public function test_staff_credit_from_the_guest_page_updates_the_balance_once(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 5_000]);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(WalletTransactionsRelationManager::class, ['ownerRecord' => $guest, 'pageClass' => ViewGuest::class])
            ->mountTableAction('create')
            ->setTableActionData(['type' => WalletTransactionType::Credit->value, 'amount' => 20_000, 'reason' => 'هدیه'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(25_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame($admin->id, $guest->walletTransactions()->sole()->created_by);
    }
}
