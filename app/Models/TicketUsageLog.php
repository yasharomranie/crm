<?php

namespace App\Models;

use App\Enums\GateRejectionReason;
use App\Enums\GateScanResult;
use App\Enums\TicketDirection;
use Database\Factories\TicketUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketUsageLog extends Model
{
    /** @use HasFactory<TicketUsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'admission_ticket_id', 'gate_id', 'user_id', 'direction', 'usage_number',
        'result', 'reason', 'device_identifier', 'ip_address', 'metadata', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TicketDirection::class,
            'result' => GateScanResult::class,
            'reason' => GateRejectionReason::class,
            'metadata' => 'array',
            'scanned_at' => 'datetime',
        ];
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
