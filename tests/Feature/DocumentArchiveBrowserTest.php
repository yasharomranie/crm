<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessLevel;
use App\Filament\Pages\DocumentArchiveBrowser;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentArchiveBrowserTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_folder_shows_its_subfolders_documents_and_breadcrumb(): void
    {
        $company = Company::factory()->create(['name' => 'گنج‌نامه']);
        $companyFolder = DocumentFolder::create(['name' => 'شرکت گنج‌نامه', 'company_id' => $company->id]);
        $formFolder = DocumentFolder::create(['name' => 'درخواست مرخصی', 'parent_id' => $companyFolder->id]);
        $document = Document::factory()->create(['folder_id' => $formFolder->id, 'title' => 'مرخصی - GNJ-FRM-000001']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('openFolder', $formFolder->id);

        $this->assertSame($formFolder->id, $component->get('folderId'));
        $this->assertTrue($component->instance()->documents()->contains($document));
        $this->assertSame([$companyFolder->id, $formFolder->id], $component->instance()->breadcrumbs()->pluck('id')->all());
    }

    public function test_creating_a_folder_nests_it_under_the_current_folder(): void
    {
        $parent = DocumentFolder::create(['name' => 'شرکت گنج‌نامه']);

        Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('openFolder', $parent->id)
            ->callAction('createFolder', data: ['name' => 'فرم بازرسی']);

        $this->assertDatabaseHas('document_folders', ['name' => 'فرم بازرسی', 'parent_id' => $parent->id]);
    }

    public function test_uploading_a_document_stores_it_in_the_current_folder(): void
    {
        Storage::fake();

        $folder = DocumentFolder::create(['name' => 'شرکت گنج‌نامه']);

        Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('openFolder', $folder->id)
            ->callAction('uploadDocument', data: [
                'title' => 'دستورالعمل ایمنی',
                'file_path' => UploadedFile::fake()->create('safety.pdf', 50),
                'access_level' => DocumentAccessLevel::PublicInternal->value,
            ]);

        $document = Document::where('title', 'دستورالعمل ایمنی')->sole();
        $this->assertSame($folder->id, $document->folder_id);
        Storage::disk()->assertExists($document->file_path);
    }

    public function test_dragging_a_document_onto_a_folder_moves_it_there(): void
    {
        $originFolder = DocumentFolder::create(['name' => 'مبدأ']);
        $targetFolder = DocumentFolder::create(['name' => 'مقصد']);
        $document = Document::factory()->create(['folder_id' => $originFolder->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('moveDocument', $document->id, $targetFolder->id);

        $this->assertSame($targetFolder->id, $document->refresh()->folder_id);
    }

    public function test_dragging_a_folder_into_its_own_descendant_is_rejected(): void
    {
        $grandparent = DocumentFolder::create(['name' => 'شرکت']);
        $parent = DocumentFolder::create(['name' => 'فرم‌ها', 'parent_id' => $grandparent->id]);
        $child = DocumentFolder::create(['name' => 'مرخصی', 'parent_id' => $parent->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('moveFolder', $grandparent->id, $child->id);

        $this->assertNull($grandparent->refresh()->parent_id);
    }

    public function test_dragging_a_folder_to_the_root_clears_its_parent(): void
    {
        $parent = DocumentFolder::create(['name' => 'شرکت']);
        $child = DocumentFolder::create(['name' => 'فرم‌ها', 'parent_id' => $parent->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(DocumentArchiveBrowser::class)
            ->call('moveFolder', $child->id, null);

        $this->assertNull($child->refresh()->parent_id);
    }
}
