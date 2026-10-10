<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantsHub;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantsHubTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHubUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'کاربر مرکز دستیاران']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistants-hub.access')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_creating_a_profile_persists_the_chosen_avatar_and_prompt(): void
    {
        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->set('newName', 'پشتیبان مهمانان')
            ->set('newDescription', 'وظیفهٔ تو پاسخ به سوالات مهمانان دربارهٔ رزرو است.')
            ->set('newShape', 'teardrop')
            ->set('newColor', 'Teal')
            ->set('newIsShared', true)
            ->call('createProfile');

        $profile = AiAssistantProfile::sole();
        $this->assertSame('پشتیبان مهمانان', $profile->name);
        $this->assertSame('teardrop', $profile->avatar_shape);
        $this->assertSame('Teal', $profile->avatar_color);
        $this->assertTrue($profile->is_shared);
    }

    public function test_creating_a_profile_without_a_description_fails_validation(): void
    {
        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->set('newDescription', '')
            ->call('createProfile')
            ->assertHasErrors(['newDescription' => 'required']);

        $this->assertSame(0, AiAssistantProfile::count());
    }

    public function test_sending_a_message_to_a_profile_uses_its_description_as_the_system_prompt(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();

        $profile = AiAssistantProfile::factory()->create([
            'created_by' => $user->id,
            'description' => 'فقط به زبان رسمی پاسخ بده.',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'بله، در خدمت شما هستم.']],
                ],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $system = collect($request->data()['messages'])->firstWhere('role', 'system');

            return $system['content'] === 'فقط به زبان رسمی پاسخ بده.';
        });

        $conversation = AiConversation::sole();
        $this->assertSame($profile->id, $conversation->ai_assistant_profile_id);
        $this->assertSame(
            [AiMessageRole::User, AiMessageRole::Assistant],
            $conversation->messages->pluck('role')->all(),
        );

        // No tools are ever offered to a custom profile's chat (tools-free
        // by design in this phase).
        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data()));
    }

    public function test_a_private_profile_is_invisible_to_a_different_user(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => false]);

        $this->actingAsHubUser();

        $component = Livewire::test(AiAssistantsHub::class)->call('openProfile', $profile->id);

        $this->assertNull($component->get('activeProfileId'));
        $this->assertNotContains($profile->id, $component->instance()->getMyProfiles()->pluck('id')->all());
    }

    public function test_a_shared_profile_is_visible_and_usable_by_another_user(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);

        $this->actingAsHubUser();

        $component = Livewire::test(AiAssistantsHub::class)->call('openProfile', $profile->id);

        $this->assertSame($profile->id, $component->get('activeProfileId'));
    }

    public function test_a_user_cannot_delete_another_users_profile(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)->call('deleteProfile', $profile->id);

        $this->assertDatabaseHas('ai_assistant_profiles', ['id' => $profile->id]);
    }

    public function test_forwarding_a_conversation_copies_its_messages_onto_a_new_conversation_under_the_target_profile(): void
    {
        $user = $this->actingAsHubUser();

        $sourceProfile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'منبع']);
        $targetProfile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'مقصد']);

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'ai_assistant_profile_id' => $sourceProfile->id,
        ]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سوال من این است']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ قبلی']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $sourceProfile->id)
            ->call('selectProfileConversation', $conversation->id)
            ->call('forwardConversationTo', $targetProfile->id);

        $this->assertSame(2, AiConversation::count());

        $forwarded = AiConversation::where('ai_assistant_profile_id', $targetProfile->id)->sole();
        $this->assertSame(
            ['سوال من این است', 'پاسخ قبلی'],
            $forwarded->messages->pluck('content')->all(),
        );
    }

    /**
     * Regression guard for the one-line scope fix on
     * HasAiAssistantEngine::getConversations(): a conversation started
     * from a custom profile in the hub must never bleed into the global
     * management assistant's own conversation list.
     */
    public function test_the_global_assistants_conversation_list_excludes_hub_profile_conversations(): void
    {
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'کاربر هر دو دستیار']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistants-hub.access'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id, 'title' => 'گفتگوی هاب']);
        $globalConversation = AiConversation::create(['user_id' => $user->id, 'title' => 'گفتگوی عمومی']);

        $component = Livewire::test(AiAssistant::class);
        $ids = $component->instance()->getConversations()->pluck('id')->all();

        $this->assertSame([$globalConversation->id], $ids);
    }
}
