<?php

namespace App\Models;

use Database\Factories\CustomerAppAuthSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1), mirroring SmsSetting/SeoGoogleSetting -
 * lets an admin turn each of the وب‌اپ مشتریان's three sign-in methods (OTP,
 * mobile/email+password, Google) on or off from "روش‌های ورود" in the panel,
 * without touching code. App\Http\Controllers\CustomerApp\AuthController
 * checks these on every relevant action, not just in the login screen's own
 * markup - a disabled method must stay unreachable even by posting its
 * route directly, not merely hidden from the UI.
 */
class CustomerAppAuthSetting extends Model
{
    /** @use HasFactory<CustomerAppAuthSettingFactory> */
    use HasFactory;

    protected $fillable = ['otp_enabled', 'password_enabled', 'google_enabled'];

    protected function casts(): array
    {
        return [
            'otp_enabled' => 'boolean',
            'password_enabled' => 'boolean',
            'google_enabled' => 'boolean',
        ];
    }

    /**
     * Passes the defaults explicitly on create, rather than relying on the
     * otp_enabled/password_enabled/google_enabled columns' own DB-level
     * defaults - Eloquent's in-memory object never re-reads a just-inserted
     * row, so the very first current() call in a fresh database would
     * otherwise see these as null (falsy) instead of the true the columns
     * actually get written as.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'otp_enabled' => true,
            'password_enabled' => true,
            'google_enabled' => true,
        ]);
    }
}
