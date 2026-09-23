<?php

namespace App\Models;

use App\Enums\PrintType;
use Database\Factories\PrintLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model
{
    /** @use HasFactory<PrintLogFactory> */
    use HasFactory;

    protected $fillable = [
        'admission_ticket_id', 'ticket_template_version_id', 'printed_by', 'print_type', 'device', 'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'print_type' => PrintType::class,
            'printed_at' => 'datetime',
        ];
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    public function ticketTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TicketTemplateVersion::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
