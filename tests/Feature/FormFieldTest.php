<?php

namespace Tests\Feature;

use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for a real production incident: an admin deleted a form
 * template's fields (planning to recreate them) and every previously
 * submitted answer for that form silently, permanently disappeared. The
 * cause was form_submission_values.form_field_id being cascadeOnDelete() -
 * deleting a FormField deleted every FormSubmissionValue that referenced
 * it, across every past submission, with no way back.
 *
 * FormField now soft-deletes instead: removing a field from the form
 * builder no longer touches its submitted answers at all, and historical
 * views load fields ->withTrashed() so old submissions keep displaying
 * correctly (see form-submission-detail.blade.php, print.blade.php,
 * word.blade.php). FieldsRelationManager also warns with the real answer
 * count before a delete, so an admin sees the (now much safer) consequence
 * up front.
 */
class FormFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_values_relation_counts_every_submitted_answer_for_the_field(): void
    {
        $field = FormField::factory()->create();
        FormSubmissionValue::factory()->count(3)->create(['form_field_id' => $field->id]);
        FormSubmissionValue::factory()->create(); // a different field entirely

        $this->assertSame(3, $field->values()->count());
    }

    public function test_deleting_a_field_soft_deletes_it_and_keeps_every_submitted_answer_intact(): void
    {
        $field = FormField::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $field->form_template_id]);
        $value = FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
        ]);

        $field->delete();

        $this->assertSoftDeleted($field);
        $this->assertModelExists($value);
        $this->assertModelExists($submission);
        $this->assertNull(FormField::find($field->id));
        $this->assertNotNull(FormField::withTrashed()->find($field->id));
    }

    public function test_a_soft_deleted_field_no_longer_appears_in_the_active_fields_relation(): void
    {
        $field = FormField::factory()->create();
        $field->delete();

        $template = $field->formTemplate;

        $this->assertCount(0, $template->fields);
        $this->assertCount(1, $template->fields()->withTrashed()->get());
    }

    public function test_the_print_route_still_shows_a_submissions_answer_after_its_field_is_deleted(): void
    {
        $viewer = User::factory()->create();
        $field = FormField::factory()->create(['label' => 'توضیحات بازدید']);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $field->form_template_id,
            'submitted_by' => $viewer->id,
        ]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_text' => 'همه‌چیز مرتب بود',
        ]);

        $field->delete();

        $this->actingAs($viewer)
            ->get(route('form-submissions.print', $submission))
            ->assertOk()
            ->assertSee('توضیحات بازدید')
            ->assertSee('همه‌چیز مرتب بود');
    }
}
