<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Filament\Resources\FormSubmissions\Pages\CreateFormSubmission;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The FormFieldType::Table field type: a repeatable, multi-column table
 * row (rendered as an actual spreadsheet-like grid via Filament's Repeater
 * ->table()) - the one structural piece a flat "one row per field" form
 * builder couldn't represent before, needed to reproduce a physical paper
 * form's own repeating tables (e.g. a multi-row rent-arrears grid) instead
 * of faking it with dozens of individually-numbered flat fields.
 */
class FormTableFieldTest extends TestCase
{
    use RefreshDatabase;

    private function tableField(FormTemplate $template): FormField
    {
        return FormField::factory()->table()->create([
            'form_template_id' => $template->id,
            'field_key' => 'units',
            'label' => 'واحدهای معوق',
        ]);
    }

    public function test_submitted_rows_are_stored_as_a_plain_indexed_array(): void
    {
        $template = FormTemplate::factory()->create(['is_active' => true]);
        $field = $this->tableField($template);

        $this->actingAs(User::factory()->create());

        Livewire::test(CreateFormSubmission::class)
            ->set('data.form_template_id', $template->id)
            ->set('data.units', [
                ['unit_name' => 'واحد ۱', 'amount' => '1500000'],
                ['unit_name' => 'واحد ۲', 'amount' => '2300000'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $value = FormSubmission::sole()->values()->where('form_field_id', $field->id)->sole();

        $this->assertSame([
            ['unit_name' => 'واحد ۱', 'amount' => 1500000],
            ['unit_name' => 'واحد ۲', 'amount' => 2300000],
        ], $value->value);
    }

    public function test_a_table_field_with_no_rows_added_is_not_saved(): void
    {
        $template = FormTemplate::factory()->create(['is_active' => true]);
        $field = $this->tableField($template);

        $this->actingAs(User::factory()->create());

        Livewire::test(CreateFormSubmission::class)
            ->set('data.form_template_id', $template->id)
            ->set('data.units', [])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, FormSubmission::sole()->values()->where('form_field_id', $field->id)->count());
    }

    public function test_merged_document_renders_the_rows_as_an_html_table(): void
    {
        $template = FormTemplate::factory()->create([
            'document_template' => '<p>فهرست معوقات: {{units}}</p>',
        ]);
        $field = $this->tableField($template);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_json' => [
                ['unit_name' => 'واحد ۱', 'amount' => '1500000'],
                ['unit_name' => 'واحد ۲', 'amount' => '2300000'],
            ],
        ]);

        $body = $submission->mergedDocumentBody();

        $this->assertStringContainsString('<table', $body);
        $this->assertStringContainsString('نام واحد', $body);
        $this->assertStringContainsString('مبلغ (ریال)', $body);
        $this->assertStringContainsString('واحد ۱', $body);
        $this->assertStringContainsString('1500000', $body);
        $this->assertStringContainsString('واحد ۲', $body);
    }

    public function test_an_empty_table_field_falls_back_to_a_blank_line_in_the_merged_document(): void
    {
        $template = FormTemplate::factory()->create(['document_template' => 'فهرست معوقات: {{units}}']);
        $this->tableField($template);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->assertStringContainsString('فهرست معوقات: ..........', $submission->mergedDocumentBody());
    }

    public function test_the_print_route_shows_the_rows_as_a_table_when_the_template_has_no_document_text(): void
    {
        $viewer = User::factory()->create();
        $template = FormTemplate::factory()->create(['document_template' => null]);
        $field = $this->tableField($template);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $viewer->id,
        ]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_json' => [
                ['unit_name' => 'واحد ۱', 'amount' => '1500000'],
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('form-submissions.print', $submission))
            ->assertOk()
            ->assertSee('<table class="value-table">', false)
            ->assertSee('نام واحد')
            ->assertSee('واحد ۱')
            ->assertSee('1500000');
    }

    public function test_a_table_field_dehydrates_using_the_table_field_type(): void
    {
        $field = FormField::factory()->table()->make();

        $this->assertSame(FormFieldType::Table, $field->field_type);
        $this->assertTrue($field->field_type->storesAsJson());
    }
}
