<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentAccessLevel;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExternalApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    private function clientWithUploader(): ExternalApiClient
    {
        return ExternalApiClient::factory()->create(['created_by' => User::factory()->create()->id]);
    }

    public function test_a_document_is_uploaded_and_stored(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/external/documents', [
            'title' => 'قرارداد اجاره اتاق',
            'file' => $file,
        ], [...$this->headers($client), 'Idempotency-Key' => 'doc-1']);

        $response->assertStatus(201);
        $document = Document::first();
        $this->assertSame('قرارداد اجاره اتاق', $document->title);
        $this->assertSame($client->created_by, $document->uploaded_by);
        $this->assertSame($client->id, $document->external_api_client_id);
        $this->assertSame(DocumentAccessLevel::Restricted, $document->access_level);
        Storage::disk()->assertExists($document->file_path);
    }

    public function test_a_client_with_no_uploader_configured_gets_a_configuration_error(): void
    {
        Storage::fake();
        $client = ExternalApiClient::factory()->create();
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/external/documents', [
            'title' => 'قرارداد اجاره اتاق',
            'file' => $file,
        ], [...$this->headers($client), 'Idempotency-Key' => 'doc-2']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'CONFIGURATION_ERROR');
        $this->assertSame(0, Document::count());
    }

    public function test_a_department_only_upload_requires_a_department(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/external/documents', [
            'title' => 'قرارداد اجاره اتاق',
            'file' => $file,
            'access_level' => 'department_only',
        ], [...$this->headers($client), 'Idempotency-Key' => 'doc-3']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_a_repeated_idempotency_key_replays_the_original_response_without_duplicating(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $headers = [...$this->headers($client), 'Idempotency-Key' => 'doc-4'];

        $first = $this->post('/api/v1/external/documents', ['title' => 'قرارداد', 'file' => $file], $headers);
        $second = $this->post('/api/v1/external/documents', ['title' => 'قرارداد', 'file' => $file], $headers);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('document_id'), $second->json('document_id'));
        $this->assertSame(1, Document::count());
    }

    public function test_a_client_can_download_a_document_it_uploaded(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $document = Document::factory()->create([
            'external_api_client_id' => $client->id,
            'uploaded_by' => $client->created_by,
            'file_path' => 'documents/contract.pdf',
        ]);
        Storage::disk()->put($document->file_path, 'file-contents');

        $response = $this->get("/api/v1/external/documents/{$document->id}/download", $this->headers($client));

        $response->assertOk();
    }

    public function test_a_client_cannot_download_another_clients_document(): void
    {
        Storage::fake();
        $owner = $this->clientWithUploader();
        $other = $this->clientWithUploader();
        $document = Document::factory()->create([
            'external_api_client_id' => $owner->id,
            'uploaded_by' => $owner->created_by,
            'file_path' => 'documents/contract.pdf',
        ]);
        Storage::disk()->put($document->file_path, 'file-contents');

        $response = $this->get("/api/v1/external/documents/{$document->id}/download", $this->headers($other));

        $response->assertStatus(404);
    }

    public function test_a_document_created_through_the_panel_is_not_downloadable_by_any_api_client(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $department = Department::factory()->create();
        $document = Document::factory()->create([
            'external_api_client_id' => null,
            'department_id' => $department->id,
            'file_path' => 'documents/contract.pdf',
        ]);
        Storage::disk()->put($document->file_path, 'file-contents');

        $response = $this->get("/api/v1/external/documents/{$document->id}/download", $this->headers($client));

        $response->assertStatus(404);
    }

    public function test_a_client_without_document_upload_permission_is_forbidden(): void
    {
        Storage::fake();
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['document.read'], 'created_by' => User::factory()->create()->id]);
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/external/documents', [
            'title' => 'قرارداد اجاره اتاق',
            'file' => $file,
        ], [...$this->headers($client), 'Idempotency-Key' => 'doc-forbidden']);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(0, Document::count());
    }

    public function test_a_client_without_document_read_permission_is_forbidden_to_download(): void
    {
        Storage::fake();
        $client = $this->clientWithUploader();
        $client->update(['allowed_operations' => ['document.upload']]);
        $document = Document::factory()->create([
            'external_api_client_id' => $client->id,
            'uploaded_by' => $client->created_by,
            'file_path' => 'documents/contract.pdf',
        ]);
        Storage::disk()->put($document->file_path, 'file-contents');

        $response = $this->get("/api/v1/external/documents/{$document->id}/download", $this->headers($client));

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
