<?php

namespace App\Models;

use App\Enums\TicketDirection;
use Database\Factories\TicketUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketUsage extends Model
{
    /** @use HasFactory<TicketUsageFactory> */
    use HasFactory;

    protected $fillable = ['admission_ticket_id', 'gate_id', 'user_id', 'usage_number', 'direction'];

    protected function casts(): array
    {
        return ['direction' => TicketDirection::class];
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
