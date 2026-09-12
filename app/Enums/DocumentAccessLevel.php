<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentAccessLevel: string implements HasLabel
{
    case PublicInternal = 'public_internal';
    case DepartmentOnly = 'department_only';
    case Restricted = 'restricted';

    public function getLabel(): string
    {
        return match ($this) {
            self::PublicInternal => 'قابل مشاهده برای همه پرسنل',
            self::DepartmentOnly => 'مخصوص دپارتمان',
            self::Restricted => 'محدود (فقط افراد مجاز)',
        };
    }
}
