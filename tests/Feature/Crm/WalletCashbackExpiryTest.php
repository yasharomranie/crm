<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\WalletExpiryService;
use App\Enums\BookingStatus;
use App\Filament\Pages\WalletSettings;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Guest;
use App\Models\SmsSetting;
use App\Models\User;
use App\Models\WalletSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletCashbackExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function checkedInBooking(Guest $guest, int $total): Booking
    {
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'status' => BookingStatus::CheckedIn]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_default_settings_keep_the_old_two_percent_for_everyone_and_no_expiry(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);

        $this->checkedInBooking($guest, 1_000_000)->checkOut();

        $credit = $guest->walletTransactions()->sole();
        $this->assertSame(20_000, (int) $credit->amount);
        $this->assertNull($credit->expires_at);
    }

    public function test_cashback_percentage_follows_the_guests_loyalty_tier(): void
    {
        WalletSetting::current()->update(['cashback_bronze_percent' => 1, 'cashback_silver_percent' => 5]);
        $guest = Guest::factory()->create(['wallet_balance' => 0]);

        // The checkout itself makes the guest "silver" (one completed stay).
        $this->checkedInBooking($guest, 1_000_000)->checkOut();

        $this->assertSame(50_000, (int) $guest->walletTransactions()->sole()->amount);
    }

    public function test_cashback_gets_an_expiry_date_when_a_validity_period_is_set(): void
    {
        WalletSetting::current()->update(['cashback_expiry_days' => 30]);
        $guest = Guest::factory()->create(['wallet_balance' => 0]);

        $this->checkedInBooking($guest, 1_000_000)->checkOut();

        $this->assertTrue($guest->walletTransactions()->sole()->expires_at->isSameDay(now()->addDays(30)));
    }

    public function test_expiry_takes_back_only_the_unspent_part_of_the_credit(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $guest->creditWallet(100_000, 'کش‌بک', null, now()->subDay());
        $guest->debitWallet(30_000, 'خرید');

        $expired = app(WalletExpiryService::class)->expireDue();

        $this->assertSame(70_000, $expired);
        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(0, $guest->fresh()->walletLedgerBalance());
    }

    public function test_expiry_runs_only_once_per_credit(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $guest->creditWallet(100_000, 'کش‌بک', null, now()->subDay());

        $service = app(WalletExpiryService::class);

        $this->assertSame(100_000, $service->expireDue());
        $this->assertSame(0, $service->expireDue());
        $this->assertSame(1, $guest->walletTransactions()->where('type', 'debit')->count());
    }

    public function test_spending_uses_the_soonest_expiring_credit_first(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $permanent = $guest->creditWallet(50_000, 'شارژ دستی');
        $expiring = $guest->creditWallet(50_000, 'کش‌بک', null, now()->addDays(2));
        $guest->debitWallet(50_000, 'خرید');

        $unspent = app(WalletExpiryService::class)->unspentCredits($guest);

        $this->assertSame(0, $unspent[$expiring->id]['remaining']);
        $this->assertSame(50_000, $unspent[$permanent->id]['remaining']);
    }

    public function test_credits_that_never_expire_are_left_alone(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $guest->creditWallet(100_000, 'شارژ دستی');

        $this->assertSame(0, app(WalletExpiryService::class)->expireDue());
        $this->assertSame(100_000, (int) $guest->fresh()->wallet_balance);
    }

    public function test_a_reminder_sms_is_sent_once_for_credit_about_to_expire(): void
    {
        Http::fake(['*' => Http::response(['status' => 1], 200)]);
        SmsSetting::factory()->create(['id' => 1, 'is_enabled' => true]);
        $guest = Guest::factory()->create(['wallet_balance' => 0, 'mobile' => '09120000099']);
        $credit = $guest->creditWallet(80_000, 'کش‌بک', null, now()->addDays(3));

        $service = app(WalletExpiryService::class);
        $service->sendExpiryReminders();
        $service->sendExpiryReminders();

        $this->assertNotNull($credit->fresh()->expiry_reminded_at);
        $this->assertSame(1, $guest->customerMessages()->count());
    }

    public function test_credit_far_from_expiry_gets_no_reminder(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $guest->creditWallet(80_000, 'کش‌بک', null, now()->addDays(60));

        $this->assertSame(0, app(WalletExpiryService::class)->sendExpiryReminders());
        $this->assertSame(0, $guest->customerMessages()->count());
    }

    public function test_the_settings_page_saves_new_rules(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WalletSettings::class)
            ->fillForm([
                'cashback_bronze_percent' => 1,
                'cashback_silver_percent' => 3,
                'cashback_gold_percent' => 5,
                'cashback_vip_percent' => 8,
                'cashback_expiry_days' => 90,
                'expiry_reminder_days' => 5,
            ])
            ->call('save');

        $settings = WalletSetting::current()->fresh();

        $this->assertSame(8.0, $settings->cashback_vip_percent);
        $this->assertSame(90, $settings->cashback_expiry_days);
        $this->assertSame(5, $settings->expiry_reminder_days);
    }
}
