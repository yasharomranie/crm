<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the holding's own legal entities/companies (e.g. "گنج‌نامه",
 * "زاگرس", "هتل بوتیک اوا") - the organization's single central place to
 * define "which companies do we have". Usable directly as the "کارفرما"
 * (employer) side of a generated contract, and as the company a
 * department belongs to; anything that already carries a department_id
 * (documents, projects, ...) resolves its company through that
 * department rather than needing its own company_id column.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
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

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
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
