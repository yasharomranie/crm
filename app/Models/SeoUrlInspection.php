<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stored result of one URL Inspection API call (see
 * App\Domain\Seo\UrlInspectionService) for one page of one project -
 * upserted per page_url, so re-inspecting the same URL just refreshes it
 * instead of piling up history.
 */
class SeoUrlInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'page_url', 'page_url_hash', 'verdict', 'robots_txt_state',
        'indexing_state', 'page_fetch_state', 'coverage_state', 'google_canonical',
        'user_canonical', 'last_crawl_time', 'inspected_at', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'last_crawl_time' => 'datetime',
            'inspected_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function isIndexed(): bool
    {
        return $this->verdict === 'PASS';
    }

    /**
     * A full, plain-language Persian explanation of why this page is or
     * isn't indexed, built from Google's own enumerated fields (never
     * from `coverage_state`, which is free text Google can change without
     * notice - that one is only ever shown as extra raw context, in
     * parentheses, in the caller's own view).
     */
    public function explanationInPersian(): string
    {
        $sentences = [];

        $sentences[] = match ($this->verdict) {
            'PASS' => 'این صفحه توسط گوگل ایندکس شده و می‌تواند در نتایج جستجو نمایش داده شود.',
            'PARTIAL' => 'این صفحه فقط تا حدی ایندکس شده - یعنی گوگل آن را دیده، اما همه‌ی جوانب آن (مثل موبایل یا AMP) لزوماً تأیید نشده.',
            'FAIL' => 'این صفحه ایندکس نشده و در نتایج جستجوی گوگل نمایش داده نمی‌شود.',
            'NEUTRAL' => 'گوگل برای این صفحه نتیجه‌ی قطعی اعلام نکرده (نه رد و نه تأیید).',
            default => 'وضعیت ایندکس این صفحه هنوز از گوگل مشخص نشده - معمولاً یعنی این URL هنوز کراول نشده است.',
        };

        if ($this->robots_txt_state === 'DISALLOWED') {
            $sentences[] = 'فایل robots.txt سایت، دسترسی گوگل به این آدرس را مسدود کرده است - تا این مسدودسازی برداشته نشود، گوگل اصلاً نمی‌تواند این صفحه را ایندکس کند.';
        }

        $sentences[] = match ($this->indexing_state) {
            'INDEXING_ALLOWED' => null,
            'BLOCKED_BY_META_TAG' => 'یک تگ meta robots (مثلاً noindex) داخل خود صفحه، ایندکس‌شدنش را ممنوع کرده است.',
            'BLOCKED_BY_HTTP_HEADER' => 'هدر HTTP پاسخ این صفحه (X-Robots-Tag) ایندکس‌شدنش را ممنوع کرده است.',
            'BLOCKED_BY_ROBOTS_TXT' => 'robots.txt به‌طور مشخص ایندکس‌شدن این صفحه را ممنوع کرده است.',
            default => null,
        };

        $sentences[] = match ($this->page_fetch_state) {
            'SUCCESSFUL', null => null,
            'SOFT_404' => 'گوگل این صفحه را "Soft 404" تشخیص داده - یعنی صفحه HTTP 200 برمی‌گرداند ولی محتوایش شبیه یک صفحه‌ی خالی/پیدا-نشد به نظر می‌رسد.',
            'NOT_FOUND' => 'وقتی گوگل این آدرس را باز کرده، با خطای ۴۰۴ (پیدا نشد) روبه‌رو شده است.',
            'ACCESS_DENIED', 'ACCESS_FORBIDDEN' => 'سرور اجازه‌ی دسترسی به این صفحه را به گوگل نداده (خطای دسترسی/۴۰۳).',
            'SERVER_ERROR' => 'سرور هنگام بارگذاری این صفحه با خطای داخلی (۵xx) پاسخ داده است.',
            'REDIRECT_ERROR' => 'ریدایرکت این صفحه به‌درستی کار نمی‌کند (مثلاً حلقه‌ی ریدایرکت).',
            'BLOCKED_4XX' => 'سرور با یک خطای ۴xx دیگر (غیر از ۴۰۳/۴۰۴) پاسخ داده است.',
            'INTERNAL_CRAWL_ERROR' => 'یک خطای داخلی سمت گوگل هنگام کراول این صفحه رخ داده - معمولاً موقتی است.',
            'INVALID_URL' => 'این آدرس از نظر گوگل نامعتبر است.',
            default => null,
        };

        if (filled($this->google_canonical) && filled($this->user_canonical) && $this->google_canonical !== $this->user_canonical) {
            $sentences[] = "گوگل آدرس دیگری را به‌عنوان نسخه‌ی اصلی (canonical) این محتوا انتخاب کرده - نه همان آدرسی که خودت در تگ canonical مشخص کرده‌ای ({$this->user_canonical})، بلکه {$this->google_canonical} را.";
        }

        if (filled($this->coverage_state)) {
            $sentences[] = "توضیح خام گوگل (Coverage state): «{$this->coverage_state}».";
        }

        return implode(' ', array_filter($sentences));
    }
}
