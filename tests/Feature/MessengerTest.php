<?php

namespace Tests\Feature;

use App\Enums\ConversationType;
use App\Filament\Pages\Messenger;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_direct_conversation_and_sending_a_message(): void
    {
        Notification::fake();

        $me = User::factory()->create();
        $colleague = User::factory()->create();

        $this->actingAs($me);

        Livewire::test(Messenger::class)
            ->call('openNewConversationModal')
            ->set('newConversationType', 'direct')
            ->set('newConversationUserId', $colleague->id)
            ->call('startConversation')
            ->assertSet('showNewConversationModal', false);

        $conversation = Conversation::query()->where('type', ConversationType::Direct)->sole();

        Livewire::test(Messenger::class)
            ->set('conversationId', $conversation->id)
            ->set('body', 'سلام همکار!')
            ->call('sendMessage')
            ->assertSet('body', '');

        $this->assertSame('سلام همکار!', $conversation->messages()->latest()->first()->body);
    }

    public function test_starting_a_direct_conversation_twice_reuses_the_same_thread(): void
    {
        $me = User::factory()->create();
        $colleague = User::factory()->create();

        $this->actingAs($me);

        $component = Livewire::test(Messenger::class)
            ->set('newConversationType', 'direct')
            ->set('newConversationUserId', $colleague->id)
            ->call('startConversation');

        $firstConversationId = $component->get('conversationId');

        Livewire::test(Messenger::class)
            ->set('newConversationType', 'direct')
            ->set('newConversationUserId', $colleague->id)
            ->call('startConversation')
            ->assertSet('conversationId', $firstConversationId);

        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_a_recipient_sees_the_message_as_unread_until_they_open_the_conversation(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $this->actingAs($sender);
        Livewire::test(Messenger::class)
            ->set('conversationId', $conversation->id)
            ->set('body', 'گزارش امروز آماده است.')
            ->call('sendMessage');

        $this->assertSame(1, $conversation->fresh()->unreadCountFor($recipient));

        $this->actingAs($recipient);
        Livewire::test(Messenger::class)
            ->call('selectConversation', $conversation->id);

        $this->assertSame(0, $conversation->fresh()->unreadCountFor($recipient));
    }

    public function test_sending_a_message_notifies_the_other_participant_but_not_the_sender(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $this->actingAs($sender);
        Livewire::test(Messenger::class)
            ->set('conversationId', $conversation->id)
            ->set('body', 'گزارش امروز آماده است.')
            ->call('sendMessage');

        Notification::assertSentTo($recipient, NewMessageReceived::class);
        Notification::assertNotSentTo($sender, NewMessageReceived::class);
    }

    public function test_navigation_badge_reflects_total_unread_messages(): void
    {
        $me = User::factory()->create();
        $colleagueA = User::factory()->create();
        $colleagueB = User::factory()->create();

        $conversationA = Conversation::findOrCreateDirect($me, $colleagueA);
        $conversationB = Conversation::findOrCreateDirect($me, $colleagueB);

        $conversationA->messages()->create(['sender_id' => $colleagueA->id, 'body' => 'یک']);
        $conversationB->messages()->create(['sender_id' => $colleagueB->id, 'body' => 'دو']);
        $conversationB->messages()->create(['sender_id' => $colleagueB->id, 'body' => 'سه']);

        $this->actingAs($me);

        $this->assertSame('۳', Messenger::getNavigationBadge());

        $conversationA->markReadFor($me);

        $this->assertSame('۲', Messenger::getNavigationBadge());
    }

    public function test_creating_a_group_conversation_with_selected_participants(): void
    {
        $me = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($me);

        Livewire::test(Messenger::class)
            ->set('newConversationType', 'group')
            ->set('newConversationName', 'تیم پشتیبانی')
            ->set('newConversationParticipantIds', [$a->id, $b->id])
            ->call('startConversation');

        $group = Conversation::query()->where('type', ConversationType::Group)->sole();

        $this->assertSame('تیم پشتیبانی', $group->name);
        $this->assertSame(3, $group->participants()->count());
    }
}
