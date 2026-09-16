<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'department_id', 'company_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The holding company this folder belongs to - set directly (unlike
     * Document::company(), which is only ever resolved indirectly through
     * a department) so a top-level "which holding is this" folder can
     * exist even with no department of its own, e.g. the auto-archived
     * root folder FormSubmission::archive() creates per company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }
}
