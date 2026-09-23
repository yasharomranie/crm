<?php

namespace App\Models;

use Database\Factories\TicketTemplateElementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplateElement extends Model
{
    /** @use HasFactory<TicketTemplateElementFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_template_version_id', 'type', 'content',
        'x', 'y', 'width', 'height', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function ticketTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TicketTemplateVersion::class);
    }
}
