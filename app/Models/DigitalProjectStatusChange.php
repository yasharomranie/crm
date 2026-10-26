<?php

namespace App\Models;

use App\Enums\DigitalProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The history of a project's status: every change with its time, author and optional reason. */
class DigitalProjectStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['digital_project_id', 'from_status', 'to_status', 'note', 'changed_by'];

    protected function casts(): array
    {
        return [
            'from_status' => DigitalProjectStatus::class,
            'to_status' => DigitalProjectStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DigitalProject::class, 'digital_project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
