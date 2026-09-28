<?php

namespace App\Models;

use Database\Factories\SeoAlertSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A singleton settings row (always id=1), mirroring SmsSetting/
 * SeoRankTrackerSetting - App\Domain\Seo\SeoAlertService reads the alert
 * thresholds/channels/recipients from here, edited from "تنظیمات هشدار
 * سئو" in the panel.
 */
class SeoAlertSetting extends Model
{
    /** @use HasFactory<SeoAlertSettingFactory> */
    use HasFactory;

    protected $fillable = ['is_enabled', 'rank_drop_threshold', 'notify_email', 'notify_sms', 'notify_push', 'recipient_user_ids'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'rank_drop_threshold' => 'integer',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_push' => 'boolean',
            'recipient_user_ids' => 'array',
        ];
    }

    /**
     * The default values are passed explicitly (matching the migration's
     * own column defaults) rather than relying on firstOrCreate(['id' =>
     * 1]) alone: on the very first call ever, Eloquent's create() returns
     * the same in-memory instance it just inserted, and never re-fetches
     * it - so any attribute *not* explicitly passed here reads back as
     * null on that instance (e.g. is_enabled ends up null, not false)
     * even though the row's real column default (0) landed correctly in
     * the database. Passing them here keeps the in-memory object correct
     * from the first call too, without needing a second query.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'is_enabled' => false,
            'rank_drop_threshold' => 5,
            'notify_email' => true,
            'notify_sms' => false,
            'notify_push' => true,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        if (blank($this->recipient_user_ids)) {
            return collect();
        }

        return User::query()->whereIn('id', $this->recipient_user_ids)->get();
    }
}
