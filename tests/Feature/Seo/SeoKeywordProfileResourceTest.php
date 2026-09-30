<?php

namespace Tests\Feature\Seo;

use App\Filament\Resources\SeoKeywordProfiles\Pages\ManageSeoKeywordProfiles;
use App\Models\SeoGoogleConnection;
use App\Models\SeoKeywordProfile;
use App\Models\SeoUrlInspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_the_inspect_indexing_action_is_hidden_without_a_landing_page(): void
    {
        $profile = SeoKeywordProfile::factory()->create(['landing_page' => null]);
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $profile->seo_project_id,
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordProfiles::class)
            ->assertTableActionHidden('inspectIndexing', $profile);
    }

    public function test_the_inspect_indexing_action_is_hidden_without_a_google_connection(): void
    {
        $profile = SeoKeywordProfile::factory()->create(['landing_page' => 'https://ganjnameh.example.com/telecabin']);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordProfiles::class)
            ->assertTableActionHidden('inspectIndexing', $profile);
    }

    public function test_the_inspect_indexing_action_shows_the_indexing_result(): void
    {
        $profile = SeoKeywordProfile::factory()->create(['landing_page' => 'https://ganjnameh.example.com/telecabin']);
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $profile->seo_project_id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);

        Http::fake([
            'searchconsole.googleapis.com/*' => Http::response([
                'inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS']],
            ]),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordProfiles::class)
            ->assertTableActionVisible('inspectIndexing', $profile)
            ->mountTableAction('inspectIndexing', $profile);

        $this->assertDatabaseHas(SeoUrlInspection::class, [
            'seo_project_id' => $profile->seo_project_id,
            'verdict' => 'PASS',
        ]);
    }
}
