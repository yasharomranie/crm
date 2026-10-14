<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoOpportunityPriority;
use App\Filament\Pages\SeoActionQueue;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoActionQueuePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_it_shows_a_prompt_when_no_project_is_selected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SeoActionQueue::class)
            ->assertSee('برای دیدن صف اقدام، ابتدا یک پروژه انتخاب کن.');
    }

    public function test_it_shows_an_empty_message_when_the_project_has_no_findings(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SeoActionQueue::class, ['projectId' => $project->id])
            ->assertSee('در حال حاضر هیچ فرصت، مشکل باز یا پیشنهاد در انتظار تصمیم برای این پروژه نیست.');
    }

    public function test_it_lists_an_opportunity_for_the_selected_project(): void
    {
        $project = SeoProject::factory()->create();
        SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'title' => 'بهبود عنوان صفحه‌ی تله‌کابین',
            'priority' => SeoOpportunityPriority::High,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoActionQueue::class, ['projectId' => $project->id])
            ->assertSee('بهبود عنوان صفحه‌ی تله‌کابین')
            ->assertSee('فرصت سئو');
    }

    public function test_it_lists_a_pending_keyword_suggestion_for_the_selected_project(): void
    {
        $project = SeoProject::factory()->create();
        SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'اجاره ویلا نزدیک گنجنامه',
            'status' => SeoKeywordSuggestionStatus::New,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoActionQueue::class, ['projectId' => $project->id])
            ->assertSee('اجاره ویلا نزدیک گنجنامه')
            ->assertSee('پیشنهاد کلمه کلیدی');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی صف اقدام']));

        Livewire::actingAs($user)
            ->test(SeoActionQueue::class)
            ->assertForbidden();
    }
}
