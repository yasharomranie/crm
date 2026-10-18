<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestMessenger;
use App\Domain\Crm\GuestSegmentQuery;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\ConsentsRelationManager;
use App\Models\Guest;
use App\Models\GuestSegment;
use App\Models\LeadCaptureForm;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestConsentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_no_answer_means_no_consent(): void
    {
        $this->assertFalse(Guest::factory()->create()->hasMarketingConsent(MessageChannel::Sms));
    }

    public function test_the_newest_decision_wins_and_history_is_kept(): void
    {
        $guest = Guest::factory()->create();

        $guest->recordConsent(MessageChannel::Sms, true, 'customer_app');
        $this->assertTrue($guest->hasMarketingConsent(MessageChannel::Sms));

        $guest->recordConsent(MessageChannel::Sms, false, 'customer_app');
        $this->assertFalse($guest->hasMarketingConsent(MessageChannel::Sms));
        $this->assertSame(2, $guest->consents()->count());
    }

    public function test_repeating_the_same_decision_adds_nothing(): void
    {
        $guest = Guest::factory()->create();

        $this->assertNotNull($guest->recordConsent(MessageChannel::Sms, true, 'lead_form'));
        $this->assertNull($guest->recordConsent(MessageChannel::Sms, true, 'lead_form'));
        $this->assertNull($guest->recordConsent(MessageChannel::Email, false, 'customer_app'));
        $this->assertSame(1, $guest->consents()->count());
    }

    public function test_channels_are_independent(): void
    {
        $guest = Guest::factory()->create();
        $guest->recordConsent(MessageChannel::Sms, true, 'staff');

        $this->assertTrue($guest->hasMarketingConsent(MessageChannel::Sms));
        $this->assertFalse($guest->hasMarketingConsent(MessageChannel::Email));
    }

    public function test_a_promotional_sms_is_refused_without_consent_and_sent_with_it(): void
    {
        Http::fake(['*' => Http::response(['status' => 1], 200)]);
        SmsSetting::factory()->create(['id' => 1, 'is_enabled' => true]);
        $guest = Guest::factory()->create(['mobile' => '09120000077']);
        $messenger = app(GuestMessenger::class);

        $refused = $messenger->sendSms($guest, 'تخفیف ویژه', null, marketing: true);

        $this->assertSame(MessageDeliveryStatus::Failed, $refused->status);
        $this->assertSame(GuestMessenger::NO_CONSENT_ERROR, $refused->error);
        Http::assertNothingSent();

        $guest->recordConsent(MessageChannel::Sms, true, 'staff');

        $this->assertSame(MessageDeliveryStatus::Sent, $messenger->sendSms($guest, 'تخفیف ویژه', null, marketing: true)->status);
    }

    public function test_a_service_message_does_not_need_consent(): void
    {
        Http::fake(['*' => Http::response(['status' => 1], 200)]);
        SmsSetting::factory()->create(['id' => 1, 'is_enabled' => true]);
        $guest = Guest::factory()->create(['mobile' => '09120000078']);

        $this->assertSame(MessageDeliveryStatus::Sent, app(GuestMessenger::class)->sendSms($guest, 'رزرو شما تأیید شد')->status);
    }

    public function test_the_lead_form_checkbox_records_consent_with_the_visitors_ip(): void
    {
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), ['name' => 'علی', 'mobile' => '09121112233', 'sms_consent' => '1'])
            ->assertRedirect();

        $guest = Guest::where('mobile', '09121112233')->sole();
        $this->assertTrue($guest->hasMarketingConsent(MessageChannel::Sms));
        $this->assertSame('lead_form', $guest->consents()->sole()->source);
        $this->assertNotNull($guest->consents()->sole()->ip_address);
    }

    public function test_without_the_checkbox_no_consent_is_recorded(): void
    {
        $form = LeadCaptureForm::factory()->create();

        $this->post(route('lead-capture.store', $form->slug), ['name' => 'علی', 'mobile' => '09121112244'])->assertRedirect();

        $this->assertSame(0, Guest::where('mobile', '09121112244')->sole()->consents()->count());
    }

    public function test_the_guest_can_change_their_choice_in_the_customer_app(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'customer')->post(route('customer-app.profile.consents'), ['sms' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($guest->hasMarketingConsent(MessageChannel::Sms));

        $this->actingAs($guest, 'customer')->post(route('customer-app.profile.consents'), [])->assertSessionHasNoErrors();
        $this->assertFalse($guest->hasMarketingConsent(MessageChannel::Sms));
    }

    public function test_staff_can_record_a_decision_given_by_phone(): void
    {
        $guest = Guest::factory()->create();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ConsentsRelationManager::class, ['ownerRecord' => $guest, 'pageClass' => ViewGuest::class])
            ->callTableAction('recordConsent', data: ['channel' => 'email', 'granted' => '1', 'note' => 'تلفنی'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($guest->hasMarketingConsent(MessageChannel::Email));
        $this->assertSame($admin->id, $guest->consents()->sole()->recorded_by);
    }

    public function test_a_segment_can_be_limited_to_guests_with_consent(): void
    {
        $yes = Guest::factory()->create();
        $revoked = Guest::factory()->create();
        Guest::factory()->create();
        $yes->recordConsent(MessageChannel::Sms, true, 'staff');
        $revoked->recordConsent(MessageChannel::Sms, true, 'staff');
        $revoked->recordConsent(MessageChannel::Sms, false, 'staff');
        $segment = GuestSegment::factory()->create(['conditions' => [['type' => 'sms_consent', 'answer' => 'yes']]]);

        $this->assertSame([$yes->id], app(GuestSegmentQuery::class)->query($segment)->pluck('guests.id')->all());
    }
}
