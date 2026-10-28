<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What the visitor's phone reports: only the outcome, never a picture. */
enum GuideEventType: string implements HasLabel
{
    case ScanMatched = 'scan_matched';
    case ScanUnmatched = 'scan_unmatched';
    case ScanError = 'scan_error';
    case VideoPlay = 'video_play';

    public function getLabel(): string
    {
        return match ($this) {
            self::ScanMatched => 'شناسایی موفق',
            self::ScanUnmatched => 'شناسایی نشد',
            self::ScanError => 'خطا',
            self::VideoPlay => 'تماشای ویدیو',
        };
    }
}
