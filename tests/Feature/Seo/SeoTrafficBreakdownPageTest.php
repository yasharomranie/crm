<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoTrafficBreakdown;
use App\Models\SeoCountryMetric;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoTrafficBreakdownPageTest extends TestCase
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
            ->test(SeoTrafficBreakdown::class)
            ->assertSee('برای دیدن ترکیب ترافیک، ابتدا یک پروژه انتخاب کن.');
    }

    public function test_it_lists_the_top_country_for_the_selected_project(): void
    {
        $project = SeoProject::factory()->create();
        SeoCountryMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now(), 'country' => 'irn', 'clicks' => 42]);

        Livewire::actingAs($this->admin())
            ->test(SeoTrafficBreakdown::class, ['projectId' => $project->id])
            ->assertSee('IRN')
            ->assertSee('42');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی ترکیب ترافیک']));

        Livewire::actingAs($user)
            ->test(SeoTrafficBreakdown::class)
            ->assertForbidden();
    }
}
