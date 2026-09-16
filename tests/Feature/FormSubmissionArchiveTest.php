<?php

namespace Tests\Feature;

use App\Enums\FormSubmissionStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormSubmissionArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_submission_archives_it_into_a_document_under_its_company_and_form_template_folders(): void
    {
        Storage::fake();

        $company = Company::factory()->create(['name' => 'گنج‌نامه']);
        $department = Department::factory()->create(['company_id' => $company->id]);
        $template = FormTemplate::factory()->create(['title' => 'درخواست مرخصی', 'department_id' => $department->id]);
        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'department_id' => $department->id,
            'submitted_by' => $submitter->id,
            'status' => FormSubmissionStatus::Draft,
        ]);

        $submission->update(['status' => FormSubmissionStatus::Approved]);

        $document = $submission->refresh()->document;
        $this->assertNotNull($document);
        $this->assertSame($submitter->id, $document->uploaded_by);
        $this->assertSame($department->id, $document->department_id);
        Storage::disk()->assertExists($document->file_path);

        $formFolder = $document->folder;
        $this->assertSame('درخواست مرخصی', $formFolder->name);

        $companyFolder = $formFolder->parent;
        $this->assertSame('شرکت گنج‌نامه', $companyFolder->name);
        $this->assertSame($company->id, $companyFolder->company_id);
    }

    public function test_a_submission_with_no_company_archives_under_the_shared_fallback_folder(): void
    {
        Storage::fake();

        $department = Department::factory()->create(['company_id' => null]);
        $template = FormTemplate::factory()->create(['department_id' => $department->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'department_id' => $department->id,
            'status' => FormSubmissionStatus::Draft,
        ]);

        $submission->update(['status' => FormSubmissionStatus::Approved]);

        $companyFolder = $submission->refresh()->document->folder->parent;
        $this->assertSame('بدون شرکت مشخص', $companyFolder->name);
        $this->assertNull($companyFolder->company_id);
    }

    public function test_rejecting_a_submission_does_not_archive_it(): void
    {
        $submission = FormSubmission::factory()->create(['status' => FormSubmissionStatus::Draft]);

        $submission->update(['status' => FormSubmissionStatus::Rejected]);

        $this->assertNull($submission->refresh()->document);
        $this->assertSame(0, Document::count());
    }

    public function test_approving_twice_does_not_duplicate_the_archived_document(): void
    {
        Storage::fake();

        $submission = FormSubmission::factory()->create(['status' => FormSubmissionStatus::Draft]);
        $submission->update(['status' => FormSubmissionStatus::Approved]);
        $firstDocumentId = $submission->refresh()->document->id;

        // Re-saving with the same status still fires an 'updated' event,
        // but wasChanged('status') should be false the second time -
        // archive() is also independently idempotent as a second line of
        // defence (see FormSubmission::archive()'s existing-document check).
        $submission->update(['status' => FormSubmissionStatus::Approved]);

        $this->assertSame(1, Document::count());
        $this->assertSame($firstDocumentId, $submission->refresh()->document->id);
    }

    public function test_reusing_the_same_form_template_creates_only_one_folder_per_company_and_template(): void
    {
        Storage::fake();

        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $template = FormTemplate::factory()->create(['department_id' => $department->id]);

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'department_id' => $department->id,
            'status' => FormSubmissionStatus::Draft,
        ])->update(['status' => FormSubmissionStatus::Approved]);

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'department_id' => $department->id,
            'status' => FormSubmissionStatus::Draft,
        ])->update(['status' => FormSubmissionStatus::Approved]);

        $this->assertSame(2, Document::count());
        $this->assertSame(1, DocumentFolder::where('company_id', $company->id)->count());
        $this->assertSame(1, DocumentFolder::where('name', $template->title)->count());
    }
}
