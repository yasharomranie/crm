<?php

namespace App\Models;

use App\Enums\LoyaltyTier;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id 1) holding the wallet rules that used to be hard-coded:
 * the cashback percentage per loyalty tier and how long earned cashback
 * stays valid. The defaults reproduce the old behaviour exactly (2% for
 * everyone, never expires).
 */
class WalletSetting extends Model
{
    protected $fillable = [
        'cashback_bronze_percent', 'cashback_silver_percent', 'cashback_gold_percent', 'cashback_vip_percent',
        'cashback_expiry_days', 'expiry_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'cashback_bronze_percent' => 'float',
            'cashback_silver_percent' => 'float',
            'cashback_gold_percent' => 'float',
            'cashback_vip_percent' => 'float',
            'cashback_expiry_days' => 'integer',
            'expiry_reminder_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function cashbackPercentFor(LoyaltyTier $tier): float
    {
        return match ($tier) {
            LoyaltyTier::Bronze => $this->cashback_bronze_percent,
            LoyaltyTier::Silver => $this->cashback_silver_percent,
            LoyaltyTier::Gold => $this->cashback_gold_percent,
            LoyaltyTier::Vip => $this->cashback_vip_percent,
        };
    }
}
