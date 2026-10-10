<?php

namespace Tests\Feature;

use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantsHub;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantWatchedSource;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

            return str_starts_with($system['content'], 'فقط به زبان رسمی پاسخ بده.');
        });

        $conversation = AiConversation::sole();
        $this->assertSame($profile->id, $conversation->ai_assistant_profile_id);
        $this->assertSame(
            [AiMessageRole::User, AiMessageRole::Assistant],
            $conversation->messages->pluck('role')->all(),
        );

        // The only tools ever offered to a custom profile's chat are the
        // two narrow, self-contained ones below - never the
        // general-purpose CRM toolset the built-in assistants get.
        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return $toolNames === ['save_knowledge_entry', 'fetch_url'];
        });
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

    public function test_sending_a_message_uses_the_profiles_preferred_model_when_one_is_set(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create(['default_model' => 'gpt-4o-mini']);
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'model' => 'gpt-4o']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(fn ($request) => $request->data()['model'] === 'gpt-4o');
    }

    public function test_sending_a_message_falls_back_to_the_providers_default_model_when_the_profile_has_none_preferred(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create(['default_model' => 'gpt-4o-mini']);
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'model' => null]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(fn ($request) => $request->data()['model'] === 'gpt-4o-mini');
    }

    public function test_updating_the_profile_model_persists_it_for_the_owner(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('updateProfileModel', 'gpt-4o');

        $this->assertSame('gpt-4o', $profile->fresh()->model);
    }

    public function test_a_non_owner_cannot_change_a_shared_profiles_model(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true, 'model' => null]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('updateProfileModel', 'gpt-4o');

        $this->assertNull($profile->fresh()->model);
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

    public function test_an_approved_knowledge_entry_is_included_in_the_system_prompt(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'description' => 'توضیح پایه.']);
        AiAssistantKnowledgeEntry::factory()->for($profile, 'profile')->create(['content' => 'نرخ مالیات بر ارزش افزوده ۱۰ درصد است.']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'باشه']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $system = collect($request->data()['messages'])->firstWhere('role', 'system');

            return str_contains($system['content'], 'نرخ مالیات بر ارزش افزوده ۱۰ درصد است.');
        });
    }

    public function test_a_pending_knowledge_entry_is_not_included_in_the_system_prompt(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'description' => 'توضیح پایه.']);
        AiAssistantKnowledgeEntry::factory()->pending()->for($profile, 'profile')->create(['content' => 'یک حدس تأییدنشده.']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'باشه']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $system = collect($request->data()['messages'])->firstWhere('role', 'system');

            return ! str_contains($system['content'], 'یک حدس تأییدنشده.');
        });
    }

    public function test_the_assistant_can_save_a_new_knowledge_entry_as_pending_by_default(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'save_knowledge_entry',
                                'arguments' => json_encode(['content' => 'نرخ کمیسیون فروش ۵ درصد است.']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ذخیره شد.']]],
            ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'یادت باشه کمیسیون فروش ۵ درصده')
            ->call('sendToProfile');

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertSame('نرخ کمیسیون فروش ۵ درصد است.', $entry->content);
        $this->assertSame(AiKnowledgeEntryStatus::Pending, $entry->status);
        $this->assertSame(AiKnowledgeEntrySource::AiSuggested, $entry->source);
    }

    public function test_the_assistant_can_save_a_knowledge_entry_as_approved_when_auto_save_is_on(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'knowledge_auto_save' => true]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'save_knowledge_entry',
                                'arguments' => json_encode(['content' => 'ساعت کاری دفتر ۹ تا ۱۷ است.']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ذخیره شد.']]],
            ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'ساعت کاری رو یادت باشه')
            ->call('sendToProfile');

        $this->assertSame(AiKnowledgeEntryStatus::Approved, AiAssistantKnowledgeEntry::sole()->status);
    }

    public function test_adding_a_manual_knowledge_entry_is_approved_immediately(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('addManualKnowledgeEntry', 'آدرس دفتر مرکزی خیابان آزادی است.');

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertSame('آدرس دفتر مرکزی خیابان آزادی است.', $entry->content);
        $this->assertSame(AiKnowledgeEntryStatus::Approved, $entry->status);
        $this->assertSame(AiKnowledgeEntrySource::Manual, $entry->source);
    }

    public function test_approving_a_pending_entry_changes_its_status(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $entry = AiAssistantKnowledgeEntry::factory()->pending()->for($profile, 'profile')->create();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('approveKnowledgeEntry', $entry->id);

        $this->assertSame(AiKnowledgeEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_deleting_a_knowledge_entry_removes_it(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $entry = AiAssistantKnowledgeEntry::factory()->for($profile, 'profile')->create();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('deleteKnowledgeEntry', $entry->id);

        $this->assertDatabaseMissing('ai_assistant_knowledge_entries', ['id' => $entry->id]);
    }

    public function test_toggling_knowledge_auto_save_flips_the_setting(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'knowledge_auto_save' => false]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleKnowledgeAutoSave');

        $this->assertTrue($profile->fresh()->knowledge_auto_save);
    }

    public function test_a_non_owner_cannot_manage_a_shared_profiles_knowledge_base(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true, 'knowledge_auto_save' => false]);
        $pending = AiAssistantKnowledgeEntry::factory()->pending()->for($profile, 'profile')->create();

        $this->actingAsHubUser();

        $component = Livewire::test(AiAssistantsHub::class)->call('openProfile', $profile->id);
        $component->call('approveKnowledgeEntry', $pending->id);
        $component->call('deleteKnowledgeEntry', $pending->id);
        $component->call('addManualKnowledgeEntry', 'نکتهٔ غیرمجاز');
        $component->call('toggleKnowledgeAutoSave');

        $this->assertSame(AiKnowledgeEntryStatus::Pending, $pending->fresh()->status);
        $this->assertDatabaseHas('ai_assistant_knowledge_entries', ['id' => $pending->id]);
        $this->assertDatabaseMissing('ai_assistant_knowledge_entries', ['content' => 'نکتهٔ غیرمجاز']);
        $this->assertFalse($profile->fresh()->knowledge_auto_save);
    }

    public function test_the_assistant_can_fetch_a_url_and_save_a_knowledge_entry_from_it(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'fetch_url',
                                    'arguments' => json_encode(['url' => 'http://8.8.8.8/prices']),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'save_knowledge_entry',
                                    'arguments' => json_encode(['content' => 'قیمت پایهٔ ویلا ۲ میلیون تومان است.']),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'نکته را ذخیره کردم.']]],
                ]),
            'http://8.8.8.8/prices' => Http::response('<html><body><p>قیمت پایهٔ ویلا ۲ میلیون تومان است.</p></body></html>'),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'از http://8.8.8.8/prices قیمت پایه رو برام پیدا کن')
            ->call('sendToProfile');

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertSame('قیمت پایهٔ ویلا ۲ میلیون تومان است.', $entry->content);
        $this->assertSame(AiKnowledgeEntryStatus::Pending, $entry->status);
        $this->assertSame(AiKnowledgeEntrySource::AiSuggested, $entry->source);

        Http::assertSent(fn ($request) => $request->url() === 'http://8.8.8.8/prices');
    }

    public function test_fetching_a_private_network_url_returns_an_error_instead_of_crashing(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'fetch_url',
                                    'arguments' => json_encode(['url' => 'http://169.254.169.254/latest/meta-data/']),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'نتوانستم آن آدرس را بخوانم.']]],
                ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'از http://169.254.169.254/latest/meta-data/ اطلاعات بیار')
            ->call('sendToProfile');

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        Http::assertNotSent(fn ($request) => $request->url() === 'http://169.254.169.254/latest/meta-data/');

        $toolMessage = $profile->conversations->sole()->messages()->where('role', AiMessageRole::Tool)->sole();
        $this->assertStringContainsString('error', $toolMessage->content);
    }

    public function test_the_owner_can_add_and_remove_a_watched_source(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $component = Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('addWatchedSource', 'https://example.com/prices', 'قیمت رقیب');

        $source = AiAssistantWatchedSource::sole();
        $this->assertSame('https://example.com/prices', $source->url);
        $this->assertSame('قیمت رقیب', $source->label);
        $this->assertSame($profile->id, $source->ai_assistant_profile_id);

        $component->call('deleteWatchedSource', $source->id);

        $this->assertDatabaseMissing('ai_assistant_watched_sources', ['id' => $source->id]);
    }

    public function test_a_non_owner_cannot_add_or_remove_a_shared_profiles_watched_sources(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        $source = AiAssistantWatchedSource::factory()->for($profile, 'profile')->create();

        $this->actingAsHubUser();

        $component = Livewire::test(AiAssistantsHub::class)->call('openProfile', $profile->id);
        $component->call('addWatchedSource', 'https://example.com/evil');
        $component->call('deleteWatchedSource', $source->id);

        $this->assertDatabaseMissing('ai_assistant_watched_sources', ['url' => 'https://example.com/evil']);
        $this->assertDatabaseHas('ai_assistant_watched_sources', ['id' => $source->id]);
    }

    public function test_the_owner_can_upload_a_document_and_get_pending_knowledge_entries(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['نرخ کمیسیون فروش ۵ درصد است.', 'ساعت کاری دفتر ۹ تا ۱۷ است.']),
                    ],
                ]],
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('price-list.txt', 'نرخ کمیسیون فروش ۵ درصد است. ساعت کاری دفتر ۹ تا ۱۷ است.');

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('knowledgeUploadFile', $file)
            ->call('processKnowledgeUpload');

        $entries = AiAssistantKnowledgeEntry::all();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn (AiAssistantKnowledgeEntry $entry) => $entry->status === AiKnowledgeEntryStatus::Pending));
        $this->assertTrue($entries->every(fn (AiAssistantKnowledgeEntry $entry) => $entry->source === AiKnowledgeEntrySource::AiSuggested));
    }

    public function test_an_unsupported_file_type_is_rejected_before_any_ai_call(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake();

        $file = UploadedFile::fake()->create('malware.exe', 10);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('knowledgeUploadFile', $file)
            ->call('processKnowledgeUpload')
            ->assertHasErrors(['knowledgeUploadFile']);

        Http::assertNothingSent();
        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
    }

    public function test_a_non_owner_cannot_upload_a_document_to_a_shared_profile(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        AiProvider::factory()->active()->create();

        $this->actingAsHubUser();

        Http::fake();

        $file = UploadedFile::fake()->createWithContent('note.txt', 'نکتهٔ محرمانه');

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('knowledgeUploadFile', $file)
            ->call('processKnowledgeUpload');

        Http::assertNothingSent();
        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
    }
}
