<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the reservation reached us. `Ota` is included now so the schema
 * doesn't need a migration once the Channel Manager phase wires up real
 * OTA integrations - until then it's just another manual option.
 */
enum BookingSource: string implements HasLabel
{
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Online = 'online';
    case Ota = 'ota';

    public function getLabel(): string
    {
        return match ($this) {
            self::WalkIn => 'حضوری',
            self::Phone => 'تلفنی',
            self::Online => 'وب‌سایت',
            self::Ota => 'سایت واسط (OTA)',
        };
    }
}
