<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessLevel;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_every_document_regardless_of_access_level(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $document = Document::factory()->restricted()->create();

        $this->assertTrue($document->isVisibleTo($admin));
        $this->assertTrue(Document::query()->visibleTo($admin)->whereKey($document->id)->exists());
    }

    public function test_public_internal_document_is_visible_to_any_user(): void
    {
        $document = Document::factory()->create(['access_level' => DocumentAccessLevel::PublicInternal]);
        $anyUser = User::factory()->create();

        $this->assertTrue($document->isVisibleTo($anyUser));
    }

    public function test_department_only_document_is_only_visible_within_the_same_department(): void
    {
        $sales = Department::factory()->create();
        $support = Department::factory()->create();

        $document = Document::factory()->departmentOnly($sales->id)->create();

        $salesUser = User::factory()->create(['department_id' => $sales->id]);
        $supportUser = User::factory()->create(['department_id' => $support->id]);
        $noDepartmentUser = User::factory()->create(['department_id' => null]);

        $this->assertTrue($document->isVisibleTo($salesUser));
        $this->assertFalse($document->isVisibleTo($supportUser));
        $this->assertFalse($document->isVisibleTo($noDepartmentUser));
    }

    public function test_department_only_document_restricted_to_a_parent_department_is_visible_to_its_child_departments(): void
    {
        $holding = Department::factory()->create(['name' => 'گنج‌نامه']);
        $reception = Department::factory()->create(['name' => 'پذیرش گنج‌نامه', 'parent_id' => $holding->id]);
        $unrelated = Department::factory()->create(['name' => 'زاگرس']);

        $document = Document::factory()->departmentOnly($holding->id)->create();

        $holdingUser = User::factory()->create(['department_id' => $holding->id]);
        $receptionUser = User::factory()->create(['department_id' => $reception->id]);
        $unrelatedUser = User::factory()->create(['department_id' => $unrelated->id]);

        $this->assertTrue($document->isVisibleTo($holdingUser));
        $this->assertTrue($document->isVisibleTo($receptionUser));
        $this->assertFalse($document->isVisibleTo($unrelatedUser));

        $visibleIds = Document::query()->visibleTo($receptionUser)->pluck('id')->all();
        $this->assertContains($document->id, $visibleIds);

        $hiddenIds = Document::query()->visibleTo($unrelatedUser)->pluck('id')->all();
        $this->assertNotContains($document->id, $hiddenIds);
    }

    public function test_department_only_document_restricted_to_a_child_department_is_not_visible_to_the_parent_department(): void
    {
        // Access only ever cascades downward (broad department -> its sub
        // departments) - a document scoped to a small team isn't
        // automatically visible to the whole holding above it.
        $holding = Department::factory()->create(['name' => 'گنج‌نامه']);
        $reception = Department::factory()->create(['name' => 'پذیرش گنج‌نامه', 'parent_id' => $holding->id]);

        $document = Document::factory()->departmentOnly($reception->id)->create();

        $holdingUser = User::factory()->create(['department_id' => $holding->id]);

        $this->assertFalse($document->isVisibleTo($holdingUser));
    }

    public function test_restricted_document_is_only_visible_to_uploader_or_shared_users(): void
    {
        $uploader = User::factory()->create();
        $sharedUser = User::factory()->create();
        $strangerUser = User::factory()->create();

        $document = Document::factory()->restricted()->create(['uploaded_by' => $uploader->id]);
        $document->shares()->create(['user_id' => $sharedUser->id]);

        $this->assertTrue($document->isVisibleTo($uploader));
        $this->assertTrue($document->isVisibleTo($sharedUser));
        $this->assertFalse($document->isVisibleTo($strangerUser));
    }

    public function test_visible_to_scope_matches_is_visible_to_for_a_mixed_set_of_documents(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);

        $visiblePublic = Document::factory()->create(['access_level' => DocumentAccessLevel::PublicInternal]);
        $visibleDepartment = Document::factory()->departmentOnly($department->id)->create();
        $visibleOwn = Document::factory()->restricted()->create(['uploaded_by' => $user->id]);
        $hiddenOtherDepartment = Document::factory()->departmentOnly(Department::factory()->create()->id)->create();
        $hiddenRestricted = Document::factory()->restricted()->create();

        $visibleIds = Document::query()->visibleTo($user)->pluck('id')->all();

        $this->assertContains($visiblePublic->id, $visibleIds);
        $this->assertContains($visibleDepartment->id, $visibleIds);
        $this->assertContains($visibleOwn->id, $visibleIds);
        $this->assertNotContains($hiddenOtherDepartment->id, $visibleIds);
        $this->assertNotContains($hiddenRestricted->id, $visibleIds);
    }

    public function test_replacing_a_files_archives_the_previous_one_as_a_new_version(): void
    {
        $document = Document::factory()->create(['file_path' => 'documents/original.pdf']);
        $uploader = User::factory()->create();

        $document->replaceFile('documents/replacement.pdf', 'application/pdf', 500, $uploader);
        $document->refresh();

        $this->assertSame('documents/replacement.pdf', $document->file_path);
        $this->assertCount(1, $document->versions);
        $this->assertSame('documents/original.pdf', $document->versions->first()->file_path);
        $this->assertSame(1, $document->versions->first()->version_number);
        $this->assertSame(2, $document->nextVersionNumber());
    }

    public function test_download_route_is_forbidden_for_a_user_who_cannot_see_the_document(): void
    {
        Storage::fake('local');

        $document = Document::factory()->restricted()->create(['file_path' => 'documents/secret.pdf']);
        Storage::disk('local')->put($document->file_path, 'secret contents');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_download_route_succeeds_for_a_user_who_can_see_the_document(): void
    {
        Storage::fake('local');

        $document = Document::factory()->create(['file_path' => 'documents/brochure.pdf']);
        Storage::disk('local')->put($document->file_path, 'brochure contents');

        $anyUser = User::factory()->create();

        $this->actingAs($anyUser)
            ->get(route('documents.download', $document))
            ->assertSuccessful();
    }

    /**
     * Regression test: the download used to be named after the document's
     * title alone, with no file extension at all - so every download in
     * the app landed in the browser with a name like "گزارش" instead of
     * "گزارش.pdf", making it look broken/unopenable.
     */
    public function test_download_filename_keeps_the_documents_title_and_the_files_real_extension(): void
    {
        $document = Document::factory()->create([
            'title' => 'گزارش فروش',
            'file_path' => 'documents/some-random-name.pdf',
        ]);

        $this->assertSame('گزارش فروش.pdf', $document->downloadFilename());
    }

    public function test_document_version_download_filename_keeps_its_extension_too(): void
    {
        $document = Document::factory()->create(['title' => 'گزارش فروش']);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'documents/old-version.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size_kb' => 120,
            'uploaded_by' => $document->uploaded_by,
        ]);

        $this->assertSame('گزارش فروش (نسخه 1).docx', $version->downloadFilename());
    }
}
