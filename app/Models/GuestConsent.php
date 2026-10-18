<?php

namespace App\Models;

use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a guest's marketing-consent history. Rows are only ever
 * added: the current answer for a channel is the newest row, and the older
 * ones are the proof of when, how and by whom consent was given or
 * withdrawn.
 */
class GuestConsent extends Model
{
    public const STATUS_GRANTED = 'granted';

    public const STATUS_REVOKED = 'revoked';

    public const SOURCES = [
        'lead_form' => 'فرم جذب سرنخ',
        'customer_app' => 'وب‌اپ مشتری',
        'staff' => 'ثبت توسط کارمند',
        'import' => 'درون‌ریزی',
    ];

    protected $fillable = ['guest_id', 'channel', 'status', 'source', 'note', 'ip_address', 'recorded_by'];

    protected function casts(): array
    {
        return ['channel' => MessageChannel::class];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
