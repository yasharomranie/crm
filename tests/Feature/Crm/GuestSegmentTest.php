<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestSegmentQuery;
use App\Enums\BookingStatus;
use App\Enums\DealStage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\GuestSegments\Pages\ManageGuestSegments;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\GuestSegment;
use App\Models\GuestTicket;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestSegmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function segment(array $conditions, string $match = 'all'): GuestSegment
    {
        return GuestSegment::factory()->create(['match_type' => $match, 'conditions' => $conditions]);
    }

    /**
     * @return list<int>
     */
    private function memberIds(GuestSegment $segment): array
    {
        return app(GuestSegmentQuery::class)->query($segment)->pluck('guests.id')->sort()->values()->all();
    }

    private function completedStay(Guest $guest, int $daysAgo): void
    {
        Booking::factory()->create([
            'guest_id' => $guest->id,
            'status' => BookingStatus::CheckedOut,
            'actual_check_out_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_a_segment_without_rules_matches_nobody(): void
    {
        Guest::factory()->create();

        $this->assertSame([], $this->memberIds($this->segment([])));
    }

    public function test_an_unknown_rule_type_matches_nobody(): void
    {
        Guest::factory()->create();

        $this->assertSame([], $this->memberIds($this->segment([['type' => 'nonsense', 'value' => 1]])));
    }

    public function test_completed_stays_rule_counts_only_checked_out_bookings(): void
    {
        $loyal = Guest::factory()->create();
        $this->completedStay($loyal, 10);
        $this->completedStay($loyal, 40);
        $once = Guest::factory()->create();
        $this->completedStay($once, 10);
        $onlyConfirmed = Guest::factory()->create();
        Booking::factory()->create(['guest_id' => $onlyConfirmed->id, 'status' => BookingStatus::Confirmed]);

        $segment = $this->segment([['type' => 'completed_stays', 'operator' => 'gte', 'value' => 2]]);

        $this->assertSame([$loyal->id], $this->memberIds($segment));
    }

    public function test_days_since_last_stay_finds_lapsed_guests_who_did_stay_before(): void
    {
        $lapsed = Guest::factory()->create();
        $this->completedStay($lapsed, 200);
        $recent = Guest::factory()->create();
        $this->completedStay($recent, 5);
        $this->completedStay($recent, 300);
        Guest::factory()->create(); // never stayed

        $segment = $this->segment([['type' => 'days_since_last_stay', 'value' => 90]]);

        $this->assertSame([$lapsed->id], $this->memberIds($segment));
    }

    public function test_never_stayed_rule(): void
    {
        $stayed = Guest::factory()->create();
        $this->completedStay($stayed, 3);
        $never = Guest::factory()->create();

        $this->assertSame([$never->id], $this->memberIds($this->segment([['type' => 'never_stayed']])));
    }

    public function test_wallet_balance_tag_open_deal_and_open_ticket_rules(): void
    {
        $tag = Tag::create(['name' => 'vip', 'slug' => 'vip']);
        $rich = Guest::factory()->create(['wallet_balance' => 500_000]);
        Guest::factory()->create(['wallet_balance' => 1_000]);
        $tagged = Guest::factory()->create();
        $tagged->attachTag($tag);
        $withDeal = Guest::factory()->create();
        Deal::create(['title' => 'تور', 'stage' => DealStage::Negotiation, 'guest_id' => $withDeal->id]);
        $wonOnly = Guest::factory()->create();
        Deal::create(['title' => 'قبلی', 'stage' => DealStage::Won, 'guest_id' => $wonOnly->id]);
        $withTicket = Guest::factory()->create();
        GuestTicket::create(['guest_id' => $withTicket->id, 'subject' => 'مشکل', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);

        $this->assertSame([$rich->id], $this->memberIds($this->segment([['type' => 'wallet_balance', 'operator' => 'gte', 'value' => 100_000]])));
        $this->assertSame([$tagged->id], $this->memberIds($this->segment([['type' => 'has_tag', 'tag_id' => $tag->id]])));
        $this->assertSame([$withDeal->id], $this->memberIds($this->segment([['type' => 'open_deal', 'answer' => 'yes']])));
        $this->assertSame([$withTicket->id], $this->memberIds($this->segment([['type' => 'open_ticket', 'answer' => 'yes']])));
    }

    public function test_contact_info_rules_distinguish_empty_strings_from_filled_values(): void
    {
        $withEmail = Guest::factory()->create(['email' => 'a@example.com']);
        Guest::factory()->create(['email' => '']);
        Guest::factory()->create(['email' => null]);

        $this->assertSame([$withEmail->id], $this->memberIds($this->segment([['type' => 'has_email', 'answer' => 'yes']])));
    }

    public function test_any_and_all_combine_rules_differently(): void
    {
        $both = Guest::factory()->create(['wallet_balance' => 900_000, 'email' => 'b@example.com']);
        $walletOnly = Guest::factory()->create(['wallet_balance' => 900_000, 'email' => null]);
        $emailOnly = Guest::factory()->create(['wallet_balance' => 0, 'email' => 'c@example.com']);
        Guest::factory()->create(['wallet_balance' => 0, 'email' => null]);

        $rules = [
            ['type' => 'wallet_balance', 'operator' => 'gte', 'value' => 500_000],
            ['type' => 'has_email', 'answer' => 'yes'],
        ];

        $this->assertSame([$both->id], $this->memberIds($this->segment($rules, 'all')));
        $this->assertSame(collect([$both->id, $walletOnly->id, $emailOnly->id])->sort()->values()->all(), $this->memberIds($this->segment($rules, 'any')));
    }

    public function test_the_guest_list_can_be_filtered_by_a_segment(): void
    {
        $match = Guest::factory()->create(['name' => 'مشتری هم‌بخش', 'wallet_balance' => 900_000]);
        $other = Guest::factory()->create(['name' => 'مشتری بیرون‌بخش', 'wallet_balance' => 0]);
        $segment = $this->segment([['type' => 'wallet_balance', 'operator' => 'gte', 'value' => 500_000]]);

        Livewire::actingAs($this->admin())
            ->test(ListGuests::class)
            ->filterTable('segment', $segment->id)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_segment_page_shows_the_live_member_count(): void
    {
        Guest::factory()->count(2)->create(['wallet_balance' => 900_000]);
        Guest::factory()->create(['wallet_balance' => 0]);
        $segment = $this->segment([['type' => 'wallet_balance', 'operator' => 'gte', 'value' => 500_000]]);

        Livewire::actingAs($this->admin())
            ->test(ManageGuestSegments::class)
            ->assertCanSeeTableRecords([$segment])
            ->assertSee('۲');
    }
}
