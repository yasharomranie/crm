<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Filament\Resources\SeoKeywordSuggestions\Pages\ManageSeoKeywordSuggestions;
use App\Models\SeoContentItem;
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

    public function test_creating_a_content_item_from_an_approved_suggestion_links_it(): void
    {
        $suggestion = SeoKeywordSuggestion::factory()->create([
            'status' => SeoKeywordSuggestionStatus::Approved,
            'keyword' => 'اجاره ویلا نزدیک گنجنامه',
            'suggested_content_type' => 'صفحه فرود',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->filterTable('status', SeoKeywordSuggestionStatus::Approved->value)
            ->callTableAction('createContentItem', $suggestion, data: [
                'title' => 'صفحه فرود اجاره ویلا',
            ]);

        $item = SeoContentItem::firstOrFail();
        $this->assertSame($suggestion->id, $item->keyword_suggestion_id);
        $this->assertSame($suggestion->seo_project_id, $item->seo_project_id);
        $this->assertSame('اجاره ویلا نزدیک گنجنامه', $item->target_keyword);
        $this->assertSame('صفحه فرود', $item->content_type);
    }

    public function test_the_create_content_item_action_is_not_available_for_a_new_suggestion(): void
    {
        $suggestion = SeoKeywordSuggestion::factory()->create(['status' => SeoKeywordSuggestionStatus::New]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->assertTableActionHidden('createContentItem', $suggestion);
    }
}
