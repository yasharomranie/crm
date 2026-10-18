<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Http\Controllers\Api\V1\External\Concerns\RunsIdempotently;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\IdempotencyKey;
use App\Support\MobileNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A lead captured on an external site/ad landing page - lands in the
 * exact same pipeline (deals table, DealStage::NewLead) a staff member
 * would create from the CRM itself, so the sales dashboard and Kanban
 * board need no special case for where a deal came from.
 */
class DealController extends Controller
{
    use RunsIdempotently;

    public function store(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $this->error('INVALID_REQUEST', 'هدر Idempotency-Key الزامی است.', 400);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'in:walk_in,phone,online,referral,social_media,other'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'guest' => ['nullable', 'array'],
            'guest.name' => ['required_with:guest', 'string', 'max:255'],
            'guest.mobile' => ['required_with:guest', 'string', 'max:32'],
            'guest.email' => ['nullable', 'email', 'max:255'],
        ]);

        $requestHash = hash('sha256', json_encode(['client' => $client->id, ...$data]));

        if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
            return $replay;
        }

        if (! $client->hasPermission('deal.create')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به ثبت سرنخ نیست.', 403);
        }

        return $this->idempotently($client, $idempotencyKey, $requestHash, function () use ($client, $data) {
            $guest = null;

            if (! empty($data['guest'])) {
                $guest = Guest::firstOrCreate(
                    ['mobile' => MobileNumber::canonical($data['guest']['mobile'])],
                    ['name' => $data['guest']['name'], 'email' => $data['guest']['email'] ?? null],
                );
            }

            $deal = Deal::create([
                'guest_id' => $guest?->id,
                'title' => $data['title'],
                'stage' => DealStage::NewLead,
                'value' => $data['value'] ?? null,
                'source' => isset($data['source']) ? DealSource::from($data['source']) : DealSource::Online,
                'external_api_client_id' => $client->id,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return [201, $this->transformDeal($deal)];
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('deal.read')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مشاهده‌ی سرنخ نیست.', 403);
        }

        $deal = Deal::with('guest')->find($id);

        if (! $deal || $deal->external_api_client_id !== $client->id) {
            return $this->error('NOT_FOUND', 'سرنخی با این شناسه یافت نشد.', 404);
        }

        return response()->json($this->transformDeal($deal));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDeal(Deal $deal): array
    {
        return [
            'deal_id' => $deal->id,
            'title' => $deal->title,
            'stage' => $deal->stage->value,
            'value' => $deal->value,
            'guest' => $deal->guest ? ['name' => $deal->guest->name, 'mobile' => $deal->guest->mobile] : null,
            'created_at' => $deal->created_at->toIso8601String(),
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

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
