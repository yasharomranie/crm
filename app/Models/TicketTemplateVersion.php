<?php

namespace App\Models;

use App\Enums\TicketTemplateStatus;
use Database\Factories\TicketTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TicketTemplateVersion extends Model
{
    /** @use HasFactory<TicketTemplateVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_template_id', 'version_number', 'status', 'width_mm', 'height_mm',
        'background_image', 'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketTemplateStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version) {
            $version->version_number ??= 1 + (int) static::where('ticket_template_id', $version->ticket_template_id)->max('version_number');
        });
    }

    public function ticketTemplate(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(TicketTemplateElement::class)->orderBy('sort_order');
    }

    /**
     * Only one version of a template can be ACTIVE (issuable) at a time
     * (section 48) - publishing this one archives whichever version held
     * that spot before, atomically, so there's never a moment with two
     * (or zero) active versions.
     */
    public function publish(): void
    {
        DB::transaction(function () {
            static::where('ticket_template_id', $this->ticket_template_id)
                ->where('status', TicketTemplateStatus::Active)
                ->update(['status' => TicketTemplateStatus::Archived]);

            $this->update(['status' => TicketTemplateStatus::Active, 'published_at' => now()]);
        });
    }
}
