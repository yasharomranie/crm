<?php

namespace App\Models;

use App\Enums\GateRejectionReason;
use App\Enums\GateScanResult;
use Database\Factories\GateOfflineScanSyncFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateOfflineScanSync extends Model
{
    /** @use HasFactory<GateOfflineScanSyncFactory> */
    use HasFactory;

    protected $fillable = [
        'gate_id', 'client_scan_id', 'admission_ticket_id', 'result', 'reason',
        'usage_number', 'local_result', 'local_reason', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => GateScanResult::class,
            'reason' => GateRejectionReason::class,
            'local_result' => GateScanResult::class,
            'local_reason' => GateRejectionReason::class,
            'scanned_at' => 'datetime',
        ];
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    /**
     * Whether the browser's own offline guess turned out to match what the
     * server, as the real source of truth, actually decided once synced -
     * false means a real discrepancy (e.g. the same ticket was also
     * scanned at another gate while this one was offline) worth a staff
     * member's attention.
     */
    public function isReconciled(): bool
    {
        return $this->result === $this->local_result && $this->reason === $this->local_reason;
    }
}
