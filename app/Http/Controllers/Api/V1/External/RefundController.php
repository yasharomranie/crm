<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Domain\Ticketing\RefundService;
use App\Domain\Ticketing\TicketingException;
use App\Http\Controllers\Controller;
use App\Models\AdmissionTicket;
use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(Request $request, string $ticketNumber): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $this->error('INVALID_REQUEST', 'هدر Idempotency-Key الزامی است.', 400);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $requestHash = hash('sha256', json_encode(['client' => $client->id, 'ticket' => $ticketNumber, ...$data]));

        if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
            return $replay;
        }

        if (! $client->hasPermission('ticket.refund')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به استرداد بلیت نیست.', 403);
        }

        $ticket = AdmissionTicket::with('orderItem.order')
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket || $ticket->orderItem->order->external_api_client_id !== $client->id) {
            return $this->error('NOT_FOUND', 'بلیتی با این شماره یافت نشد.', 404);
        }

        [$status, $body] = $this->refund($ticket, $data);

        return $this->storeIdempotencyAndRespond($client, $idempotencyKey, $requestHash, $status, $body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function refund(AdmissionTicket $ticket, array $data): array
    {
        try {
            $refund = app(RefundService::class)->refundTicket(
                $ticket,
                reason: $data['reason'] ?? null,
                reference: $data['reference'] ?? null,
            );

            return [201, $this->transformRefund($refund, $ticket)];
        } catch (TicketingException $exception) {
            return [422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => $exception->getMessage()]]];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRefund(Refund $refund, AdmissionTicket $ticket): array
    {
        return [
            'refund_id' => $refund->id,
            'ticket_number' => $ticket->ticket_number,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'reference' => $refund->reference,
            'refunded_at' => $refund->refunded_at->toIso8601String(),
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
