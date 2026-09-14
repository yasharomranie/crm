<?php

namespace Tests\Feature;

use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for a real production incident: an admin deleted a form
 * template's fields (planning to recreate them) and every previously
 * submitted answer for that form silently, permanently disappeared. The
 * cause is form_submission_values.form_field_id being cascadeOnDelete() -
 * deleting a FormField deletes every FormSubmissionValue that references
 * it, across every past submission, with no way back.
 *
 * FieldsRelationManager now warns with the exact count before letting an
 * admin delete a field (see FormField::values()) - these tests pin down
 * the two facts that warning depends on: the count is accurate, and the
 * cascade is exactly as destructive as the warning says.
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

    public function test_deleting_a_field_permanently_deletes_every_submitted_answer_for_it(): void
    {
        $field = FormField::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $field->form_template_id]);
        $value = FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
        ]);

        $field->delete();

        $this->assertModelMissing($value);
        $this->assertModelExists($submission);
    }

    public function test_deleting_a_field_with_no_submitted_answers_is_a_no_op_for_other_data(): void
    {
        $field = FormField::factory()->create();

        $field->delete();

        $this->assertModelMissing($field);
    }
}
