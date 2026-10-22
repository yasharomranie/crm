<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Support\StaticMapThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormSubmissionMediaPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPng(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    public function test_gps_field_embeds_a_static_map_thumbnail_when_tiles_are_reachable(): void
    {
        Storage::fake();
        Http::fake([
            'https://tile.openstreetmap.org/*' => Http::response($this->tinyPng(), 200),
        ]);

        $template = FormTemplate::factory()->create([
            'document_template' => '<p><span data-type="mergeTag" data-id="location">موقعیت</span></p>',
        ]);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'location',
            'field_type' => FormFieldType::Gps,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value_json' => ['lat' => 35.715298, 'lng' => 51.404343],
        ]);

        $merged = $submission->load('values.formField')->mergedDocumentBody();

        $this->assertStringContainsString('<img src="data:image/png;base64,', $merged);
        $this->assertStringContainsString('مشاهدهٔ موقعیت روی نقشه', $merged);
    }

    public function test_gps_field_falls_back_to_a_plain_link_when_tiles_are_unreachable(): void
    {
        Storage::fake();
        Http::fake([
            'https://tile.openstreetmap.org/*' => Http::response('', 500),
        ]);

        $template = FormTemplate::factory()->create([
            'document_template' => '<p><span data-type="mergeTag" data-id="location">موقعیت</span></p>',
        ]);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'location',
            'field_type' => FormFieldType::Gps,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value_json' => ['lat' => 35.715298, 'lng' => 51.404343],
        ]);

        $merged = $submission->load('values.formField')->mergedDocumentBody();

        $this->assertStringNotContainsString('<img', $merged);
        $this->assertStringContainsString('مشاهدهٔ موقعیت روی نقشه', $merged);
    }

    public function test_gps_thumbnail_uses_the_local_tile_pack_without_any_internet(): void
    {
        Storage::fake();
        Storage::fake('local');
        Http::fake();
        [$x, $y] = [(int) floor((51.404343 + 180) / 360 * 2 ** 16), (int) floor((1 - asinh(tan(deg2rad(35.715298))) / M_PI) / 2 * 2 ** 16)];

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                Storage::disk('local')->put('map-tiles/16/'.($x + $dx).'/'.($y + $dy).'.png', $this->tinyPng());
            }
        }

        $template = FormTemplate::factory()->create([
            'document_template' => '<p><span data-type="mergeTag" data-id="location">موقعیت</span></p>',
        ]);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'location',
            'field_type' => FormFieldType::Gps,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create([
            'form_field_id' => $field->id,
            'value_json' => ['lat' => 35.715298, 'lng' => 51.404343],
        ]);

        $merged = $submission->load('values.formField')->mergedDocumentBody();

        $this->assertStringContainsString('<img src="data:image/png;base64,', $merged);
        Http::assertNothingSent();
    }

    public function test_gps_thumbnail_does_not_call_a_server_when_the_tile_address_is_the_local_route(): void
    {
        Storage::fake();
        Storage::fake('local');
        config(['services.maps.tile_url' => '/map-tiles/{z}/{x}/{y}.png']);
        Http::fake();

        $this->assertNull(StaticMapThumbnail::dataUri(35.715298, 51.404343));
        Http::assertNothingSent();
    }

    public function test_file_field_embeds_an_image_attachment_inline(): void
    {
        Storage::fake();
        $path = 'form-submissions/photo.jpg';
        Storage::disk()->put($path, $this->tinyPng());

        $template = FormTemplate::factory()->create([
            'document_template' => '<p><span data-type="mergeTag" data-id="photo">عکس</span></p>',
        ]);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'photo',
            'field_type' => FormFieldType::File,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create(['form_field_id' => $field->id, 'file_path' => $path]);

        $merged = $submission->load('values.formField')->mergedDocumentBody();

        $this->assertStringContainsString('<img src="data:', $merged);
        $this->assertStringContainsString('پیوست فایل', $merged);
    }

    public function test_file_field_shows_only_a_download_link_for_a_non_image_attachment(): void
    {
        Storage::fake();
        $path = 'form-submissions/report.pdf';
        Storage::disk()->put($path, 'not a real pdf, just bytes');

        $template = FormTemplate::factory()->create([
            'document_template' => '<p><span data-type="mergeTag" data-id="report">گزارش</span></p>',
        ]);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'report',
            'field_type' => FormFieldType::File,
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create(['form_field_id' => $field->id, 'file_path' => $path]);

        $merged = $submission->load('values.formField')->mergedDocumentBody();

        $this->assertStringNotContainsString('<img', $merged);
        $this->assertStringContainsString('پیوست فایل', $merged);
    }
}
