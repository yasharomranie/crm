<?php

namespace App\Support;

/**
 * One spelling per mobile number. A customer is found by their mobile (OTP
 * login, wallet payment, ticket and lead linking), so 0912..., +98912...,
 * 0098 912... and Persian digits must all mean the same row - otherwise the
 * same person quietly becomes two customers with split wallets and history.
 */
class MobileNumber
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * 09123456789 for every common way of writing one Iranian mobile
     * number, or null if it is not one.
     */
    public static function normalize(?string $mobile): ?string
    {
        if ($mobile === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', strtr(trim($mobile), self::DIGITS)) ?? '';

        $digits = match (true) {
            str_starts_with($digits, '0098') => '0'.substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => '0'.substr($digits, 2),
            strlen($digits) === 10 && str_starts_with($digits, '9') => '0'.$digits,
            default => $digits,
        };

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }

    /**
     * The value to store and to look up by: the normalised Iranian mobile,
     * or - for a number that is not one (a foreign line, a typo) - the text
     * with its digits made Latin and the ends trimmed. Blank becomes null.
     */
    public static function canonical(?string $mobile): ?string
    {
        if ($mobile === null) {
            return null;
        }

        $clean = trim(strtr($mobile, self::DIGITS));

        if ($clean === '') {
            return null;
        }

        return self::normalize($clean) ?? $clean;
    }

    /**
     * What to put between the % of a LIKE: for an Iranian mobile the last
     * ten digits (so 0912..., +98912... and 912... all find the same row),
     * otherwise the text as typed with Latin digits.
     */
    public static function searchFragment(string $term): string
    {
        $normalized = self::normalize($term);

        return $normalized !== null ? substr($normalized, 1) : trim(strtr($term, self::DIGITS));
    }
}
