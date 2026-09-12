<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkflowActionType: string implements HasLabel
{
    case Approve = 'approve';
    case Reject = 'reject';
    case ReferParallel = 'refer_parallel';
    case Comment = 'comment';
    case Cancel = 'cancel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Approve => 'تأیید',
            self::Reject => 'رد',
            self::ReferParallel => 'ارجاع موازی',
            self::Comment => 'ثبت نظر',
            self::Cancel => 'لغو',
        };
    }
}
