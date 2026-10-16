<?php

namespace App\Models;

use App\Enums\DealSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public, no-login form (served at /lead/{slug}) whose submissions land
 * as a Guest + a NewLead Deal - the browser-facing counterpart of the
 * API-key-protected /api/v1/external/deals endpoint.
 */
class LeadCaptureForm extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'title', 'description', 'success_message', 'deal_source', 'assigned_to', 'is_active'];

    protected function casts(): array
    {
        return [
            'deal_source' => DealSource::class,
            'is_active' => 'boolean',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function publicUrl(): string
    {
        return route('lead-capture.show', $this->slug);
    }
}
