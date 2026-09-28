<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoContentItemStatus;
use App\Filament\Resources\SeoContentItems\Pages\CreateSeoContentItem;
use App\Filament\Resources\SeoContentItems\Pages\EditSeoContentItem;
use App\Filament\Resources\SeoContentItems\Pages\ListSeoContentItems;
use App\Models\SeoContentItem;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoContentItemResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_content_items(): void
    {
        $item = SeoContentItem::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoContentItems::class)
            ->assertCanSeeTableRecords([$item]);
    }

    public function test_creating_a_content_item(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CreateSeoContentItem::class)
            ->fillForm([
                'seo_project_id' => $project->id,
                'title' => 'مقاله راهنمای تله کابین',
                'target_keyword' => 'تله کابین گنجنامه قیمت',
                'content_type' => 'مقاله وبلاگ',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = SeoContentItem::firstOrFail();
        $this->assertSame($project->id, $item->seo_project_id);
        $this->assertSame('مقاله راهنمای تله کابین', $item->title);
        $this->assertSame(SeoContentItemStatus::Idea, $item->status);
    }

    public function test_editing_a_content_item(): void
    {
        $item = SeoContentItem::factory()->create(['title' => 'قدیمی']);

        Livewire::actingAs($this->admin())
            ->test(EditSeoContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => 'جدید'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('جدید', $item->fresh()->title);
    }

    public function test_starting_to_write_moves_status_from_idea_to_writing(): void
    {
        $item = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Idea]);

        Livewire::actingAs($this->admin())
            ->test(ListSeoContentItems::class)
            ->callTableAction('startWriting', $item);

        $this->assertSame(SeoContentItemStatus::Writing, $item->fresh()->status);
    }

    public function test_publishing_a_content_item_records_the_url_and_timestamp(): void
    {
        $item = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Writing]);

        Livewire::actingAs($this->admin())
            ->test(ListSeoContentItems::class)
            ->callTableAction('publish', $item, data: [
                'published_url' => 'https://ganjnameh.example.com/telecabin-guide',
            ]);

        $fresh = $item->fresh();
        $this->assertSame(SeoContentItemStatus::Published, $fresh->status);
        $this->assertSame('https://ganjnameh.example.com/telecabin-guide', $fresh->published_url);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_the_publish_action_is_not_available_for_an_already_published_item(): void
    {
        $item = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(ListSeoContentItems::class)
            ->assertTableActionHidden('publish', $item);
    }

    public function test_the_performance_action_is_only_available_for_published_items(): void
    {
        $idea = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Idea]);
        $published = SeoContentItem::factory()->create([
            'status' => SeoContentItemStatus::Published,
            'published_url' => 'https://ganjnameh.example.com/guide',
            'published_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListSeoContentItems::class)
            ->assertTableActionHidden('viewPerformance', $idea)
            ->assertTableActionVisible('viewPerformance', $published);
    }
}
