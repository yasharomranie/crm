<?php

namespace App\Domain\Ticketing;

use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrToken;
use App\Models\TicketVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place that creates an Order + OrderItems + AdmissionTickets +
 * QrTokens - used by the Cashier page, the external sales API, and
 * complimentary issuance alike, so every issuance path gets the same
 * price-snapshot, discount, and template-version-snapshot guarantees
 * (sections 18-19, 46) with no duplicated logic.
 *
 * @param  array{
 *     source_type: TicketSourceType,
 *     items: array<int, array{ticket_variant_id: int, quantity: int}>,
 *     customer_name?: ?string,
 *     customer_phone?: ?string,
 *     discount_code?: ?string,
 *     external_order_id?: ?string,
 *     external_api_client_id?: ?int,
 *     created_by?: ?int,
 * }  $data
 */
class TicketIssuer
{
    public function __construct(private readonly DiscountService $discounts) {}

    public function issue(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];

            if (empty($items)) {
                throw new TicketingException('حداقل یک قلم بلیت باید انتخاب شود.');
            }

            $order = Order::create([
                'source_type' => $data['source_type'],
                'external_order_id' => $data['external_order_id'] ?? null,
                'external_api_client_id' => $data['external_api_client_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'currency' => 'IRR',
            ]);

            $lines = [];
            $originalTotal = 0;

            foreach ($items as $item) {
                $variant = TicketVariant::with('product')->findOrFail($item['ticket_variant_id']);
                $quantity = max(1, (int) $item['quantity']);
                $price = $variant->currentPrice();

                if (! $price) {
                    throw new TicketingException("قیمتی برای «{$variant->name}» تعریف نشده است.");
                }

                if (! $variant->is_active || ! $variant->product->is_active) {
                    throw new TicketingException("«{$variant->name}» در حال حاضر برای فروش فعال نیست.");
                }

                $lineOriginal = $price->amount * $quantity;
                $originalTotal += $lineOriginal;

                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $price->amount,
                    'original_amount' => $lineOriginal,
                ];
            }

            $discountCode = null;
            $discountTotal = 0;

            if (! empty($data['discount_code'])) {
                $discountCode = $this->discounts->lockAndValidate(
                    $data['discount_code'],
                    $originalTotal,
                    $data['customer_phone'] ?? null,
                );
            }

            foreach ($lines as &$line) {
                $lineDiscount = 0;

                if ($discountCode && $this->discounts->variantIsEligible($discountCode, $line['variant'])) {
                    $lineDiscount = $discountCode->calculateDiscount($line['original_amount']);
                }

                $line['discount_amount'] = $lineDiscount;
                $line['final_amount'] = $line['original_amount'] - $lineDiscount;
                $discountTotal += $lineDiscount;
            }

            unset($line);

            foreach ($lines as $line) {
                /** @var TicketVariant $variant */
                $variant = $line['variant'];

                $orderItem = $order->items()->create([
                    'product_id' => $variant->product_id,
                    'ticket_variant_id' => $variant->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'original_amount' => $line['original_amount'],
                    'discount_amount' => $line['discount_amount'],
                    'final_amount' => $line['final_amount'],
                ]);

                $activeVersion = $variant->ticketTemplate?->activeVersion();
                $validFrom = now();
                $validUntil = $validFrom->copy()->addDays($variant->default_validity_days);

                for ($i = 0; $i < $line['quantity']; $i++) {
                    $ticket = AdmissionTicket::create([
                        'order_item_id' => $orderItem->id,
                        'ticket_variant_id' => $variant->id,
                        'status' => AdmissionTicketStatus::Active,
                        'allowed_uses' => $variant->allowed_uses,
                        'used_uses' => 0,
                        'valid_from' => $validFrom,
                        'valid_until' => $validUntil,
                        'source_type' => $data['source_type'],
                        'ticket_template_version_id' => $activeVersion?->id,
                        'customer_name' => $data['customer_name'] ?? null,
                        'customer_phone' => $data['customer_phone'] ?? null,
                        'external_ticket_id' => $data['external_ticket_id'] ?? null,
                    ]);

                    QrToken::create([
                        'admission_ticket_id' => $ticket->id,
                        'token' => QrToken::generateToken(),
                    ]);
                }
            }

            if ($discountCode) {
                $this->discounts->recordUsage($discountCode, $order, $discountTotal, $data['customer_phone'] ?? null);
            }

            $order->update([
                'discount_code_id' => $discountCode?->id,
                'discount_code_snapshot' => $discountCode?->code,
                'discount_type_snapshot' => $discountCode?->discount_type,
                'discount_value_snapshot' => $discountCode?->discount_value,
                'original_amount' => $originalTotal,
                'discount_amount' => $discountTotal,
                'final_amount' => $originalTotal - $discountTotal,
            ]);

            return $order->fresh(['items.tickets.qrToken', 'items.ticketVariant', 'items.product']);
        });
    }

    /**
     * A free issuance (section 31-36) still goes through the exact same
     * order/ticket/QR machinery above (so it prints, scans, and reports
     * through the same pipeline as any other ticket) - the only
     * difference is final_amount is forced to 0 and a ComplimentaryIssue
     * row exists, which is what every report/query must key off of to
     * tell it apart from an ordinary sale (never final_amount === 0
     * alone - that's also true of a 100%-off DiscountCode).
     */
    public function issueComplimentary(
        Product $product,
        TicketVariant $variant,
        int $quantity,
        string $reason,
        User $issuedBy,
        ?string $recipientName = null,
        ?string $recipientPhone = null,
        ?string $notes = null,
    ): Order {
        return DB::transaction(function () use ($product, $variant, $quantity, $reason, $issuedBy, $recipientName, $recipientPhone, $notes) {
            $price = $variant->currentPrice();
            $nominalValue = ($price?->amount ?? 0) * $quantity;

            $order = $this->issue([
                'source_type' => TicketSourceType::Complimentary,
                'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $quantity]],
                'customer_name' => $recipientName,
                'customer_phone' => $recipientPhone,
                'created_by' => $issuedBy->id,
            ]);

            // The normal issue() flow already charged the variant's real
            // price into original/final_amount - a Complimentary order is
            // never actually owed, so force it to zero after the fact
            // rather than teaching issue() a "free" mode that every other
            // caller would have to remember not to trigger.
            $order->update(['discount_amount' => $order->original_amount, 'final_amount' => 0]);
            $order->items->each(fn ($item) => $item->update(['discount_amount' => $item->original_amount, 'final_amount' => 0]));

            $order->complimentaryIssues()->create([
                'product_id' => $product->id,
                'ticket_variant_id' => $variant->id,
                'quantity' => $quantity,
                'original_value' => $nominalValue,
                'reason' => $reason,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'notes' => $notes,
                'issued_by' => $issuedBy->id,
                'issued_at' => now(),
            ]);

            return $order->fresh(['items.tickets.qrToken', 'complimentaryIssues']);
        });
    }
}
