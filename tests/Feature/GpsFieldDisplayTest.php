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
 * A "gps" field used to just print raw "عرض جغرافیایی X — طول جغرافیایی Y"
 * text everywhere its value showed up. It's now a small embedded map
 * (app/../components/gps-map.blade.php) wherever that's possible, and a
 * plain Google Maps link where it isn't (the Word export, which can't run
 * the JS a map needs - same reason the QR code is skipped there).
 */
class GpsFieldDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function submissionWithGpsValue(float $lat, float $lng): FormSubmission
    {
        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create(['submitted_by' => $submitter->id]);
        $field = FormField::factory()->create([
            'form_template_id' => $submission->form_template_id,
            'field_type' => FormFieldType::Gps,
            'label' => 'موقعیت مکانی',
        ]);

        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $field->id,
            'value_text' => null,
            'value_json' => ['lat' => $lat, 'lng' => $lng],
        ]);

        return $submission->fresh(['submitter']);
    }

    public function test_the_print_page_shows_a_map_and_a_google_maps_link_for_a_gps_field(): void
    {
        $submission = $this->submissionWithGpsValue(35.6892, 51.389);

        $this->actingAs($submission->submitter)
            ->get(route('form-submissions.print', $submission))
            ->assertOk()
            ->assertSee('leaflet', escape: false)
            ->assertSee('https://www.google.com/maps?q=35.6892,51.389', escape: false)
            ->assertDontSee('عرض جغرافیایی');
    }

    public function test_the_word_export_shows_a_google_maps_link_for_a_gps_field(): void
    {
        $submission = $this->submissionWithGpsValue(35.6892, 51.389);

        $response = $this->actingAs($submission->submitter)
            ->get(route('form-submissions.export-word', $submission));

        $response->assertOk()
            ->assertSee('https://www.google.com/maps?q=35.6892,51.389', escape: false)
            ->assertSee('مشاهده روی نقشه');
    }

    public function test_the_submission_detail_view_shows_a_map_for_a_gps_field(): void
    {
        $submission = $this->submissionWithGpsValue(35.6892, 51.389);

        $this->actingAs($submission->submitter)
            ->get(route('filament.admin.resources.form-submissions.view', $submission))
            ->assertOk()
            ->assertSee('leaflet', escape: false)
            ->assertSee('https://www.google.com/maps?q=35.6892,51.389', escape: false);
    }
}
