<?php

namespace Tests\Feature\Crm;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Filament\Pages\CommunicationInbox;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\CustomerMessagesRelationManager;
use App\Mail\GuestEmail;
use App\Models\CustomerMessage;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_inbox_lists_each_guest_once_with_their_latest_message_first(): void
    {
        $older = Guest::factory()->create(['name' => 'مشتری قدیمی‌تر']);
        $newer = Guest::factory()->create(['name' => 'مشتری تازه‌تر']);

        CustomerMessage::factory()->create(['guest_id' => $older->id, 'subject' => 'اولین']);
        CustomerMessage::factory()->create(['guest_id' => $newer->id, 'subject' => 'دومین']);
        CustomerMessage::factory()->create(['guest_id' => $older->id, 'subject' => 'سومین و آخرین']);

        $conversations = Livewire::actingAs($this->admin())
            ->test(CommunicationInbox::class)
            ->assertSee('مشتری قدیمی‌تر')
            ->assertSee('مشتری تازه‌تر')
            ->instance()
            ->conversations();

        $this->assertSame([$older->id, $newer->id], $conversations->pluck('guest_id')->all());
        $this->assertSame('سومین و آخرین', $conversations->first()->subject);
    }

    public function test_selecting_a_conversation_shows_its_thread(): void
    {
        $guest = Guest::factory()->create();
        CustomerMessage::factory()->create(['guest_id' => $guest->id, 'body' => 'متن اولین پیام']);

        Livewire::actingAs($this->admin())
            ->test(CommunicationInbox::class)
            ->assertDontSee('متن اولین پیام')
            ->call('selectGuest', $guest->id)
            ->assertSee('متن اولین پیام');
    }

    public function test_a_reply_can_be_sent_by_email_from_the_inbox(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $guest = Guest::factory()->create(['email' => 'sara@example.com']);

        Livewire::actingAs($this->admin())
            ->test(CommunicationInbox::class)
            ->call('selectGuest', $guest->id)
            ->set('channel', 'email')
            ->set('subject', 'پاسخ شما')
            ->set('body', 'ممنون از پیگیری شما.')
            ->call('send')
            ->assertHasNoErrors();

        Mail::assertSent(GuestEmail::class, fn (GuestEmail $mail) => $mail->hasTo('sara@example.com'));
        $this->assertSame(MessageDeliveryStatus::Sent, $guest->customerMessages()->firstOrFail()->status);
    }

    public function test_an_email_reply_requires_a_subject_and_a_body(): void
    {
        $guest = Guest::factory()->create(['email' => 'sara@example.com']);

        Livewire::actingAs($this->admin())
            ->test(CommunicationInbox::class)
            ->call('selectGuest', $guest->id)
            ->set('channel', 'email')
            ->call('send')
            ->assertHasErrors(['subject', 'body']);

        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_the_guest_page_offers_email_and_sms_only_when_the_guest_has_that_contact_detail(): void
    {
        $emailOnly = Guest::factory()->create(['email' => 'a@example.com', 'mobile' => null]);
        $mobileOnly = Guest::factory()->create(['email' => null, 'mobile' => '09121234567']);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewGuest::class, ['record' => $emailOnly->getKey()])
            ->assertActionVisible('sendEmail')
            ->assertActionHidden('sendSms');

        Livewire::actingAs($admin)
            ->test(ViewGuest::class, ['record' => $mobileOnly->getKey()])
            ->assertActionHidden('sendEmail')
            ->assertActionVisible('sendSms');
    }

    public function test_sending_an_email_from_the_guest_page_logs_it(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $guest = Guest::factory()->create(['email' => 'sara@example.com']);

        Livewire::actingAs($this->admin())
            ->test(ViewGuest::class, ['record' => $guest->getKey()])
            ->mountAction('sendEmail')
            ->setActionData(['subject' => 'سلام', 'body' => 'متن'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Mail::assertSent(GuestEmail::class);
        $this->assertSame(MessageChannel::Email, $guest->customerMessages()->firstOrFail()->channel);
    }

    public function test_the_guests_messages_tab_lists_their_log(): void
    {
        $guest = Guest::factory()->create();
        $message = CustomerMessage::factory()->create(['guest_id' => $guest->id]);

        Livewire::actingAs($this->admin())
            ->test(CustomerMessagesRelationManager::class, ['ownerRecord' => $guest, 'pageClass' => ViewGuest::class])
            ->assertCanSeeTableRecords([$message]);
    }
}
