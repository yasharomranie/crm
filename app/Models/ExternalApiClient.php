<?php

namespace App\Models;

use Database\Factories\ExternalApiClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExternalApiClient extends Model
{
    /** @use HasFactory<ExternalApiClientFactory> */
    use HasFactory;

    protected $fillable = [
        'client_name', 'company', 'terminal_id', 'api_key', 'api_secret_hash',
        'allowed_operations', 'is_active', 'rate_limit_per_minute', 'last_used_at', 'created_by',
    ];

    protected $hidden = ['api_secret_hash'];

    protected function casts(): array
    {
        return [
            'allowed_operations' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function allowedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'external_api_client_product');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Creates the client and returns [$client, $plainSecret] - the plain
     * secret exists only in that return value and is never persisted or
     * loggable again (section 103: "Secret واقعی نباید قابل مشاهده مجدد
     * باشد").
     *
     * @param  array<int, string>  $allowedOperations
     * @return array{0: self, 1: string}
     */
    public static function issue(
        string $clientName,
        array $allowedOperations,
        ?string $company = null,
        ?int $rateLimitPerMinute = null,
        ?int $createdBy = null,
    ): array {
        $plainSecret = Str::random(40);

        $client = static::create([
            'client_name' => $clientName,
            'company' => $company,
            'api_key' => 'pk_'.Str::random(32),
            'api_secret_hash' => Hash::make($plainSecret),
            'allowed_operations' => $allowedOperations,
            'is_active' => true,
            'rate_limit_per_minute' => $rateLimitPerMinute ?? 60,
            'created_by' => $createdBy,
        ]);

        return [$client, $plainSecret];
    }

    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->api_secret_hash);
    }

    /**
     * Rotates the secret in place (same reasoning as issue(): the plain
     * value only ever exists in this return value, never persisted or
     * loggable again) - for when a client's secret has leaked or been
     * lost, without having to reissue a whole new api_key too.
     */
    public function regenerateSecret(): string
    {
        $plainSecret = Str::random(40);

        $this->update(['api_secret_hash' => Hash::make($plainSecret)]);

        return $plainSecret;
    }

    public function hasPermission(string $operation): bool
    {
        return in_array($operation, $this->allowed_operations ?? [], true);
    }

    /**
     * Empty allowedProducts() means unrestricted (section 103's "empty =
     * every product" convention, matching DiscountCode's own product
     * restriction).
     */
    public function isProductAllowed(int $productId): bool
    {
        if ($this->allowedProducts()->doesntExist()) {
            return true;
        }

        return $this->allowedProducts()->whereKey($productId)->exists();
    }
}
