<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test for the "متن سند" (document template): a form template can
 * carry fixed boilerplate text with {{field_key}} placeholders, which a
 * submission merges its real answers into - turning the generic label/
 * value list into a document that reads like the physical paper form
 * (a multi-clause contract-assignment form, in the request that asked
 * for this).
 */
class FormDocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_merged_document_body_is_null_when_the_template_has_no_document_text(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => null]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->assertNull($submission->mergedDocumentBody());
    }

    public function test_merged_document_body_substitutes_a_text_placeholder_with_the_submitted_value(): void
    {
        $template = FormTemplate::factory()->create([
            'document_template' => '<p>مشخصات پیمانکار: نام آقای {{contractor_name}} به شماره ملی {{national_id}}</p>',
        ]);
        $nameField = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'contractor_name']);
        $idField = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'national_id']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        FormSubmissionValue::factory()->create(['form_submission_id' => $submission->id, 'form_field_id' => $nameField->id, 'value_text' => 'یاشار عمرانی']);
        FormSubmissionValue::factory()->create(['form_submission_id' => $submission->id, 'form_field_id' => $idField->id, 'value_text' => '1234567890']);

        $body = $submission->mergedDocumentBody();

        $this->assertStringContainsString('نام آقای یاشار عمرانی به شماره ملی 1234567890', $body);
    }

    public function test_a_checkbox_placeholder_renders_as_yes_or_no(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => 'حمل مصالح به عهدهٔ پیمانکار: {{carrier}}']);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'carrier',
            'field_type' => FormFieldType::Checkbox,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_json' => true,
        ]);

        $this->assertStringContainsString('حمل مصالح به عهدهٔ پیمانکار: بله', $submission->mergedDocumentBody());
    }

    public function test_an_unanswered_placeholder_falls_back_to_a_blank_line(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => 'نشانی: {{address}}']);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'address']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->assertStringContainsString('نشانی: ..........', $submission->mergedDocumentBody());
    }

    public function test_a_placeholder_with_no_matching_field_is_flagged_instead_of_silently_disappearing(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => 'مبلغ: {{typo_amount}}']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->assertStringContainsString('[کلید فیلد نامعتبر: typo_amount]', $submission->mergedDocumentBody());
    }

    public function test_a_field_removed_after_submission_still_resolves_in_the_merged_document(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => 'نام: {{name}}']);
        $field = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'name']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        FormSubmissionValue::factory()->create(['form_submission_id' => $submission->id, 'form_field_id' => $field->id, 'value_text' => 'علی رضایی']);

        $field->delete(); // soft-delete, same as removing it from the form builder

        $this->assertStringContainsString('نام: علی رضایی', $submission->mergedDocumentBody());
    }

    public function test_a_native_merge_tag_chip_from_the_rich_editor_is_resolved_the_same_as_plain_placeholder_text(): void
    {
        // What FormTemplateForm's RichEditor::mergeTags() actually saves
        // when someone types "{{" and picks a field from the dropdown,
        // or drags it in from the "تگ‌های ادغام" toolbar button - not the
        // plain {{field_key}} text an admin might type by hand instead.
        $template = FormTemplate::factory()->create([
            'document_template' => '<p>نام آقای <span data-type="mergeTag" data-id="contractor_name">نام پیمانکار</span> امضا می‌کند.</p>',
        ]);
        $field = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'contractor_name']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_text' => 'یاشار عمرانی',
        ]);

        $body = $submission->mergedDocumentBody();

        $this->assertStringContainsString('نام آقای یاشار عمرانی امضا می‌کند.', $body);
        $this->assertStringNotContainsString('data-type="mergeTag"', $body);
    }

    public function test_the_print_route_shows_the_merged_document_instead_of_the_field_list_when_a_template_exists(): void
    {
        $viewer = User::factory()->create();
        $template = FormTemplate::factory()->create(['document_template' => '<p>موضوع پیمان: {{subject}}</p>']);
        $field = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'subject', 'label' => 'موضوع']);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $viewer->id,
        ]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_text' => 'نصب تجهیزات',
        ]);

        $response = $this->actingAs($viewer)->get(route('form-submissions.print', $submission));

        $response->assertOk()
            ->assertSee('موضوع پیمان: نصب تجهیزات', false)
            ->assertDontSee('<dl class="fields">', false);
    }
}
