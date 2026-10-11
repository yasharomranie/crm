<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistantUsageDashboard;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiAssistantScheduledPrompt;
use App\Models\AiAssistantWatchedSource;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantUsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsDashboardUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'ناظر آمار دستیاران']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant-usage-dashboard.access')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_it_counts_messages_consultations_feedback_and_automation_per_profile(): void
    {
        $user = $this->actingAsDashboardUser();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $other = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $conversation = AiConversation::create(['user_id' => $user->id, 'ai_assistant_profile_id' => $profile->id]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سؤال']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ', 'feedback' => 'up']);
        $conversation->messages()->create(['role' => AiMessageRole::Tool, 'content' => '{}', 'tool_name' => 'save_knowledge_entry']);

        AiAssistantConsultation::create([
            'consulting_ai_assistant_profile_id' => $profile->id,
            'consulted_ai_assistant_profile_id' => $other->id,
            'question' => 'سؤال؟',
            'answer' => 'پاسخ.',
        ]);

        AiAssistantWatchedSource::factory()->for($profile, 'profile')->create();
        AiAssistantScheduledPrompt::factory()->for($profile, 'profile')->create(['is_active' => true]);
        AiAssistantScheduledPrompt::factory()->for($profile, 'profile')->create(['is_active' => false]);

        $component = Livewire::test(AiAssistantUsageDashboard::class);
        $stats = collect($component->instance()->getProfileUsageStats())->firstWhere(fn ($row) => $row['profile']->id === $profile->id);

        $this->assertSame(2, $stats['message_count']); // user + assistant, not the tool message
        $this->assertSame(1, $stats['conversation_count']);
        $this->assertSame(1, $stats['consultations_asked']);
        $this->assertSame(0, $stats['consultations_answered']);
        $this->assertSame(1, $stats['feedback_up']);
        $this->assertSame(0, $stats['feedback_down']);
        $this->assertSame(1, $stats['watched_sources_count']);
        $this->assertSame(1, $stats['active_scheduled_prompts_count']);
    }

    public function test_it_only_shows_profiles_visible_to_the_current_user(): void
    {
        $user = $this->actingAsDashboardUser();
        AiAssistantProfile::factory()->create(['created_by' => $user->id, 'name' => 'مال من']);

        $other = User::factory()->create();
        AiAssistantProfile::factory()->create(['created_by' => $other->id, 'is_shared' => false, 'name' => 'خصوصی دیگران']);

        $component = Livewire::test(AiAssistantUsageDashboard::class);
        $names = collect($component->instance()->getProfileUsageStats())->map(fn ($row) => $row['profile']->displayName());

        $this->assertTrue($names->contains('مال من'));
        $this->assertFalse($names->contains('خصوصی دیگران'));
    }

    public function test_a_user_without_the_permission_is_denied(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی آمار دستیاران']));

        $this->actingAs($user)
            ->get(route('filament.admin.pages.ai-assistant-usage-dashboard'))
            ->assertForbidden();
    }
}
