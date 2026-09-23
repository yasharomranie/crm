<?php

namespace App\Models;

use App\Enums\TicketTemplateStatus;
use Database\Factories\TicketTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTemplate extends Model
{
    /** @use HasFactory<TicketTemplateFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'created_by'];

    public function versions(): HasMany
    {
        return $this->hasMany(TicketTemplateVersion::class)->orderByDesc('version_number');
    }

    public function activeVersion(): ?TicketTemplateVersion
    {
        return $this->versions()->where('status', TicketTemplateStatus::Active)->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
