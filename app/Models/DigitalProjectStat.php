<?php

namespace App\Models;

use App\Enums\DigitalProjectMetric;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hand-entered reading of a channel number (followers, views...) on a given day. */
class DigitalProjectStat extends Model
{
    use HasFactory;

    protected $fillable = ['digital_project_id', 'metric', 'value', 'recorded_on', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'metric' => DigitalProjectMetric::class,
            'value' => 'integer',
            'recorded_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DigitalProject::class, 'digital_project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
