<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\DocumentAccessLevel;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets an external system deposit a file into مدیریت اسناد (e.g. a signed
 * contract from an e-signature tool, an ID scan from a booking widget) and
 * read it back later - stored through Document exactly like a panel
 * upload, so it shows up in the same folders/versions/access-control
 * system as everything else. uploaded_by is borrowed from the API
 * client's own created_by, same reasoning as FormSubmissionController.
 */
class DocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('document.upload')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به بارگذاری سند نیست.', 403);
        }

        if (! $client->created_by) {
            return $this->error('CONFIGURATION_ERROR', 'برای این اکانت کاربر «بارگذاری‌کننده» تنظیم نشده است - از پنل مدیریت اکانت‌های API این را تنظیم کنید.', 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $this->error('INVALID_REQUEST', 'هدر Idempotency-Key الزامی است.', 400);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
            'access_level' => ['nullable', 'string', 'in:public_internal,department_only,restricted'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $accessLevel = isset($data['access_level']) ? DocumentAccessLevel::from($data['access_level']) : DocumentAccessLevel::Restricted;

        if ($accessLevel === DocumentAccessLevel::DepartmentOnly && ! isset($data['department_id'])) {
            return $this->error('VALIDATION_ERROR', 'برای سطح دسترسی «مخصوص دپارتمان» باید department_id مشخص شود.', 422);
        }

        $file = $data['file'];
        $requestHash = hash('sha256', json_encode([
            'client' => $client->id,
            'title' => $data['title'],
            'access_level' => $accessLevel->value,
            'department_id' => $data['department_id'] ?? null,
            'file_hash' => hash_file('sha256', $file->getRealPath()),
        ]));

        if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
            return $replay;
        }

        $filePath = $file->store('documents', config('filesystems.default'));

        $document = Document::create([
            'title' => $data['title'],
            'file_path' => $filePath,
            'mime_type' => Storage::disk()->mimeType($filePath),
            'size_kb' => (int) ceil(Storage::disk()->size($filePath) / 1024),
            'access_level' => $accessLevel,
            'department_id' => $data['department_id'] ?? null,
            'uploaded_by' => $client->created_by,
            'external_api_client_id' => $client->id,
        ]);

        return $this->storeIdempotencyAndRespond($client, $idempotencyKey, $requestHash, 201, $this->transformDocument($document));
    }

    public function download(Request $request, int $document): StreamedResponse|JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('document.read')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مشاهده‌ی سند نیست.', 403);
        }

        $documentModel = Document::find($document);

        if (! $documentModel || $documentModel->external_api_client_id !== $client->id) {
            return $this->error('NOT_FOUND', 'سندی با این شناسه یافت نشد.', 404);
        }

        return Storage::disk()->download($documentModel->file_path, $documentModel->downloadFilename());
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDocument(Document $document): array
    {
        return [
            'document_id' => $document->id,
            'title' => $document->title,
            'mime_type' => $document->mime_type,
            'size_kb' => $document->size_kb,
            'access_level' => $document->access_level->value,
            'created_at' => $document->created_at->toIso8601String(),
        ];
    }

    private function replayIfSeen(ExternalApiClient $client, string $key, string $requestHash): ?JsonResponse
    {
        $existing = IdempotencyKey::where('external_api_client_id', $client->id)->where('key', $key)->first();

        if (! $existing) {
            return null;
        }

        if ($existing->request_hash !== $requestHash) {
            return $this->error('CONFLICT', 'این Idempotency-Key قبلاً با محتوای متفاوتی استفاده شده است.', 409);
        }

        return response()->json($existing->response_body, $existing->response_status);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function storeIdempotencyAndRespond(ExternalApiClient $client, string $key, string $requestHash, int $status, array $body): JsonResponse
    {
        try {
            IdempotencyKey::create([
                'external_api_client_id' => $client->id,
                'key' => $key,
                'request_hash' => $requestHash,
                'response_status' => $status,
                'response_body' => $body,
            ]);
        } catch (QueryException) {
            if ($replay = $this->replayIfSeen($client, $key, $requestHash)) {
                return $replay;
            }
        }

        return response()->json($body, $status);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
