<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApproverType: string implements HasLabel
{
    case Role = 'role';
    case SpecificUser = 'specific_user';
    case DepartmentManager = 'department_manager';

    public function getLabel(): string
    {
        return match ($this) {
            self::Role => 'نقش سازمانی',
            self::SpecificUser => 'کاربر مشخص',
            self::DepartmentManager => 'مدیر دپارتمان درخواست‌دهنده',
        };
    }
}
