<?php

namespace App\Models;

use Database\Factories\WebAppDomainSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1) - lets an admin point a domain
 * they own (their own subdomain or a fully separate domain) at the
 * customer-facing وب‌اپ (see CustomerAppController), served at that
 * domain's root instead of this app's own /app path. The main app's
 * /app route always keeps working regardless of this setting.
 */
class WebAppDomainSetting extends Model
{
    /** @use HasFactory<WebAppDomainSettingFactory> */
    use HasFactory;

    protected $fillable = ['domain', 'is_enabled', 'dns_verified_at'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'dns_verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'domain' => null,
            'is_enabled' => false,
            'dns_verified_at' => null,
        ]);
    }

    /**
     * Whether requests for `domain` should currently be served as the
     * customer app's root - requires the toggle on AND a DNS check that
     * has actually passed (a domain typed in but never verified must
     * not silently start being treated as this app's own).
     */
    public function isActiveFor(?string $host): bool
    {
        return $this->is_enabled
            && $this->dns_verified_at !== null
            && $this->domain !== null
            && $host !== null
            && strcasecmp($this->domain, $host) === 0;
    }
}
