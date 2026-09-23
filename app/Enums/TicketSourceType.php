<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an order/ticket came to exist - kept distinct from discount info:
 * a Complimentary ticket and a 100%-discounted Cashier sale can both end
 * up with final_amount = 0, but they are never the same thing (see
 * ComplimentaryIssue) and reports must never merge them.
 */
enum TicketSourceType: string implements HasLabel
{
    case Cashier = 'cashier';
    case ExternalPos = 'external_pos';
    case Online = 'online';
    case Complimentary = 'complimentary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cashier => 'صندوق',
            self::ExternalPos => 'دستگاه POS',
            self::Online => 'فروش آنلاین',
            self::Complimentary => 'اهدایی/رایگان',
        };
    }
}
