<?php

namespace App\Models;

use App\Enums\DigitalProjectLogKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One free-text line of the daily journal of a digital project. */
class DigitalProjectLog extends Model
{
    use HasFactory;

    protected $fillable = ['digital_project_id', 'kind', 'body', 'logged_on', 'user_id'];

    protected function casts(): array
    {
        return [
            'kind' => DigitalProjectLogKind::class,
            'logged_on' => 'date',
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
