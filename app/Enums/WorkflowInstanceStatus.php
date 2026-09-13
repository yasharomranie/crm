<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkflowInstanceStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'در جریان',
            self::Approved => 'تأیید نهایی',
            self::Rejected => 'رد نهایی',
            self::Cancelled => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Maps a finished workflow outcome onto the matching FormSubmission
     * status, for subjects that are form submissions.
     */
    public function toFormSubmissionStatus(): FormSubmissionStatus
    {
        return match ($this) {
            self::Approved => FormSubmissionStatus::Approved,
            self::Rejected => FormSubmissionStatus::Rejected,
            self::Cancelled => FormSubmissionStatus::Cancelled,
            self::InProgress => FormSubmissionStatus::PendingApproval,
        };
    }
}
