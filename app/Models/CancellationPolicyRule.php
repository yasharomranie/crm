<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationPolicyRule extends Model
{
    protected $fillable = ['cancellation_policy_id', 'min_days_before', 'penalty_percent'];

    protected function casts(): array
    {
        return [
            'min_days_before' => 'integer',
            'penalty_percent' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }
}
