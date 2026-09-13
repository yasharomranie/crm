<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by User::checkIn()/checkOut() when the requested action doesn't
 * make sense given today's attendance record (already checked in, trying
 * to check out before checking in, etc).
 */
class AttendanceException extends RuntimeException
{
    public static function alreadyCheckedInToday(): self
    {
        return new self('امروز قبلاً ورود خود را ثبت کرده‌اید.');
    }

    public static function notCheckedInYet(): self
    {
        return new self('ابتدا باید ورود امروز خود را ثبت کنید.');
    }

    public static function alreadyCheckedOutToday(): self
    {
        return new self('امروز قبلاً خروج خود را ثبت کرده‌اید.');
    }
}
