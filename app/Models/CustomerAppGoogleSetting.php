<?php

namespace App\Models;

use Database\Factories\CustomerAppGoogleSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1), mirroring SeoGoogleSetting -
 * App\Domain\CustomerApp\CustomerGoogleOAuthService reads the OAuth client
 * credentials for "ورود با گوگل" in the وب‌اپ مشتریان from here. Deliberately
 * its own Google Cloud OAuth app/credentials, separate from SeoGoogleSetting:
 * that one authorizes Search Console access on behalf of staff, this one
 * signs customers into their own account - sharing one OAuth app between
 * the two would mix an internal admin integration with a public login flow.
 */
class CustomerAppGoogleSetting extends Model
{
    /** @use HasFactory<CustomerAppGoogleSettingFactory> */
    use HasFactory;

    protected $fillable = ['client_id', 'client_secret'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return filled($this->client_id) && filled($this->client_secret);
    }
}
