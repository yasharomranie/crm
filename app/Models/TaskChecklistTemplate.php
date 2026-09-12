<?php

namespace App\Models;

use App\Enums\ChecklistRecurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskChecklistTemplate extends Model
{
    protected $fillable = ['name', 'department_id', 'recurrence', 'is_active'];

    protected function casts(): array
    {
        return [
            'recurrence' => ChecklistRecurrence::class,
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }
}
