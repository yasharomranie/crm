<?php

namespace App\Models;

use Database\Factories\ContractCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dynamically admin-managed "routine contract" type (e.g. پیمانکاری,
 * پشتیبانی سایت, اجاره...). ai_guidance lets an admin steer the AI draft
 * for this category specifically (e.g. clauses that type of contract must
 * always include) without any code change.
 */
class ContractCategory extends Model
{
    /** @use HasFactory<ContractCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'ai_guidance'];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
