<?php

namespace Tests\Feature\Seo;

use App\Filament\Resources\SeoKeywordProfiles\Pages\ManageSeoKeywordProfiles;
use App\Models\SeoKeywordProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoKeywordProfileResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_keyword_profiles(): void
    {
        $profile = SeoKeywordProfile::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordProfiles::class)
            ->assertCanSeeTableRecords([$profile]);
    }
}
