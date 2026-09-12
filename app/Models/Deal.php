<?php

namespace App\Models;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    use HasTags;

    protected $fillable = [
        'guest_id', 'title', 'stage', 'value', 'assigned_to',
        'source', 'expected_close_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'source' => DealSource::class,
            'value' => 'decimal:0',
            'expected_close_date' => 'date',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
