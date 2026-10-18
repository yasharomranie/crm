<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestSegmentQuery;
use App\Domain\Crm\LeadScorer;
use App\Enums\BookingStatus;
use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Enums\TaskType;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\GuestSegment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadScoringTest extends TestCase
{
    use RefreshDatabase;

    private function scorer(): LeadScorer
    {
        return app(LeadScorer::class);
    }

    public function test_won_and_lost_deals_have_no_score(): void
    {
        $won = Deal::create(['title' => 'a', 'stage' => DealStage::Won]);
        $lost = Deal::create(['title' => 'b', 'stage' => DealStage::Lost]);

        $this->assertNull($won->fresh()->lead_score);
        $this->assertNull($lost->fresh()->lead_score);
        $this->assertNull($this->scorer()->score($won)['label']);
    }

    public function test_a_later_stage_scores_higher_than_an_earlier_one(): void
    {
        $early = Deal::create(['title' => 'early', 'stage' => DealStage::NewLead]);
        $late = Deal::create(['title' => 'late', 'stage' => DealStage::Negotiation]);

        $this->assertGreaterThan($early->fresh()->lead_score, $late->fresh()->lead_score);
    }

    public function test_every_point_comes_with_a_reason(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000001']);
        $deal = Deal::create(['title' => 'x', 'stage' => DealStage::ProposalSent, 'value' => 20_000_000, 'guest_id' => $guest->id, 'source' => DealSource::Referral]);

        $result = $this->scorer()->score($deal);

        $this->assertGreaterThan(0, $result['score']);
        $this->assertStringContainsString('+45 مرحله', implode('|', $result['reasons']));
        $this->assertStringContainsString('+10 ارزش', implode('|', $result['reasons']));
        $this->assertStringContainsString('+10 منبع: معرفی دوستان', implode('|', $result['reasons']));
    }

    public function test_a_returning_customer_with_a_live_deal_is_hot(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000002', 'wallet_balance' => 10_000]);
        Booking::factory()->create(['guest_id' => $guest->id, 'status' => BookingStatus::CheckedOut, 'actual_check_out_at' => now()->subMonths(2)]);
        $deal = Deal::create([
            'title' => 'x', 'stage' => DealStage::Negotiation, 'value' => 60_000_000, 'guest_id' => $guest->id,
            'source' => DealSource::Referral, 'expected_close_date' => now()->addDays(5),
        ]);

        $score = $deal->fresh()->lead_score;

        $this->assertGreaterThanOrEqual(LeadScorer::HOT_AT, $score);
        $this->assertSame('داغ', LeadScorer::labelFor($score));
    }

    public function test_a_neglected_early_deal_is_cold_and_the_score_is_clamped(): void
    {
        $deal = Deal::create(['title' => 'x', 'stage' => DealStage::NewLead]);
        $deal->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();

        $score = $this->scorer()->refresh($deal->fresh());

        $this->assertSame('سرد', LeadScorer::labelFor($score));
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_an_overdue_follow_up_lowers_the_score(): void
    {
        $deal = Deal::create(['title' => 'x', 'stage' => DealStage::ProposalSent]);
        $before = $deal->fresh()->lead_score;

        Task::create(['title' => 'پیگیری', 'task_type' => TaskType::CrmFollowUp, 'source_type' => Deal::class, 'source_id' => $deal->id, 'due_at' => now()->subDay()]);

        $this->assertSame($before - 5, $this->scorer()->refresh($deal->fresh()));
    }

    public function test_the_nightly_refresh_rescoring_picks_up_idle_time(): void
    {
        $deal = Deal::create(['title' => 'x', 'stage' => DealStage::NeedsAssessment]);
        $fresh = $deal->fresh()->lead_score;
        Deal::whereKey($deal->id)->update(['updated_at' => now()->subDays(20)]);

        $this->assertSame(1, $this->scorer()->refreshOpenDeals());
        $this->assertLessThan($fresh, $deal->fresh()->lead_score);
    }

    public function test_the_deals_list_can_be_filtered_by_temperature(): void
    {
        $hot = Deal::create(['title' => 'داغ', 'stage' => DealStage::Negotiation, 'value' => 60_000_000, 'source' => DealSource::Referral]);
        $cold = Deal::create(['title' => 'سرد', 'stage' => DealStage::NewLead]);
        Deal::whereKey($hot->id)->update(['lead_score' => 90]);
        Deal::whereKey($cold->id)->update(['lead_score' => 10]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        Livewire::actingAs($admin)->test(ListDeals::class)
            ->filterTable('temperature', 'hot')
            ->assertCanSeeTableRecords([$hot])
            ->assertCanNotSeeTableRecords([$cold]);
    }

    public function test_a_segment_can_target_guests_with_a_hot_lead(): void
    {
        $hotGuest = Guest::factory()->create();
        $coldGuest = Guest::factory()->create();
        $hot = Deal::create(['title' => 'x', 'stage' => DealStage::Negotiation, 'guest_id' => $hotGuest->id]);
        Deal::create(['title' => 'y', 'stage' => DealStage::NewLead, 'guest_id' => $coldGuest->id]);
        Deal::whereKey($hot->id)->update(['lead_score' => 85]);
        $segment = GuestSegment::factory()->create(['conditions' => [['type' => 'hot_lead', 'answer' => 'yes']]]);

        $ids = app(GuestSegmentQuery::class)->query($segment)->pluck('guests.id')->all();

        $this->assertSame([$hotGuest->id], $ids);
    }
}
