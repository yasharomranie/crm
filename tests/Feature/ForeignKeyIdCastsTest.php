<?php

namespace Tests\Feature;

use App\Http\Controllers\FormSubmissionFileController;
use App\Models\Document;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for a bug confirmed live in production: some MySQL/PDO
 * driver configurations hand foreign key columns back as PHP strings when a
 * model doesn't explicitly cast them, which silently breaks any strict
 * ===/!== comparison against another model's (always-int) id - e.g.
 * FormSubmissionFileController incorrectly 404ing a real, correctly stored
 * file because "11" === 11 is false.
 *
 * This sandbox's own PDO configuration always returns proper ints, so these
 * tests force the string shape with setRawAttributes() (exactly how
 * Eloquent hydrates a row fetched from the database) instead of relying on
 * a driver quirk that can't be reproduced here.
 */
class ForeignKeyIdCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_submission_value_casts_its_foreign_keys_to_integers_even_when_hydrated_as_strings(): void
    {
        $value = (new FormSubmissionValue)->setRawAttributes([
            'id' => '5',
            'form_submission_id' => '11',
            'form_field_id' => '3',
        ], true);

        $this->assertSame(11, $value->form_submission_id);
        $this->assertSame(3, $value->form_field_id);
    }

    public function test_a_submitter_can_see_their_own_submission_even_when_submitted_by_comes_back_as_a_string(): void
    {
        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create(['submitted_by' => $submitter->id]);

        $submission->setRawAttributes(array_merge(
            $submission->getAttributes(),
            ['submitted_by' => (string) $submitter->id],
        ), true);

        $this->assertSame($submitter->id, $submission->submitted_by);
        $this->assertTrue($submission->isVisibleTo($submitter));
    }

    public function test_a_restricted_documents_uploader_can_see_it_even_when_uploaded_by_comes_back_as_a_string(): void
    {
        $uploader = User::factory()->create();
        $document = Document::factory()->restricted()->create(['uploaded_by' => $uploader->id]);

        $document->setRawAttributes(array_merge(
            $document->getAttributes(),
            ['uploaded_by' => (string) $uploader->id],
        ), true);

        $this->assertSame($uploader->id, $document->uploaded_by);
        $this->assertTrue($document->isVisibleTo($uploader));
    }

    public function test_the_file_download_controller_serves_the_file_even_when_form_submission_id_comes_back_as_a_string(): void
    {
        Storage::fake();

        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create(['submitted_by' => $submitter->id]);
        $value = FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'file_path' => 'form-submissions/attachment.jpg',
        ]);
        Storage::disk()->put($value->file_path, 'fake image bytes');

        // Reproduces the exact production shape: the value's own foreign
        // key comes back as a string, while the submission's id is a
        // normal int - this is what silently 404d a real file.
        $value->setRawAttributes(array_merge(
            $value->getAttributes(),
            ['form_submission_id' => (string) $submission->id],
        ), true);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $submitter);

        $response = (new FormSubmissionFileController)->show($request, $submission, $value);

        $this->assertSame(200, $response->getStatusCode());
    }
}
