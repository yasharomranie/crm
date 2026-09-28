<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Filament\Resources\SeoKeywordSuggestions\Pages\ManageSeoKeywordSuggestions;
use App\Models\SeoKeywordSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoKeywordSuggestionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_suggestions(): void
    {
        $suggestion = SeoKeywordSuggestion::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->assertCanSeeTableRecords([$suggestion]);
    }

    public function test_approving_a_suggestion_updates_its_status(): void
    {
        $suggestion = SeoKeywordSuggestion::factory()->create(['status' => SeoKeywordSuggestionStatus::New]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->callTableAction('approve', $suggestion);

        $this->assertSame(SeoKeywordSuggestionStatus::Approved, $suggestion->fresh()->status);
    }

    public function test_rejecting_a_suggestion_updates_its_status(): void
    {
        $suggestion = SeoKeywordSuggestion::factory()->create(['status' => SeoKeywordSuggestionStatus::New]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->callTableAction('reject', $suggestion);

        $this->assertSame(SeoKeywordSuggestionStatus::Rejected, $suggestion->fresh()->status);
    }
}
