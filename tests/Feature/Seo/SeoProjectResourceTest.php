<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoProjectGoal;
use App\Filament\Resources\SeoProjects\Pages\CreateSeoProject;
use App\Filament\Resources\SeoProjects\Pages\ListSeoProjects;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_seo_projects(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoProjects::class)
            ->assertCanSeeTableRecords([$project]);
    }

    public function test_a_project_can_be_created_with_competitors(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSeoProject::class)
            ->fillForm([
                'name' => 'گنجنامه - سایت اصلی',
                'website_url' => 'https://ganjnameh.example.com',
                'goal' => SeoProjectGoal::Booking->value,
                'market' => 'ایران',
                'competitors' => [
                    ['domain' => 'competitor-one.example.com'],
                    ['domain' => 'competitor-two.example.com'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = SeoProject::where('name', 'گنجنامه - سایت اصلی')->firstOrFail();

        $this->assertSame(SeoProjectGoal::Booking, $project->goal);
        $this->assertCount(2, $project->competitors);
    }
}
