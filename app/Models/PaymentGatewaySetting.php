<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id 1) with the online payment gateway (Zarinpal)
 * credentials used for wallet top-ups, entered once from the admin panel.
 */
class PaymentGatewaySetting extends Model
{
    protected $fillable = ['is_enabled', 'merchant_id', 'is_sandbox', 'min_topup_amount', 'max_topup_amount'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_sandbox' => 'boolean',
            'merchant_id' => 'encrypted',
            'min_topup_amount' => 'integer',
            'max_topup_amount' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isUsable(): bool
    {
        return $this->is_enabled && filled($this->merchant_id);
    }
}
