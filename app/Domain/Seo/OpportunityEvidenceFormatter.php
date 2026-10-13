<?php

namespace App\Domain\Seo;

/**
 * Persian labels for the plain snake_case action codes OpportunityEngine/
 * UrlOpportunityEngine write into SeoOpportunity::$recommended_actions,
 * and for the most common SeoOpportunity::$evidence keys - both were
 * shown raw and in English inside an otherwise fully-Persian RTL panel
 * (resources/views/filament/seo/opportunity-evidence.blade.php). Kept
 * here rather than inline in that Blade view: an @php block with a
 * match() expression inside that view's @foreach loop is what broke
 * Livewire's wire:key loop compiler on SeoActionQueue's own table (see
 * that fix's commit) - logic like this belongs in a plain PHP class,
 * never inline control structures inside a Livewire-tracked loop body.
 *
 * An unmapped code/key falls back to the raw string rather than hiding
 * it - a new detector's action/evidence key showing up untranslated is
 * far less harmful than one silently disappearing from the panel.
 */
class OpportunityEvidenceFormatter
{
    /**
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'review_title' => 'بازبینی عنوان صفحه',
        'review_meta' => 'بازبینی توضیحات متا',
        'review_search_intent' => 'بررسی تطابق قصد جستجو',
        'improve_content' => 'بهبود محتوای صفحه',
        'review_serp' => 'بررسی نحوه‌ی نمایش در نتایج گوگل (SERP)',
        'check_technical_issues' => 'بررسی مشکلات فنی سایت',
        'check_serp_competition' => 'بررسی رقابت در نتایج گوگل',
        'check_seasonality' => 'بررسی احتمال فصلی‌بودن',
        'review_top_declining_pages' => 'بررسی صفحات پراُفت',
        'identify_growth_driver' => 'شناسایی علت رشد',
        'capitalize_on_trend' => 'استفاده از این روند رو به رشد',
        'check_search_console_manual_actions' => 'بررسی اقدامات دستی گوگل سرچ کنسول',
        'check_indexing' => 'بررسی وضعیت ایندکس‌شدن',
        'check_robots_and_sitemap' => 'بررسی robots.txt و sitemap.xml',
        'check_backlinks' => 'بررسی بک‌لینک‌ها',
        'choose_primary_page' => 'انتخاب صفحه‌ی اصلی',
        'differentiate_or_merge_content' => 'متفاوت‌سازی یا ادغام محتوا',
        'update_internal_links_to_primary' => 'اصلاح لینک‌های داخلی به‌سمت صفحه‌ی اصلی',
        'review_content_relevance' => 'بازبینی ارتباط محتوا با موضوع',
        'review_internal_links' => 'بازبینی لینک‌های داخلی',
        'consider_merging_or_redirecting' => 'بررسی ادغام یا ریدایرکت صفحه',
        'check_indexing_status' => 'بررسی وضعیت ایندکس‌شدن در گوگل',
        'check_internal_links' => 'بررسی لینک‌های داخلی به این صفحه',
    ];

    /**
     * @var array<string, string>
     */
    private const EVIDENCE_KEY_LABELS = [
        'keyword' => 'کلمه‌ی کلیدی',
        'position' => 'رتبه',
        'impressions_28d' => 'ایمپرشن (۲۸ روز اخیر)',
        'clicks_28d' => 'کلیک (۲۸ روز اخیر)',
        'ctr_28d' => 'نرخ کلیک - CTR (۲۸ روز اخیر)',
        'benchmark_ctr_for_position' => 'CTR مرجع برای این رتبه',
        'estimated_monthly_clicks_if_fixed' => 'برآورد کلیک ماهانه در صورت رفع',
        'landing_page' => 'صفحه‌ی فرودی',
        'current_week_clicks' => 'کلیک هفته‌ی جاری',
        'previous_week_clicks' => 'کلیک هفته‌ی قبل',
        'percent_change' => 'درصد تغییر',
        'top_declining_queries' => 'پراُفت‌ترین کلمات کلیدی',
        'top_declining_pages' => 'پراُفت‌ترین صفحات',
        'date' => 'تاریخ',
        'value' => 'مقدار',
        'expected_range' => 'بازه‌ی طبیعی مورد انتظار',
        'z_score' => 'نمره‌ی Z (میزان غیرعادی بودن)',
        'pages' => 'صفحات رقیب برای این کلمه',
        'page_url' => 'آدرس صفحه',
        'published_days_ago' => 'روز از انتشار',
        'best_position' => 'بهترین رتبه‌ی ثبت‌شده',
        'topic' => 'موضوع',
        'cluster_average_clicks' => 'میانگین کلیک این خوشه',
        'clicks_same_week_last_year' => 'کلیک همین بازه در سال قبل',
        'percent_change_vs_last_year' => 'درصد تغییر نسبت به سال قبل',
    ];

    public static function actionLabel(string $code): string
    {
        return self::ACTION_LABELS[$code] ?? $code;
    }

    public static function evidenceKeyLabel(string $key): string
    {
        return self::EVIDENCE_KEY_LABELS[$key] ?? $key;
    }
}
