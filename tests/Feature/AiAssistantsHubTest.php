<?php

namespace Tests\Feature;

use App\Enums\AiAssistantOcrUploadStatus;
use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantsHub;
use App\Jobs\OcrKnowledgeUploadJob;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantOcrUpload;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantWatchedSource;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsScannedPdfFixtures;
use Tests\TestCase;

class AiAssistantsHubTest extends TestCase
{
    use BuildsScannedPdfFixtures, RefreshDatabase;

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
        $component->call('toggleProfileConsultable');
        $component->call('updateProfileSpecialties', 'تخصص غیرمجاز');

        $this->assertSame(AiKnowledgeEntryStatus::Pending, $pending->fresh()->status);
        $this->assertDatabaseHas('ai_assistant_knowledge_entries', ['id' => $pending->id]);
        $this->assertDatabaseMissing('ai_assistant_knowledge_entries', ['content' => 'نکتهٔ غیرمجاز']);
        $this->assertFalse($profile->fresh()->knowledge_auto_save);
        $this->assertTrue($profile->fresh()->consultable);
        $this->assertNull($profile->fresh()->specialties);
    }

    public function test_creating_a_profile_with_specialties_saves_them_as_a_list(): void
    {
        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openCreateForm')
            ->set('newDescription', 'وظیفهٔ تو پاسخ به سوالات حقوقی است.')
            ->set('newSpecialties', 'حقوقی, قرارداد ,  مالیاتی')
            ->call('createProfile');

        $this->assertSame(['حقوقی', 'قرارداد', 'مالیاتی'], AiAssistantProfile::sole()->specialties);
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

    public function test_a_long_document_is_sent_to_the_ai_in_multiple_chunks(): void
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

        // Long enough to span several extraction chunks - a single call
        // would only ever see the opening of the document.
        $file = UploadedFile::fake()->createWithContent('long-manual.txt', str_repeat('قیمت متفاوت است. ', 5000));

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('knowledgeUploadFile', $file)
            ->call('processKnowledgeUpload');

        Http::assertSentCount(3);
        // Every chunk returned the same two notes - deduplicated across chunks, not one row per chunk.
        $this->assertCount(2, AiAssistantKnowledgeEntry::all());
    }

    public function test_uploading_a_scanned_pdf_with_no_text_layer_dispatches_the_ocr_job_instead_of_an_error(): void
    {
        Queue::fake();
        Storage::fake();

        $user = $this->actingAsHubUser();
        $provider = AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $localPath = sys_get_temp_dir().'/scanned-upload-test-'.uniqid().'.pdf';
        $this->buildScannedPdf($localPath, ['Scanned contract page.']);
        $file = UploadedFile::fake()->createWithContent('scanned-contract.pdf', file_get_contents($localPath));
        unlink($localPath);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('knowledgeUploadFile', $file)
            ->call('processKnowledgeUpload');

        Http::assertNothingSent();
        $this->assertSame(0, AiAssistantKnowledgeEntry::count());

        $ocrUpload = AiAssistantOcrUpload::sole();
        $this->assertSame($profile->id, $ocrUpload->ai_assistant_profile_id);
        $this->assertSame('scanned-contract.pdf', $ocrUpload->original_filename);
        $this->assertSame(AiAssistantOcrUploadStatus::Pending, $ocrUpload->status);

        Queue::assertPushed(OcrKnowledgeUploadJob::class, function (OcrKnowledgeUploadJob $job) use ($ocrUpload, $provider, $user) {
            Storage::assertExists($job->storedPath);

            return $job->ocrUploadId === $ocrUpload->id
                && $job->aiProviderId === $provider->id
                && $job->userId === $user->id;
        });
    }

    public function test_the_knowledge_panel_shows_ocr_upload_status_badges(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        AiAssistantOcrUpload::factory()->for($profile, 'profile')->create(['original_filename' => 'in-progress.pdf']);
        AiAssistantOcrUpload::factory()->for($profile, 'profile')->completed(4)->create(['original_filename' => 'done.pdf']);
        AiAssistantOcrUpload::factory()->for($profile, 'profile')->failed('مشکل در خواندن فایل.')->create(['original_filename' => 'broken.pdf']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->assertSee('in-progress.pdf')
            ->assertSee('در حال OCR')
            ->assertSee('done.pdf')
            ->assertSee('4 نکته')
            ->assertSee('broken.pdf')
            ->assertSee('خطا');
    }

    public function test_the_knowledge_panel_shows_no_ocr_section_without_any_upload(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->assertDontSee('وضعیت فایل‌های اسکن‌شده');
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

    public function test_the_consult_assistant_tool_is_not_offered_when_no_other_consultable_profile_exists(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام.']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return $toolNames === ['save_knowledge_entry', 'fetch_url'];
        });
    }

    public function test_a_profile_can_consult_another_consultable_profile_and_weave_the_answer_in(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'description' => 'دستیار فروش']);
        $target = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'description' => 'دستیار حقوقی', 'specialties' => ['حقوقی']]);

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
                                    'name' => 'consult_assistant',
                                    'arguments' => json_encode(['target_profile_id' => $target->id, 'question' => 'آیا این بند قراردادی معتبر است؟']),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'طبق قانون، این بند معتبر است.']]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'طبق مشورت با دستیار حقوقی، این بند معتبر است.']]],
                ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $requester->id)
            ->set('body', 'این بند قراردادی معتبره؟')
            ->call('sendToProfile')
            ->assertSee('پاسخ دستیار «'.$target->displayName().'»')
            ->assertSee('طبق قانون، این بند معتبر است.');

        $consultation = AiAssistantConsultation::sole();
        $this->assertSame($requester->id, $consultation->consulting_ai_assistant_profile_id);
        $this->assertSame($target->id, $consultation->consulted_ai_assistant_profile_id);
        $this->assertSame('طبق قانون، این بند معتبر است.', $consultation->answer);

        $finalMessage = $requester->conversations->sole()->messages()->where('role', AiMessageRole::Assistant)->orderByDesc('id')->first();
        $this->assertSame('طبق مشورت با دستیار حقوقی، این بند معتبر است.', $finalMessage->content);
    }

    public function test_consulting_an_inaccessible_profile_is_rejected_without_any_call_to_it(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $other = User::factory()->create();
        $outOfScope = AiAssistantProfile::factory()->create(['created_by' => $other->id, 'is_shared' => false]);

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
                                    'name' => 'consult_assistant',
                                    'arguments' => json_encode(['target_profile_id' => $outOfScope->id, 'question' => 'سؤال؟']),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'نتوانستم مشورت بگیرم.']]],
                ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $requester->id)
            ->set('body', 'یه چیزی بپرس')
            ->call('sendToProfile');

        $this->assertSame(0, AiAssistantConsultation::count());

        $toolMessage = $requester->conversations->sole()->messages()->where('role', AiMessageRole::Tool)->sole();
        $this->assertStringContainsString('error', $toolMessage->content);
    }

    public function test_consultations_are_capped_per_message_even_within_a_single_round(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $targets = AiAssistantProfile::factory()->count(4)->create(['created_by' => $user->id]);

        $toolCalls = $targets->map(fn (AiAssistantProfile $target, int $index) => [
            'id' => 'call_'.$index,
            'type' => 'function',
            'function' => [
                'name' => 'consult_assistant',
                'arguments' => json_encode(['target_profile_id' => $target->id, 'question' => 'سؤال '.$index]),
            ],
        ])->all();

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls],
                    ]],
                ])
                // One chatCompletion response per consultation actually performed (capped at 3), then the final round.
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ ۱']]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ ۲']]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ ۳']]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'جمع‌بندی نهایی.']]]]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $requester->id)
            ->set('body', 'از همه بپرس')
            ->call('sendToProfile');

        $this->assertSame(3, AiAssistantConsultation::count());

        $toolMessages = $requester->conversations->sole()->messages()->where('role', AiMessageRole::Tool)->get();
        $this->assertTrue($toolMessages->contains(fn ($message) => str_contains($message->content, 'سقف تعداد مشورت')));
    }

    public function test_the_owner_can_update_a_profiles_name_description_avatar_and_visibility(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create([
            'created_by' => $user->id,
            'name' => 'قدیمی',
            'avatar_shape' => 'circle',
            'avatar_color' => 'Amber',
            'is_shared' => false,
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openEditForm', $profile->id)
            ->set('newName', 'جدید')
            ->set('newDescription', 'دستورالعمل تازه برای این دستیار است.')
            ->set('newShape', 'hexagon')
            ->set('newColor', 'Indigo')
            ->set('newIsShared', true)
            ->set('newSpecialties', 'حقوقی, قرارداد')
            ->call('updateProfile');

        $profile->refresh();
        $this->assertSame('جدید', $profile->name);
        $this->assertSame('دستورالعمل تازه برای این دستیار است.', $profile->description);
        $this->assertSame('hexagon', $profile->avatar_shape);
        $this->assertSame('Indigo', $profile->avatar_color);
        $this->assertTrue($profile->is_shared);
        $this->assertSame(['حقوقی', 'قرارداد'], $profile->specialties);
    }

    public function test_a_non_owner_cannot_update_a_shared_profile(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true, 'name' => 'اصلی']);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openEditForm', $profile->id)
            ->set('newName', 'دستکاری‌شده')
            ->set('newDescription', 'توضیحات جایگزین برای این دستیار.')
            ->call('updateProfile');

        $this->assertSame('اصلی', $profile->fresh()->name);
    }

    public function test_duplicating_a_profile_copies_its_configuration_but_not_its_knowledge_or_watched_sources(): void
    {
        $user = $this->actingAsHubUser();
        $source = AiAssistantProfile::factory()->create([
            'created_by' => $user->id,
            'name' => 'اصلی',
            'description' => 'وظیفهٔ این دستیار پاسخ به سوالات فروش است.',
            'specialties' => ['فروش'],
            'is_shared' => true,
            'consultable' => true,
        ]);
        $source->knowledgeEntries()->create(['content' => 'نکتهٔ محرمانه', 'status' => AiKnowledgeEntryStatus::Approved, 'source' => AiKnowledgeEntrySource::Manual]);
        $source->watchedSources()->create(['url' => 'https://example.com']);

        Livewire::test(AiAssistantsHub::class)->call('duplicateProfile', $source->id);

        $clone = AiAssistantProfile::where('id', '!=', $source->id)->sole();
        $this->assertSame('اصلی (کپی)', $clone->name);
        $this->assertSame($source->description, $clone->description);
        $this->assertSame(['فروش'], $clone->specialties);
        $this->assertFalse($clone->is_shared);
        $this->assertSame(0, $clone->knowledgeEntries()->count());
        $this->assertSame(0, $clone->watchedSources()->count());
    }

    public function test_duplicating_someone_elses_shared_profile_is_allowed_and_creates_a_private_copy(): void
    {
        $owner = User::factory()->create();
        $source = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);

        $user = $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)->call('duplicateProfile', $source->id);

        $clone = AiAssistantProfile::where('id', '!=', $source->id)->sole();
        $this->assertSame($user->id, $clone->created_by);
        $this->assertFalse($clone->is_shared);
    }

    public function test_duplicating_a_private_profile_owned_by_someone_else_is_rejected(): void
    {
        $owner = User::factory()->create();
        $source = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => false]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)->call('duplicateProfile', $source->id);

        $this->assertSame(1, AiAssistantProfile::count());
    }

    public function test_the_owner_can_rename_a_conversation(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id, 'title' => 'قدیمی']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('renameConversation', $conversation->id, 'نام تازه');

        $this->assertSame('نام تازه', $conversation->fresh()->title);
    }

    public function test_the_owner_can_delete_a_conversation_and_the_selection_falls_back_to_another_one(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $older = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id, 'title' => 'قدیمی‌تر']);
        $newer = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id, 'title' => 'جدیدتر']);

        $component = Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('selectProfileConversation', $newer->id)
            ->call('deleteConversation', $newer->id);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $newer->id]);
        $this->assertSame($older->id, $component->get('conversationId'));
    }

    public function test_a_user_cannot_delete_another_users_conversation_with_a_shared_profile(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        $ownersConversation = AiConversation::create(['user_id' => $owner->id, 'ai_assistant_profile_id' => $profile->id]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('deleteConversation', $ownersConversation->id);

        $this->assertDatabaseHas('ai_conversations', ['id' => $ownersConversation->id]);
    }

    public function test_the_owner_can_edit_an_approved_knowledge_entrys_content(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $entry = $profile->knowledgeEntries()->create(['content' => 'متن اشتباه', 'status' => AiKnowledgeEntryStatus::Approved, 'source' => AiKnowledgeEntrySource::Manual]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('updateKnowledgeEntry', $entry->id, 'متن درست‌شده');

        $this->assertSame('متن درست‌شده', $entry->fresh()->content);
        $this->assertSame(AiKnowledgeEntryStatus::Approved, $entry->fresh()->status);
    }

    public function test_a_non_owner_cannot_edit_a_shared_profiles_knowledge_entry(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        $entry = $profile->knowledgeEntries()->create(['content' => 'اصلی', 'status' => AiKnowledgeEntryStatus::Approved, 'source' => AiKnowledgeEntrySource::Manual]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('updateKnowledgeEntry', $entry->id, 'دستکاری‌شده');

        $this->assertSame('اصلی', $entry->fresh()->content);
    }

    public function test_toggling_fetch_url_access_flips_the_setting(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'fetch_url_enabled' => true]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleProfileFetchUrl');

        $this->assertFalse($profile->fresh()->fetch_url_enabled);
    }

    public function test_disabling_fetch_url_access_removes_the_tool_from_the_offered_tools(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'fetch_url_enabled' => false]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return $toolNames === ['save_knowledge_entry'];
        });
    }

    public function test_the_chat_shows_a_visible_chip_when_the_assistant_consults_another_profile(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $target = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'دستیار حقوقی', 'specialties' => ['حقوقی']]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'consult_assistant', 'arguments' => json_encode(['target_profile_id' => $target->id, 'question' => 'سؤال؟'])],
                        ]],
                    ],
                ]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ تخصصی.']]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'جمع‌بندی.']]]]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $requester->id)
            ->set('body', 'سؤال؟')
            ->call('sendToProfile')
            ->assertSee('از «دستیار حقوقی» مشورت گرفت');
    }

    public function test_the_chat_shows_a_visible_chip_when_the_assistant_reads_a_web_page(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'fetch_url', 'arguments' => json_encode(['url' => 'http://8.8.8.8/info'])],
                        ]],
                    ],
                ]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'خواندم.']]]]),
            'http://8.8.8.8/info' => Http::response('<html><body>متن صفحه</body></html>'),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'از http://8.8.8.8/info بخون')
            ->call('sendToProfile')
            ->assertSee('8.8.8.8');
    }

    public function test_the_knowledge_panel_shows_consultation_history_for_both_the_asking_and_answering_profile(): void
    {
        $user = $this->actingAsHubUser();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'فروش']);
        $target = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'حقوقی']);

        AiAssistantConsultation::create([
            'consulting_ai_assistant_profile_id' => $requester->id,
            'consulted_ai_assistant_profile_id' => $target->id,
            'question' => 'این قرارداد معتبر است؟',
            'answer' => 'بله.',
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $requester->id)
            ->assertSee('از «حقوقی» پرسید');

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $target->id)
            ->assertSee('«فروش» از این دستیار پرسید');
    }

    private function grantAiAssistantReportPermission(User $user): void
    {
        $user->givePermissionTo(Permission::where('name', 'ai-assistant.report')->orWhere('name', 'like', '%.view_any')->get());
    }

    public function test_the_owner_can_grant_and_revoke_a_crm_tool(): void
    {
        $user = $this->actingAsHubUser();
        $this->grantAiAssistantReportPermission($user);
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleAllowedCrmTool', 'check_room_availability');

        $this->assertSame(['check_room_availability'], $profile->fresh()->allowed_tools);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleAllowedCrmTool', 'check_room_availability');

        $this->assertSame([], $profile->fresh()->allowed_tools);
    }

    public function test_an_unknown_tool_name_cannot_be_granted(): void
    {
        $user = $this->actingAsHubUser();
        $this->grantAiAssistantReportPermission($user);
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleAllowedCrmTool', 'delete_everything');

        $this->assertSame([], $profile->fresh()->allowed_tools ?? []);
    }

    public function test_a_granted_crm_tool_is_only_offered_when_the_viewer_has_the_report_permission(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'allowed_tools' => ['check_room_availability']]);

        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]]])]);

        // Without "ai-assistant.report", the granted tool is withheld.
        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return ! in_array('check_room_availability', $toolNames, true);
        });

        $this->grantAiAssistantReportPermission($user);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('newProfileConversation')
            ->set('body', 'سلام دوباره')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return in_array('check_room_availability', $toolNames, true);
        });
    }

    public function test_the_assistant_can_call_a_granted_crm_tool_and_use_its_result(): void
    {
        $user = $this->actingAsHubUser();
        $this->grantAiAssistantReportPermission($user);
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'allowed_tools' => ['lookup_guest']]);

        Guest::factory()->create(['name' => 'رضا کریمی', 'mobile' => '09120000000']);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'lookup_guest', 'arguments' => json_encode(['name_or_mobile' => 'رضا کریمی'])],
                        ]],
                    ],
                ]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'مشتری رضا کریمی پیدا شد.']]]]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'رضا کریمی رو پیدا کن')
            ->call('sendToProfile');

        $toolMessage = $profile->conversations->sole()->messages()->where('role', AiMessageRole::Tool)->sole();
        $payload = json_decode($toolMessage->content, true);

        $this->assertSame('رضا کریمی', $payload['name']);
    }

    public function test_a_tool_not_granted_to_the_profile_is_not_offered_even_if_in_the_catalog(): void
    {
        $user = $this->actingAsHubUser();
        $this->grantAiAssistantReportPermission($user);
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'allowed_tools' => ['lookup_guest']]);

        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]]])]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('body', 'سلام')
            ->call('sendToProfile');

        Http::assertSent(function ($request) {
            $toolNames = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return in_array('lookup_guest', $toolNames, true) && ! in_array('check_room_availability', $toolNames, true);
        });
    }

    public function test_the_owner_can_add_and_remove_a_scheduled_prompt(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $component = Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('addScheduledPrompt', 'مهمان‌های امروز رو چک کن', '08:30');

        $scheduledPrompt = $profile->scheduledPrompts()->sole();
        $this->assertSame('مهمان‌های امروز رو چک کن', $scheduledPrompt->prompt);
        $this->assertSame('08:30', $scheduledPrompt->run_at_time);
        $this->assertTrue($scheduledPrompt->is_active);

        $component->call('deleteScheduledPrompt', $scheduledPrompt->id);

        $this->assertSame(0, $profile->scheduledPrompts()->count());
    }

    public function test_adding_a_scheduled_prompt_with_an_invalid_time_is_rejected(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('addScheduledPrompt', 'چیزی را چک کن', 'نیمه‌شب');

        $this->assertSame(0, $profile->scheduledPrompts()->count());
    }

    public function test_toggling_a_scheduled_prompt_flips_its_active_state(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $scheduledPrompt = $profile->scheduledPrompts()->create(['prompt' => 'چیزی را چک کن', 'run_at_time' => '08:00']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('toggleScheduledPromptActive', $scheduledPrompt->id);

        $this->assertFalse($scheduledPrompt->fresh()->is_active);
    }

    public function test_a_non_owner_cannot_manage_a_shared_profiles_scheduled_prompts(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('addScheduledPrompt', 'دستکاری', '08:00');

        $this->assertSame(0, $profile->scheduledPrompts()->count());
    }

    public function test_setting_feedback_on_an_assistant_message_persists_it(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $message = $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('setMessageFeedback', $message->id, 'up');

        $this->assertSame('up', $message->fresh()->feedback);
    }

    public function test_setting_the_same_feedback_twice_clears_it(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $message = $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        $component = Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('setMessageFeedback', $message->id, 'down');

        $this->assertSame('down', $message->fresh()->feedback);

        $component->call('setMessageFeedback', $message->id, 'down');

        $this->assertNull($message->fresh()->feedback);
    }

    public function test_feedback_cannot_be_set_on_a_user_message(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $message = $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سلام']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('setMessageFeedback', $message->id, 'up');

        $this->assertNull($message->fresh()->feedback);
    }

    public function test_feedback_cannot_be_set_on_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        $ownersConversation = AiConversation::create(['user_id' => $owner->id, 'ai_assistant_profile_id' => $profile->id]);
        $message = $ownersConversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        $this->actingAsHubUser();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('setMessageFeedback', $message->id, 'up');

        $this->assertNull($message->fresh()->feedback);
    }

    public function test_an_invalid_feedback_value_is_ignored(): void
    {
        $user = $this->actingAsHubUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $message = $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('setMessageFeedback', $message->id, 'sideways');

        $this->assertNull($message->fresh()->feedback);
    }

    public function test_regenerating_the_last_response_replaces_it_with_a_new_one(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'امکانات ویلا چیست؟']);
        $weakReply = $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'نمی‌دانم.']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ویلا سه خوابه با جکوزی و حیاط اختصاصی دارد.']]],
            ]),
        ]);

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('regenerateLastResponse');

        $this->assertModelMissing($weakReply);
        $messages = $conversation->refresh()->messages;
        $this->assertSame(
            [AiMessageRole::User, AiMessageRole::Assistant],
            $messages->pluck('role')->all(),
        );
        $this->assertSame('ویلا سه خوابه با جکوزی و حیاط اختصاصی دارد.', $messages->last()->content);
    }

    public function test_regenerating_is_a_noop_when_the_last_message_is_not_an_assistant_reply(): void
    {
        $user = $this->actingAsHubUser();
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سلام']);

        Http::fake();

        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->call('regenerateLastResponse');

        Http::assertNothingSent();
        $this->assertSame(1, $conversation->refresh()->messages()->count());
    }

    public function test_a_user_cannot_regenerate_a_response_in_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id, 'is_shared' => true]);
        $ownersConversation = AiConversation::create(['user_id' => $owner->id, 'ai_assistant_profile_id' => $profile->id]);
        $ownersConversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        $this->actingAsHubUser();
        Http::fake();

        // conversationId is forged directly (bypassing selectProfileConversation,
        // as a tampered request could) to prove getSelectedProfileConversation()'s
        // own user_id scoping - not just the UI's normal selection path - is what
        // blocks this.
        Livewire::test(AiAssistantsHub::class)
            ->call('openProfile', $profile->id)
            ->set('conversationId', $ownersConversation->id)
            ->call('regenerateLastResponse');

        Http::assertNothingSent();
        $this->assertSame(1, $ownersConversation->refresh()->messages()->count());
    }
}
