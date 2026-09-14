<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A "date_jalali" field's answer used to print the raw Gregorian date
 * (e.g. "2026-08-23 00:00:00") on the submission view, print page, and
 * Word export - none of the three had a branch for this field type, so it
 * fell through to the generic {{ $value->value }} output. Now formatted
 * with ->toJalali() everywhere, matching the rest of the app's dates.
 */
class FormDateJalaliDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function submissionWithJalaliDateValue(): FormSubmission
    {
        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create(['submitted_by' => $submitter->id]);
        $field = FormField::factory()->create([
            'form_template_id' => $submission->form_template_id,
            'field_type' => FormFieldType::DateJalali,
            'label' => 'تاریخ بازدید',
        ]);

        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_text' => null,
            'value_date' => '2026-08-23',
        ]);

        return $submission->fresh(['submitter']);
    }

    public function test_the_submission_detail_view_shows_the_date_in_jalali_format(): void
    {
        $submission = $this->submissionWithJalaliDateValue();

        $this->actingAs($submission->submitter)
            ->get(route('filament.admin.resources.form-submissions.view', $submission))
            ->assertOk()
            ->assertSee('۱۴۰۵/۰۶/۰۱')
            ->assertDontSee('2026-08-23');
    }

    public function test_the_print_page_shows_the_date_in_jalali_format(): void
    {
        $submission = $this->submissionWithJalaliDateValue();

        $this->actingAs($submission->submitter)
            ->get(route('form-submissions.print', $submission))
            ->assertOk()
            ->assertSee('۱۴۰۵/۰۶/۰۱')
            ->assertDontSee('2026-08-23');
    }

    public function test_the_word_export_shows_the_date_in_jalali_format(): void
    {
        $submission = $this->submissionWithJalaliDateValue();

        $this->actingAs($submission->submitter)
            ->get(route('form-submissions.export-word', $submission))
            ->assertOk()
            ->assertSee('۱۴۰۵/۰۶/۰۱')
            ->assertDontSee('2026-08-23');
    }
}
