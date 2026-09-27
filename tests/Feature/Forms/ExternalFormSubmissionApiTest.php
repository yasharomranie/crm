<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Models\ExternalApiClient;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalFormSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    private function clientWithSubmitter(): ExternalApiClient
    {
        return ExternalApiClient::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    public function test_a_submission_is_created_with_correctly_typed_values(): void
    {
        $template = FormTemplate::factory()->create();
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'full_name',
            'field_type' => FormFieldType::Text,
            'is_required' => true,
        ]);
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'guests_count',
            'field_type' => FormFieldType::Number,
        ]);
        $client = $this->clientWithSubmitter();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => ['full_name' => 'زهرا محمدی', 'guests_count' => 3],
        ], $this->headers($client));

        $response->assertStatus(201);
        $submission = FormSubmission::first();
        $this->assertSame('زهرا محمدی', $submission->fieldValue('full_name'));
        $this->assertEquals(3, $submission->fieldValue('guests_count'));
        $this->assertSame($client->id, $submission->external_api_client_id);
        $this->assertSame($client->created_by, $submission->submitted_by);
    }

    public function test_a_missing_required_field_is_rejected(): void
    {
        $template = FormTemplate::factory()->create();
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'full_name',
            'is_required' => true,
        ]);
        $client = $this->clientWithSubmitter();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_an_invalid_select_option_is_rejected(): void
    {
        $template = FormTemplate::factory()->create();
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'room_type',
            'field_type' => FormFieldType::Select,
            'options' => ['single' => 'یک‌نفره', 'double' => 'دونفره'],
        ]);
        $client = $this->clientWithSubmitter();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => ['room_type' => 'triple'],
        ], $this->headers($client));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_a_required_file_field_makes_the_template_unsubmittable_through_the_api(): void
    {
        $template = FormTemplate::factory()->create();
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'attachment',
            'field_type' => FormFieldType::File,
            'is_required' => true,
        ]);
        $client = $this->clientWithSubmitter();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_an_inactive_template_is_not_found(): void
    {
        $template = FormTemplate::factory()->create(['is_active' => false]);
        $client = ExternalApiClient::factory()->create();

        $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client))->assertStatus(404);
    }

    public function test_a_submission_with_no_workflow_is_approved_immediately(): void
    {
        $template = FormTemplate::factory()->create();
        $client = $this->clientWithSubmitter();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client));

        $response->assertStatus(201);
        $this->assertSame(FormSubmissionStatus::Approved->value, $response->json('status'));
    }

    public function test_a_client_with_no_submitter_configured_gets_a_configuration_error(): void
    {
        $template = FormTemplate::factory()->create();
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'CONFIGURATION_ERROR');
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_a_client_without_form_submission_create_permission_is_forbidden(): void
    {
        $template = FormTemplate::factory()->create();
        $client = $this->clientWithSubmitter();
        $client->update(['allowed_operations' => ['form_submission.read']]);

        $response = $this->postJson("/api/v1/external/form-templates/{$template->id}/submissions", [
            'values' => [],
        ], $this->headers($client));

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(0, FormSubmission::count());
    }
}
