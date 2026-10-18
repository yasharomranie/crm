<?php

namespace App\Domain\Ticketing;

use App\Domain\Crm\GuestDuplicateDetector;
use App\Enums\TicketSourceType;
use App\Models\Guest;
use App\Models\Order;
use Illuminate\Support\Number;

/**
 * A ticket bought with a phone number is a customer interaction like any
 * other: the buyer is matched to (or added as) a guest and the purchase
 * lands on their CRM timeline. Free tickets and sales without a usable
 * phone number are left out - there is nobody to attach them to.
 */
class TicketCrmLink
{
    public function record(Order $order): ?Guest
    {
        if ($order->source_type === TicketSourceType::Complimentary) {
            return null;
        }

        $mobile = GuestDuplicateDetector::normalizeMobile($order->customer_phone);

        if ($mobile === null) {
            return null;
        }

        $guest = Guest::query()->where('mobile', $mobile)->first()
            ?? Guest::create(['name' => filled($order->customer_name) ? $order->customer_name : 'مشتری گیشه', 'mobile' => $mobile]);

        activity('crm_interactions')
            ->performedOn($guest)
            ->event('ticket_purchased')
            ->withProperties(['order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => (int) $order->final_amount])
            ->log("خرید بلیت {$order->order_number} به مبلغ ".Number::toPersian(number_format((int) $order->final_amount)).' تومان');

        return $guest;
    }
}
