<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Filament\Resources\SeoKeywordSuggestions\Pages\ManageSeoKeywordSuggestions;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_the_export_action_downloads_a_csv_of_the_filtered_suggestions(): void
    {
        $project = SeoProject::factory()->create(['name' => 'ویلا گنجنامه']);
        $included = SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'اجاره ویلا همدان',
            'status' => SeoKeywordSuggestionStatus::New,
        ]);
        $excluded = SeoKeywordSuggestion::factory()->create([
            'status' => SeoKeywordSuggestionStatus::Approved,
            'keyword' => 'کلمه‌ی خارج از فیلتر',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->filterTable('status', SeoKeywordSuggestionStatus::New->value)
            ->callAction('exportKeywordSuggestions')
            ->assertFileDownloaded('پیشنهادهای-کلمات-کلیدی.csv');

        $csv = base64_decode($component->effects['download']['content']);

        $this->assertStringContainsString($included->keyword, $csv);
        $this->assertStringNotContainsString($excluded->keyword, $csv);
    }

    public function test_the_discover_keywords_header_action_dispatches_the_job_for_the_chosen_project(): void
    {
        Queue::fake();

        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->callAction('discoverKeywords', data: [
                'seo_project_id' => $project->id,
                'count' => 10,
            ]);

        Queue::assertPushed(DiscoverSeoKeywordsJob::class, fn (DiscoverSeoKeywordsJob $job) => $job->seoProjectId === $project->id && $job->count === 10);
    }

    public function test_the_discover_keywords_action_warns_when_the_chosen_projects_real_services_is_empty(): void
    {
        Queue::fake();
        $project = SeoProject::factory()->create(['real_services' => null]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->callAction('discoverKeywords', data: [
                'seo_project_id' => $project->id,
                'count' => 10,
            ])
            ->assertNotified('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.');
    }

    public function test_the_discover_keywords_action_does_not_warn_when_real_services_is_filled(): void
    {
        Queue::fake();
        $project = SeoProject::factory()->create(['real_services' => 'داریم: تله‌کابین']);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoKeywordSuggestions::class)
            ->callAction('discoverKeywords', data: [
                'seo_project_id' => $project->id,
                'count' => 10,
            ])
            ->assertNotNotified('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.');
    }
}
