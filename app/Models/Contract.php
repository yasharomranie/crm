<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An AI-drafted contract, archived for later review/reprinting. The audit
 * is deliberately NOT AI-generated: it's a fixed, deterministic keyword
 * check (mirrors AiAssistant::computeAlerts()) so the "N warnings" count
 * behaves the same way every time, independent of model output.
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $fillable = [
        'contract_category_id',
        'contract_company_id',
        'counterparty_name',
        'title',
        'brief',
        'body',
        'audit_warnings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audit_warnings' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'contract_category_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(ContractCompany::class, 'contract_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The standard clauses every routine contract is expected to carry.
     * Each entry's keywords are matched loosely (str_contains) against the
     * drafted text; if none of a clause's keyword variants are found, it
     * is flagged as missing.
     *
     * @return array<string, array{label: string, keywords: array<int, string>}>
     */
    public static function requiredClauseRules(): array
    {
        return [
            'parties' => [
                'label' => 'مشخصات طرفین',
                'keywords' => ['طرفین قرارداد', 'طرفین معامله', 'کارفرما', 'پیمانکار', 'موجر', 'مستأجر'],
            ],
            'duration_amount' => [
                'label' => 'مدت و مبلغ',
                'keywords' => ['مدت قرارداد', 'مدت اجرا', 'مبلغ قرارداد', 'مبلغ کل'],
            ],
            'penalty' => [
                'label' => 'وجه التزام',
                'keywords' => ['وجه التزام', 'خسارت تأخیر', 'جریمه تأخیر'],
            ],
            'liability_insurance' => [
                'label' => 'بیمه مسئولیت',
                'keywords' => ['بیمه مسئولیت', 'بیمه حوادث', 'مسئولیت مدنی'],
            ],
            'dispute_resolution' => [
                'label' => 'حل اختلاف',
                'keywords' => ['حل اختلاف', 'داوری', 'مرجع رسیدگی', 'مراجع قضائی'],
            ],
            'deductions' => [
                'label' => 'کسورات',
                'keywords' => ['کسورات', 'کسور قانونی', 'مالیات بر ارزش افزوده', 'بیمه تأمین اجتماعی'],
            ],
        ];
    }

    /**
     * Runs the deterministic audit against a piece of contract text.
     *
     * @return array<int, array{key: string, label: string, message: string}> the MISSING clauses only
     */
    public static function auditText(string $body): array
    {
        $missing = [];

        foreach (static::requiredClauseRules() as $key => $rule) {
            $found = collect($rule['keywords'])->contains(
                fn (string $keyword) => Str::contains($body, $keyword),
            );

            if (! $found) {
                $missing[] = [
                    'key' => $key,
                    'label' => $rule['label'],
                    'message' => "در پیش‌نویس، بند «{$rule['label']}» یافت نشد یا ناقص است.",
                ];
            }
        }

        return $missing;
    }
}
