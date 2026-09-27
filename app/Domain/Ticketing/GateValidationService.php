<?php

namespace App\Domain\Ticketing;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Enums\AdmissionTicketStatus;
use App\Enums\GateRejectionReason;
use App\Enums\GateScanResult;
use App\Enums\WebhookEventType;
use App\Models\AdmissionTicket;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\QrToken;
use App\Models\TicketUsageLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Implements the exact checklist from the spec's "Gate Validation
 * Algorithm" section, in order, and the atomic consumption that follows
 * a pass. Every scan - success or rejection - is written to
 * ticket_usage_logs; only a genuine consumption also gets a ticket_usages
 * row (see that table's own migration comment on why the two are
 * separate).
 *
 * Concurrency (double-scan) is defended in two independent layers:
 *  1. SELECT ... FOR UPDATE on the ticket row inside the consuming
 *     transaction - the second of two concurrent scans blocks until the
 *     first commits, then re-reads the now-updated used_uses and is
 *     correctly rejected.
 *  2. A UNIQUE(admission_ticket_id, usage_number) constraint on
 *     ticket_usages as a hard backstop even if the locking above were
 *     ever weakened by a future change.
 */
class GateValidationService
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    /**
     * @return array{result: GateScanResult, ticket: ?AdmissionTicket, reason: ?GateRejectionReason, usage_number: ?int}
     */
    public function scan(string $qrToken, Gate $gate, ?User $user, array $context = []): array
    {
        $token = QrToken::with('admissionTicket.ticketVariant')->where('token', $qrToken)->first();

        if (! $token || $token->isRevoked()) {
            return $this->reject(null, $gate, $user, null, GateRejectionReason::InvalidQr, $context);
        }

        $ticket = $token->admissionTicket;

        if (! $ticket) {
            return $this->reject(null, $gate, $user, null, GateRejectionReason::InvalidQr, $context);
        }

        if ($ticket->status === AdmissionTicketStatus::Cancelled) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::Cancelled, $context);
        }

        if ($ticket->status === AdmissionTicketStatus::Refunded) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::Refunded, $context);
        }

        if ($ticket->status === AdmissionTicketStatus::Blocked) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::Blocked, $context);
        }

        $now = now();

        if ($now->lessThan($ticket->valid_from)) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::NotYetValid, $context);
        }

        if ($now->greaterThanOrEqualTo($ticket->valid_until)) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::Expired, $context);
        }

        if ($ticket->remaining_uses <= 0) {
            return $this->reject($ticket, $gate, $user, null, GateRejectionReason::NoRemainingUsage, $context);
        }

        $nextUsageNumber = $ticket->nextUsageNumber();
        $matchedRule = $this->findMatchingRule($gate, $ticket, $nextUsageNumber);

        if (! $matchedRule) {
            // Distinguish "wrong gate entirely" from "right gate, wrong
            // stage" (a round-trip's return scanned before its outbound)
            // purely for a clearer message - the ticket is rejected
            // either way.
            $anyRuleForThisGateAndTicket = $gate->rules()
                ->where('is_active', true)
                ->get()
                ->contains(fn (GateRule $rule) => $rule->matches($ticket, 1, $ticket->ticketVariant->direction)
                    || $rule->matches($ticket, 2, $ticket->ticketVariant->direction));

            $reason = $anyRuleForThisGateAndTicket ? GateRejectionReason::SequenceError : GateRejectionReason::WrongGate;

            return $this->reject($ticket, $gate, $user, $nextUsageNumber, $reason, $context);
        }

        return $this->consume($ticket, $gate, $user, $matchedRule, $nextUsageNumber, $context);
    }

    private function findMatchingRule(Gate $gate, AdmissionTicket $ticket, int $nextUsageNumber): ?GateRule
    {
        return $gate->rules()
            ->where('is_active', true)
            ->get()
            ->first(fn (GateRule $rule) => $rule->matches($ticket, $nextUsageNumber, $ticket->ticketVariant->direction));
    }

    private function consume(
        AdmissionTicket $ticket,
        Gate $gate,
        ?User $user,
        GateRule $matchedRule,
        int $expectedUsageNumber,
        array $context,
    ): array {
        try {
            return DB::transaction(function () use ($ticket, $gate, $user, $matchedRule, $expectedUsageNumber, $context) {
                /** @var AdmissionTicket $locked */
                $locked = AdmissionTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

                // Re-check everything time-sensitive against the freshly
                // locked row - a concurrent scan may have consumed a
                // usage (or the ticket may have been cancelled/refunded)
                // in the gap between the pre-checks above and this lock.
                if ($locked->isBlockingStatus() || $locked->remaining_uses <= 0 || $locked->nextUsageNumber() !== $expectedUsageNumber) {
                    throw new TicketingException('race-lost');
                }

                $direction = $matchedRule->direction ?? $ticket->ticketVariant->direction;

                $locked->usages()->create([
                    'gate_id' => $gate->id,
                    'user_id' => $user?->id,
                    'usage_number' => $expectedUsageNumber,
                    'direction' => $direction,
                ]);

                $locked->increment('used_uses');
                $locked->refresh();

                $locked->update([
                    'status' => $locked->remaining_uses > 0
                        ? AdmissionTicketStatus::PartiallyUsed
                        : AdmissionTicketStatus::Used,
                ]);

                $this->log($locked, $gate, $user, $expectedUsageNumber, $direction?->value, GateScanResult::Success, null, $context);

                return [
                    'result' => GateScanResult::Success,
                    'ticket' => $locked,
                    'reason' => null,
                    'usage_number' => $expectedUsageNumber,
                ];
            });
        } catch (TicketingException|QueryException) {
            // Lost the race to a concurrent scan (either our own
            // re-check caught it, or the unique constraint on
            // ticket_usages did) - the other request gets SUCCESS, this
            // one is a normal, expected rejection.
            return $this->reject($ticket->fresh(), $gate, $user, $expectedUsageNumber, GateRejectionReason::AlreadyUsed, $context);
        }
    }

    private function reject(
        ?AdmissionTicket $ticket,
        Gate $gate,
        ?User $user,
        ?int $usageNumber,
        GateRejectionReason $reason,
        array $context,
    ): array {
        $this->log($ticket, $gate, $user, $usageNumber, null, GateScanResult::Rejected, $reason, $context);

        return [
            'result' => GateScanResult::Rejected,
            'ticket' => $ticket,
            'reason' => $reason,
            'usage_number' => $usageNumber,
        ];
    }

    private function log(
        ?AdmissionTicket $ticket,
        Gate $gate,
        ?User $user,
        ?int $usageNumber,
        ?string $direction,
        GateScanResult $result,
        ?GateRejectionReason $reason,
        array $context,
    ): void {
        TicketUsageLog::create([
            'admission_ticket_id' => $ticket?->id,
            'gate_id' => $gate->id,
            'user_id' => $user?->id,
            'direction' => $direction,
            'usage_number' => $usageNumber,
            'result' => $result,
            'reason' => $reason,
            'device_identifier' => $context['device_identifier'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'scanned_at' => now(),
        ]);

        $this->webhooks->dispatch(
            $ticket?->orderItem?->order?->external_api_client_id,
            $result === GateScanResult::Success ? WebhookEventType::TicketScanned : WebhookEventType::TicketRejected,
            [
                'ticket_number' => $ticket?->ticket_number,
                'gate' => $gate->code,
                'result' => $result->value,
                'reason' => $reason?->value,
                'usage_number' => $usageNumber,
                'scanned_at' => now()->toIso8601String(),
            ],
        );
    }
}
