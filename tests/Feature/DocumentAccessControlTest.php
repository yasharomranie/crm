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
}
