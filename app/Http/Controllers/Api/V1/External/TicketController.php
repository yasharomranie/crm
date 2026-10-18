<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PrintType;
use App\Enums\TicketSourceType;
use App\Http\Controllers\Controller;
use App\Models\AdmissionTicket;
use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\TicketVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The order/ticket the API returns is created through the exact same
 * TicketIssuer used by the Cashier page - an externally-sold ticket goes
 * through the same price snapshot, discount, and template-version rules
 * as any other (section 101: the central system is always the source of
 * truth, POS/online never invent their own ticket_number or QR).
 */
class TicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $this->error('INVALID_REQUEST', 'هدر Idempotency-Key الزامی است.', 400);
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'ticket_variant_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'external_order_id' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $requestHash = hash('sha256', json_encode(['client' => $client->id, ...$data]));

        if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
            return $replay;
        }

        if (! $client->hasPermission('ticket.issue')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به صدور بلیت نیست.', 403);
        }

        if (! $client->isProductAllowed($data['product_id'])) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به فروش این محصول نیست.', 403);
        }

        $variant = TicketVariant::find($data['ticket_variant_id']);

        if (! $variant || $variant->product_id !== (int) $data['product_id']) {
            return $this->error('NOT_FOUND', 'نوع بلیت یافت نشد.', 404);
        }

        // The order and its idempotency record are written in one
        // transaction: if an identical request running at the same moment
        // wins the unique key, this transaction rolls back - tickets
        // included - and the winner's response is replayed. Without this
        // both requests would sell tickets and only the replay would hide
        // the duplicate order.
        try {
            [$status, $body] = DB::transaction(function () use ($client, $variant, $data, $idempotencyKey, $requestHash) {
                [$status, $body] = $this->issue($client, $variant, $data);

                IdempotencyKey::create([
                    'external_api_client_id' => $client->id,
                    'key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'response_status' => $status,
                    'response_body' => $body,
                ]);

                return [$status, $body];
            });
        } catch (QueryException $exception) {
            if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
                return $replay;
            }

            throw $exception;
        }

        return response()->json($body, $status);
    }

    public function show(Request $request, string $ticketNumber): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('ticket.read')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مشاهده‌ی بلیت نیست.', 403);
        }

        $ticket = AdmissionTicket::with(['qrToken', 'ticketVariant.product', 'orderItem.order'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket || ! $this->clientOwnsTicket($client, $ticket)) {
            return $this->error('NOT_FOUND', 'بلیتی با این شماره یافت نشد.', 404);
        }

        return response()->json($this->transformTicket($ticket));
    }

    public function reprint(Request $request, string $ticketNumber): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('ticket.reprint')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به چاپ مجدد نیست.', 403);
        }

        $ticket = AdmissionTicket::with(['qrToken', 'ticketVariant.product'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket || ! $this->clientOwnsTicket($client, $ticket)) {
            return $this->error('NOT_FOUND', 'بلیتی با این شماره یافت نشد.', 404);
        }

        // Never creates a new ticket (section 121) - only a log entry, and
        // the same ticket_number/QR are returned again.
        $ticket->printLogs()->create([
            'ticket_template_version_id' => $ticket->ticket_template_version_id,
            'print_type' => PrintType::Reprint,
            'device' => "external:{$client->client_name}",
            'printed_at' => now(),
        ]);

        return response()->json($this->transformTicket($ticket));
    }

    private function clientOwnsTicket(ExternalApiClient $client, AdmissionTicket $ticket): bool
    {
        $order = $ticket->orderItem->order;

        return $order->external_api_client_id === $client->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function issue(ExternalApiClient $client, TicketVariant $variant, array $data): array
    {
        try {
            $order = app(TicketIssuer::class)->issue([
                'source_type' => $client->terminal_id ? TicketSourceType::ExternalPos : TicketSourceType::Online,
                'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $data['quantity'] ?? 1]],
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'discount_code' => $data['discount_code'] ?? null,
                'external_order_id' => $data['external_order_id'] ?? null,
                'external_api_client_id' => $client->id,
            ]);

            if (! empty($data['payment_reference']) && $order->final_amount > 0) {
                $order->payments()->create([
                    'method' => $client->terminal_id ? 'card' : 'online',
                    'amount' => $order->final_amount,
                    'status' => 'paid',
                    'reference' => $data['payment_reference'],
                    'paid_at' => now(),
                ]);
                $order->update(['amount_paid' => $order->final_amount]);
            }

            return [201, $this->transformOrder($order)];
        } catch (TicketingException $exception) {
            return [422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => $exception->getMessage()]]];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformOrder(Order $order): array
    {
        $tickets = $order->items->flatMap(fn ($item) => $item->tickets)->map(fn (AdmissionTicket $ticket) => $this->transformTicket($ticket));

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'external_order_id' => $order->external_order_id,
            'final_amount' => $order->final_amount,
            'currency' => $order->currency,
            'tickets' => $tickets->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTicket(AdmissionTicket $ticket): array
    {
        return [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            // The QR must encode this raw value verbatim (section 112) -
            // never the ticket_number, never the id.
            'qr_payload' => $ticket->qrToken?->token,
            'product' => $ticket->ticketVariant->product->code,
            'ticket_variant' => $ticket->ticketVariant->code,
            'valid_from' => $ticket->valid_from->toIso8601String(),
            'valid_until' => $ticket->valid_until->toIso8601String(),
            'allowed_uses' => $ticket->allowed_uses,
            'remaining_uses' => $ticket->remaining_uses,
            'template_version_id' => $ticket->ticket_template_version_id,
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
