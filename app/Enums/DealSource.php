<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DealSource: string implements HasLabel
{
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Online = 'online';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WalkIn => 'حضوری',
            self::Phone => 'تلفنی',
            self::Online => 'وب‌سایت',
            self::Referral => 'معرفی دوستان',
            self::SocialMedia => 'شبکه‌های اجتماعی',
            self::Other => 'سایر',
        };
    }
}
