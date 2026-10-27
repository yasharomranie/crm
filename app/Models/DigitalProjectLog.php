<?php

namespace App\Models;

use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectWorkCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One free-text line of the daily journal of a digital project. */
class DigitalProjectLog extends Model
{
    use HasFactory;

    protected $fillable = ['digital_project_id', 'kind', 'body', 'logged_on', 'user_id', 'work_category'];

    protected function casts(): array
    {
        return [
            'kind' => DigitalProjectLogKind::class,
            'logged_on' => 'date',
            'work_category' => DigitalProjectWorkCategory::class,
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
