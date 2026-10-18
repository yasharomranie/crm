<?php

namespace App\Models;

use Database\Factories\TicketVariantPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TicketVariantPrice extends Model
{
    /** @use HasFactory<TicketVariantPriceFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->useLogName('ticket_variant_price');
    }

    protected $fillable = ['ticket_variant_id', 'amount', 'currency', 'applies_to', 'effective_from', 'effective_until', 'created_by'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function ticketVariant(): BelongsTo
    {
        return $this->belongsTo(TicketVariant::class);
    }
}
