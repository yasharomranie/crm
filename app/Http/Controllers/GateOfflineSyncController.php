<?php

namespace App\Http\Controllers;

use App\Domain\Ticketing\GateValidationService;
use App\Enums\AdmissionTicketStatus;
use App\Enums\GateRejectionReason;
use App\Enums\GateScanResult;
use App\Models\AdmissionTicket;
use App\Models\Gate;
use App\Models\GateOfflineScanSync;
use App\Models\GateRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Backs the Gate Scanner page's offline mode (messenger.blade.php's
 * ai-assistant-style JS "thread" pattern applied to gate-scanner.blade.php):
 * while the device has a connection, the browser pulls a snapshot of every
 * currently-usable ticket plus this gate's own rules into IndexedDB
 * (pullPackage) and validates scans against that live server data as
 * usual; the moment navigator.onLine goes false, scanning switches to a
 * JS port of GateValidationService running entirely against that local
 * snapshot, queuing each result. pushScans is where those queued scans
 * get replayed through the *real* GateValidationService - the offline
 * guess is informational only, never trusted - once the connection is
 * back.
 */
class GateOfflineSyncController extends Controller
{
    public const PACKAGE_PAGE_SIZE = 2000;

    public function pullPackage(Request $request, Gate $gate): JsonResponse
    {
        $this->authorizeGateScannerAccess($request);

        $pageSize = max(100, min(5000, (int) $request->query('limit', self::PACKAGE_PAGE_SIZE)));

        $rules = $gate->rules()
            ->where('is_active', true)
            ->get(['id', 'product_id', 'ticket_variant_id', 'direction', 'usage_stage']);

        // Every ticket that could plausibly still be scanned somewhere
        // during the offline window - not yet blocked/exhausted, and not
        // already expired. valid_from is deliberately not filtered on: a
        // ticket that isn't valid *yet* may well become valid while this
        // device is offline (e.g. a multi-day pass synced the day before).
        $tickets = AdmissionTicket::query()
            ->when($this->ruleScope($rules), fn ($query, array $scope) => $query->whereHas('ticketVariant', fn ($variants) => $variants->where(fn ($either) => $either
                ->whereIn('ticket_variants.id', $scope['variants'])
                ->orWhereIn('ticket_variants.product_id', $scope['products']))))
            ->whereNotIn('status', [
                AdmissionTicketStatus::Cancelled,
                AdmissionTicketStatus::Refunded,
                AdmissionTicketStatus::Blocked,
                AdmissionTicketStatus::Used,
            ])
            ->where('admission_tickets.id', '>', max(0, (int) $request->query('after_id', 0)))
            ->where('valid_until', '>', now())
            ->whereHas('qrToken', fn ($query) => $query->whereNull('revoked_at'))
            ->with(['qrToken:id,admission_ticket_id,token', 'ticketVariant:id,product_id,direction'])
            ->orderBy('admission_tickets.id')
            ->limit($pageSize + 1)
            ->get([
                'id', 'ticket_number', 'ticket_variant_id', 'status',
                'allowed_uses', 'used_uses', 'valid_from', 'valid_until',
            ]);

        // One more row than the page is asked for tells whether another
        // page follows; the browser keeps fetching with after_id until
        // next_after_id comes back null, then swaps its local copy in one go.
        $hasMore = $tickets->count() > $pageSize;
        $tickets = $tickets->take($pageSize);

        return response()->json([
            'gate' => ['id' => $gate->id, 'name' => $gate->name],
            'rules' => $rules,
            'next_after_id' => $hasMore ? $tickets->last()->id : null,
            'tickets' => $tickets->map(fn ($ticket) => [
                'token' => $ticket->qrToken?->token,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'product_id' => $ticket->ticketVariant->product_id,
                'ticket_variant_id' => $ticket->ticket_variant_id,
                'direction' => $ticket->ticketVariant->direction?->value,
                'status' => $ticket->status->value,
                'allowed_uses' => $ticket->allowed_uses,
                'used_uses' => $ticket->used_uses,
                'valid_from' => $ticket->valid_from->toIso8601String(),
                'valid_until' => $ticket->valid_until->toIso8601String(),
            ])->filter(fn ($ticket) => $ticket['token'] !== null)->values(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * The products/variants this gate can admit at all, so the offline
     * package only carries tickets that gate could ever accept instead of
     * every ticket in the system. Null (no filtering) as soon as any rule
     * is unrestricted - and also when the gate has no rules, where nothing
     * could be admitted anyway but the package stays harmless.
     *
     * @param  Collection<int, GateRule>  $rules
     * @return array{products: list<int>, variants: list<int>}|null
     */
    private function ruleScope($rules): ?array
    {
        if ($rules->isEmpty() || $rules->contains(fn ($rule) => $rule->product_id === null && $rule->ticket_variant_id === null)) {
            return null;
        }

        return [
            'products' => $rules->pluck('product_id')->filter()->unique()->values()->all(),
            'variants' => $rules->pluck('ticket_variant_id')->filter()->unique()->values()->all(),
        ];
    }

    public function pushScans(Request $request, Gate $gate): JsonResponse
    {
        $this->authorizeGateScannerAccess($request);

        $data = $request->validate([
            'scans' => ['required', 'array', 'max:200'],
            'scans.*.client_scan_id' => ['required', 'string', 'max:64'],
            'scans.*.qr_token' => ['required', 'string', 'max:255'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.local_result' => ['required', 'in:success,rejected'],
            'scans.*.local_reason' => ['nullable', 'string'],
        ]);

        // Oldest first, so a round-trip ticket scanned outbound then
        // return while offline still consumes in the right order once
        // replayed through the real, stateful GateValidationService.
        $scans = collect($data['scans'])->sortBy('scanned_at')->values();

        $results = $scans->map(function (array $scan) use ($gate, $request) {
            $existing = GateOfflineScanSync::query()
                ->where('gate_id', $gate->id)
                ->where('client_scan_id', $scan['client_scan_id'])
                ->first();

            if ($existing) {
                return $this->transform($existing);
            }

            $localReason = $scan['local_reason'] ?? null;

            $result = app(GateValidationService::class)->scan($scan['qr_token'], $gate, $request->user(), [
                'device_identifier' => $request->header('X-Device-Id'),
                'ip_address' => $request->ip(),
                'scanned_at' => $scan['scanned_at'],
                'metadata' => ['offline_sync' => true, 'client_scanned_at' => $scan['scanned_at']],
            ]);

            $sync = GateOfflineScanSync::create([
                'gate_id' => $gate->id,
                'client_scan_id' => $scan['client_scan_id'],
                'admission_ticket_id' => $result['ticket']?->id,
                'result' => $result['result'],
                'reason' => $result['reason'],
                'usage_number' => $result['usage_number'],
                'local_result' => GateScanResult::from($scan['local_result']),
                'local_reason' => $localReason ? GateRejectionReason::tryFrom($localReason) : null,
                'scanned_at' => $scan['scanned_at'],
            ]);

            return $this->transform($sync);
        });

        return response()->json(['results' => $results]);
    }

    private function authorizeGateScannerAccess(Request $request): void
    {
        abort_unless($request->user()?->can('gate-scanner.access'), 403, 'شما مجاز به استفاده از اسکنر گیت نیستید.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(GateOfflineScanSync $sync): array
    {
        return [
            'client_scan_id' => $sync->client_scan_id,
            'result' => $sync->result->value,
            'reason' => $sync->reason?->value,
            'reason_label' => $sync->reason?->getLabel(),
            'ticket_number' => $sync->admissionTicket?->ticket_number,
            'usage_number' => $sync->usage_number,
            'reconciled' => $sync->isReconciled(),
        ];
    }
}
