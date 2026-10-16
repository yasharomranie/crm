<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\StaleDealAlertService;
use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\StaleDealsDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaleDealAlertTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function deal(array $attributes = [], int $idleDays = 20): Deal
    {
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::ProposalSent, ...$attributes]);

        // Written below Eloquent so updated_at really is in the past.
        Deal::query()->whereKey($deal->id)->toBase()->update(['updated_at' => now()->subDays($idleDays)]);

        return $deal->fresh();
    }

    public function test_an_owner_gets_one_digest_covering_all_their_stale_deals(): void
    {
        Notification::fake();

        $rep = User::factory()->create();
        $this->deal(['title' => 'الف', 'assigned_to' => $rep->id]);
        $this->deal(['title' => 'ب', 'assigned_to' => $rep->id]);

        $this->assertSame(2, app(StaleDealAlertService::class)->notifyStaleDeals());

        Notification::assertSentToTimes($rep, StaleDealsDigestNotification::class, 1);
    }

    public function test_unassigned_stale_deals_alert_every_admin(): void
    {
        Notification::fake();

        $adminOne = $this->admin();
        $adminTwo = $this->admin();
        $bystander = User::factory()->create();
        $this->deal();

        app(StaleDealAlertService::class)->notifyStaleDeals();

        Notification::assertSentTo($adminOne, StaleDealsDigestNotification::class);
        Notification::assertSentTo($adminTwo, StaleDealsDigestNotification::class);
        Notification::assertNotSentTo($bystander, StaleDealsDigestNotification::class);
    }

    public function test_fresh_won_and_lost_deals_are_not_flagged(): void
    {
        Notification::fake();

        $rep = User::factory()->create();
        $this->deal(['assigned_to' => $rep->id], idleDays: 3);
        $this->deal(['assigned_to' => $rep->id, 'stage' => DealStage::Won]);
        $this->deal(['assigned_to' => $rep->id, 'stage' => DealStage::Lost]);

        $this->assertSame(0, app(StaleDealAlertService::class)->notifyStaleDeals());

        Notification::assertNothingSent();
    }

    public function test_a_deal_is_flagged_once_per_idle_stretch_but_again_after_being_touched(): void
    {
        Notification::fake();

        $rep = User::factory()->create();
        $deal = $this->deal(['assigned_to' => $rep->id]);
        $service = app(StaleDealAlertService::class);

        $this->assertSame(1, $service->notifyStaleDeals());
        $this->assertSame(0, $service->notifyStaleDeals());

        // The alert itself must not have made the deal look freshly touched.
        $this->assertTrue($deal->fresh()->updated_at->lt(now()->subDays(StaleDealAlertService::STALE_AFTER_DAYS)));

        // Someone works the deal after that alert, then it goes quiet again:
        // modelled by an alert 30 days ago and a last touch 20 days ago.
        $deal->update(['notes' => 'تماس گرفته شد']);
        Deal::query()->whereKey($deal->id)->toBase()->update([
            'updated_at' => now()->subDays(20),
            'stale_notified_at' => now()->subDays(30),
        ]);

        $this->assertSame(1, $service->notifyStaleDeals());
    }
}
