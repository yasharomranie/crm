<?php

namespace App\Domain\CustomerApp;

use App\Models\GuestOtpCode;
use App\Support\MobileNumber;
use App\Support\SmsGateway;

/**
 * SMS one-time-password login for the وب‌اپ مشتریان (see
 * App\Http\Controllers\CustomerApp\AuthController) - request-rate limiting
 * lives at the route level (the "customer-otp" limiter registered in
 * AppServiceProvider), this class only owns code generation/expiry/attempt
 * bookkeeping. A code's delivery going through SmsGateway is attempted but
 * never required to succeed here, matching SmsGateway's own rule that a
 * failed/unconfigured gateway must never break the feature it's one channel
 * of - verifyOtp() still works against whatever was stored, which is also
 * what lets tests read the code straight out of the database.
 */
class OtpService
{
    private const EXPIRY_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function requestOtp(string $mobile): GuestOtpCode
    {
        $mobile = MobileNumber::canonical($mobile) ?? $mobile;

        // Previous outstanding codes for this number are no longer usable
        // once a new one is requested - otherwise an old, still-unexpired
        // code sitting in an intercepted SMS stays valid indefinitely.
        GuestOtpCode::where('mobile', $mobile)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $otp = GuestOtpCode::create([
            'mobile' => $mobile,
            'code' => (string) random_int(100000, 999999),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        SmsGateway::send($mobile, "کد ورود شما به گنج‌نامه: {$otp->code}\nاین کد تا ".self::EXPIRY_MINUTES.' دقیقه معتبر است.');

        return $otp;
    }

    public function verifyOtp(string $mobile, string $code): bool
    {
        $mobile = MobileNumber::canonical($mobile) ?? $mobile;

        $otp = GuestOtpCode::where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            return false;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->update(['consumed_at' => now()]);

            return false;
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code, $code)) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
