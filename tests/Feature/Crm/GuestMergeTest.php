<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestDuplicateDetector;
use App\Domain\Crm\GuestMergeService;
use App\Enums\DealStage;
use App\Enums\TaskType;
use App\Enums\TicketPriority;
use App\Filament\Pages\DuplicateGuests;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Models\Booking;
use App\Models\CustomerMessage;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\GuestTicket;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestMergeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_everything_the_duplicate_owned_moves_to_the_kept_guest_and_the_duplicate_is_deleted(): void
    {
        $keep = Guest::factory()->create(['name' => 'نگه‌داشته']);
        $duplicate = Guest::factory()->create(['name' => 'تکراری']);

        $booking = Booking::factory()->create(['guest_id' => $duplicate->id]);
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead, 'guest_id' => $duplicate->id]);
        $ticket = GuestTicket::create(['guest_id' => $duplicate->id, 'subject' => 'تیکت', 'priority' => TicketPriority::Normal]);
        $feedback = GuestFeedback::create(['guest_id' => $duplicate->id, 'nps_score' => 9]);
        $trip = TripPlan::create(['guest_id' => $duplicate->id, 'days' => 3]);
        $followUp = Task::factory()->create([
            'task_type' => TaskType::CrmFollowUp,
            'source_type' => Guest::class,
            'source_id' => $duplicate->id,
        ]);
        $message = CustomerMessage::factory()->create(['guest_id' => $duplicate->id]);
        $duplicate->creditWallet(50_000, 'شارژ');
        activity('crm_interactions')->performedOn($duplicate)->event('note')->log('یادداشت قدیمی');

        app(GuestMergeService::class)->merge($keep, $duplicate);

        $this->assertModelMissing($duplicate);
        $this->assertSame($keep->id, $booking->fresh()->guest_id);
        $this->assertSame($keep->id, $deal->fresh()->guest_id);
        $this->assertSame($keep->id, $ticket->fresh()->guest_id);
        $this->assertSame($keep->id, $feedback->fresh()->guest_id);
        $this->assertSame($keep->id, $trip->fresh()->guest_id);
        $this->assertSame($keep->id, $message->fresh()->guest_id);
        $this->assertSame($keep->id, $followUp->fresh()->source_id);
        $this->assertSame(1, $keep->walletTransactions()->count());
        $this->assertSame(50_000, (int) $keep->fresh()->wallet_balance);
        $this->assertTrue($keep->activitiesAsSubject()->where('description', 'یادداشت قدیمی')->exists());
    }

    public function test_wallet_balances_add_up_and_notes_are_combined(): void
    {
        $keep = Guest::factory()->create(['notes' => 'یادداشت اول']);
        $duplicate = Guest::factory()->create(['notes' => 'یادداشت دوم']);
        $keep->creditWallet(10_000, 'الف');
        $duplicate->creditWallet(5_000, 'ب');

        app(GuestMergeService::class)->merge($keep->fresh(), $duplicate->fresh());

        $keep->refresh();

        $this->assertSame(15_000, (int) $keep->wallet_balance);
        $this->assertSame("یادداشت اول\n---\nیادداشت دوم", $keep->notes);
    }

    public function test_blank_contact_fields_on_the_kept_guest_are_filled_from_the_duplicate_without_unique_clashes(): void
    {
        $keep = Guest::factory()->create(['mobile' => '09121234567', 'email' => null, 'national_code' => null]);
        $duplicate = Guest::factory()->create(['mobile' => '+989121234567', 'email' => 'dup@example.com', 'national_code' => '1234567890']);

        app(GuestMergeService::class)->merge($keep, $duplicate);

        $keep->refresh();

        $this->assertSame('09121234567', $keep->mobile);
        $this->assertSame('dup@example.com', $keep->email);
        $this->assertSame('1234567890', $keep->national_code);
    }

    public function test_the_kept_guests_own_contact_details_are_never_overwritten(): void
    {
        $keep = Guest::factory()->create(['mobile' => '09121234567', 'email' => 'keep@example.com']);
        $duplicate = Guest::factory()->create(['mobile' => '09359999999', 'email' => 'dup@example.com']);

        app(GuestMergeService::class)->merge($keep, $duplicate);

        $keep->refresh();

        $this->assertSame('09121234567', $keep->mobile);
        $this->assertSame('keep@example.com', $keep->email);
    }

    public function test_a_tag_both_guests_had_does_not_break_the_merge(): void
    {
        $keep = Guest::factory()->create();
        $duplicate = Guest::factory()->create();
        $shared = Tag::create(['name' => 'vip']);
        $onlyDuplicate = Tag::create(['name' => 'ski']);

        $keep->attachTag($shared);
        $duplicate->attachTag($shared);
        $duplicate->attachTag($onlyDuplicate);

        app(GuestMergeService::class)->merge($keep, $duplicate);

        $this->assertEqualsCanonicalizing([$shared->id, $onlyDuplicate->id], $keep->tags()->pluck('tags.id')->all());
    }

    public function test_the_merge_is_recorded_on_the_kept_guests_timeline(): void
    {
        $keep = Guest::factory()->create();
        $duplicate = Guest::factory()->create(['name' => 'نام تکراری', 'mobile' => '09121234567']);

        app(GuestMergeService::class)->merge($keep, $duplicate);

        $entry = $keep->activitiesAsSubject()->where('event', 'merged')->firstOrFail();

        $this->assertStringContainsString('نام تکراری', $entry->description);
        $this->assertSame('09121234567', $entry->properties['absorbed_guest']['mobile']);
    }

    public function test_a_guest_cannot_be_merged_into_itself(): void
    {
        $guest = Guest::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(GuestMergeService::class)->merge($guest, $guest);
    }

    public function test_the_duplicates_page_lists_a_group_and_merges_it_on_confirmation(): void
    {
        $keep = Guest::factory()->create(['name' => 'الف', 'mobile' => '09121234567']);
        $duplicate = Guest::factory()->create(['name' => 'ب', 'mobile' => '+989121234567']);

        Livewire::actingAs($this->admin())
            ->test(DuplicateGuests::class)
            ->assertSee(GuestDuplicateDetector::REASON_MOBILE)
            ->call('mergeGroup', $keep->id, [$duplicate->id])
            ->assertSee('هیچ پرونده‌ی تکراری‌ای پیدا نشد.');

        $this->assertModelMissing($duplicate);
        $this->assertModelExists($keep);
    }

    public function test_the_duplicates_page_refuses_ids_that_no_longer_form_a_detected_group(): void
    {
        $keep = Guest::factory()->create(['mobile' => '09121234567']);
        $stranger = Guest::factory()->create(['mobile' => '09359999999']);

        Livewire::actingAs($this->admin())
            ->test(DuplicateGuests::class)
            ->call('mergeGroup', $keep->id, [$stranger->id]);

        $this->assertModelExists($stranger);
    }

    public function test_a_guest_can_be_merged_into_another_from_its_own_page(): void
    {
        $keep = Guest::factory()->create(['name' => 'نگه‌دار']);
        $duplicate = Guest::factory()->create(['name' => 'حذف‌شونده']);
        $deal = Deal::create(['title' => 'معامله', 'stage' => DealStage::NewLead, 'guest_id' => $duplicate->id]);

        Livewire::actingAs($this->admin())
            ->test(ViewGuest::class, ['record' => $duplicate->getKey()])
            ->mountAction('mergeIntoAnother')
            ->setActionData(['keep_id' => $keep->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertModelMissing($duplicate);
        $this->assertSame($keep->id, $deal->fresh()->guest_id);
    }
}
