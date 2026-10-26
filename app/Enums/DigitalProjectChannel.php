<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a digital project lives. The case order is also the order of the
 * groups in the project table.
 */
enum DigitalProjectChannel: string implements HasLabel
{
    case Website = 'website';
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case Whatsapp = 'whatsapp';
    case Linkedin = 'linkedin';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => 'سایت',
            self::Instagram => 'اینستاگرام',
            self::Telegram => 'تلگرام',
            self::Whatsapp => 'واتس‌اپ',
            self::Linkedin => 'لینکدین',
            self::Other => 'سایر',
        };
    }

    /** The heading of this channel's group in the project table. */
    public function groupTitle(): string
    {
        return match ($this) {
            self::Website => '🌐 سایت‌ها',
            self::Instagram => '📸 اینستاگرام',
            self::Telegram => '✈️ تلگرام',
            self::Whatsapp => '💬 واتس‌اپ',
            self::Linkedin => '💼 لینکدین',
            self::Other => '📁 سایر',
        };
    }

    /** A SQL "case" that sorts the channel column in the order of the cases above. */
    public static function orderSql(string $column = 'channel'): string
    {
        $whens = collect(self::cases())
            ->map(fn (self $channel, int $index) => "when '{$channel->value}' then {$index}")
            ->implode(' ');

        return "case {$column} {$whens} else ".count(self::cases()).' end';
    }
}
