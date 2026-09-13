<?php

namespace App\Models;

use Database\Factories\ContractCompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the user's own legal entities/companies, usable as the "کارفرما"
 * (employer) side of a generated contract. Kept dynamic/admin-managed
 * since the organization operates more than one company.
 */
class ContractCompany extends Model
{
    /** @use HasFactory<ContractCompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'national_id',
        'economic_code',
        'address',
        'representative_name',
        'representative_role',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * A short block of identifying details for the AI prompt, e.g.:
     * "نام: شرکت گنج‌نامه / شناسه ملی: ... / نماینده: ...".
     */
    public function identitySummary(): string
    {
        return collect([
            "نام: {$this->name}",
            $this->national_id ? "شناسه ملی/کد ملی: {$this->national_id}" : null,
            $this->economic_code ? "کد اقتصادی: {$this->economic_code}" : null,
            $this->address ? "نشانی: {$this->address}" : null,
            $this->representative_name ? "نمایندهٔ قانونی: {$this->representative_name}".
                ($this->representative_role ? " ({$this->representative_role})" : '') : null,
        ])->filter()->implode("\n");
    }
}
