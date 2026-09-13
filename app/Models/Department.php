<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'code', 'manager_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Resolves ApproverType::DepartmentManager steps in the workflow
     * engine: whoever is set here approves requests from this department.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * This department's own id plus every ancestor's id, walking up via
     * parent_id (e.g. for "پذیرش گنج‌نامه" under "گنج‌نامه", returns both
     * ids). Used to resolve "is this document/folder's department broad
     * enough to cover the current user's department" without a recursive
     * descendants query on every row being checked - a document
     * restricted to a holding-level department (e.g. "گنج‌نامه") is meant
     * to be visible to everyone in any department beneath it, not just an
     * exact department_id match.
     *
     * @return array<int, int>
     */
    public function selfAndAncestorIds(): array
    {
        $ids = [];
        $department = $this;

        // A guard against a corrupt/circular parent_id chain: no real
        // organization nests departments anywhere near this deep.
        $maxDepth = 20;

        while ($department && count($ids) < $maxDepth) {
            $ids[] = $department->id;
            $department = $department->parent;
        }

        return $ids;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
