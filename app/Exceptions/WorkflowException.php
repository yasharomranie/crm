<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by WorkflowInstance::act() when the requested approval action
 * doesn't make sense: the instance already finished, or the acting user
 * isn't one of the current step's resolved approvers.
 */
class WorkflowException extends RuntimeException
{
    public static function alreadyFinished(): self
    {
        return new self('این درخواست قبلاً به نتیجه رسیده و دیگر قابل اقدام نیست.');
    }

    public static function noPendingStep(): self
    {
        return new self('مرحلهٔ در انتظاری برای این درخواست یافت نشد.');
    }

    public static function notAnEligibleApprover(): self
    {
        return new self('شما در مرحلهٔ فعلی این درخواست، مجاز به اقدام نیستید.');
    }

    public static function onlySubmitterCanCancel(): self
    {
        return new self('فقط ثبت‌کنندهٔ درخواست می‌تواند آن را لغو کند.');
    }

    public static function commentRequiredForReferral(): self
    {
        return new self('برای ارجاع موازی، ذکر دلیل الزامی است.');
    }
}
