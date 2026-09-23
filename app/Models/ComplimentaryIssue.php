<?php

namespace App\Models;

use Database\Factories\ComplimentaryIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ComplimentaryIssue extends Model
{
    /** @use HasFactory<ComplimentaryIssueFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->useLogName('complimentary_issue');
    }

    protected $fillable = [
        'order_id', 'product_id', 'ticket_variant_id', 'quantity', 'original_value',
        'reason', 'recipient_name', 'recipient_phone', 'notes', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ticketVariant(): BelongsTo
    {
        return $this->belongsTo(TicketVariant::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
