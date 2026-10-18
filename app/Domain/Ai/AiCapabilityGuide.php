<?php

namespace App\Domain\Ai;

use App\Models\User;

/**
 * "What can I ask?" - the parts of the system the assistant can report on
 * for this user (the ones whose module they may list), each with two
 * example questions to click.
 */
class AiCapabilityGuide
{
    private const EXAMPLES = [
        'bookings' => ['ورودی‌ها و خروجی‌های فردا کدام مهمان‌هاست؟', 'نرخ اشغال ۷ روز آینده چقدر است؟'],
        'sales-channels' => ['کانال‌های فروش چه سهمی از رزروها دارند؟'],
        'guests' => ['مشتریانی که رزرو لغو کرده‌اند و کیف پول دارند را پیدا کن', 'کل بدهی کیف پول مشتریان چقدر است؟'],
        'guest-tickets' => ['کدام تیکت‌های پشتیبانی از مهلتشان گذشته؟'],
        'deals' => ['عملکرد فروش هر کارشناس را مقایسه کن'],
        'guest-feedback' => ['رضایت مشتریان (NPS) این ماه چطور است؟'],
        'attractions' => ['تریپ‌پلنر چقدر استفاده شده و جاذبه‌های پرطرفدار کدامند؟'],
        'seo-projects' => ['وضعیت سئو را خلاصه کن', 'کدام کلیدواژه‌ها بیشترین افت رتبه را داشتند؟'],
        'projects' => ['بودجه و هزینهٔ واقعی پروژه‌ها را مقایسه کن', 'کدام فاز پروژه‌ها عقب افتاده؟'],
        'attendance-records' => ['امروز چه کسانی هنوز ورود نزده‌اند؟', 'بیشترین تأخیر این ماه با چه کسانی است؟'],
        'tasks' => ['کدام وظایف عقب افتاده‌اند و مسئولشان کیست؟', 'یک وظیفه برای پیگیری بیمه بساز'],
        'form-submissions' => ['کدام درخواست‌ها از مهلتشان گذشته؟', 'گلوگاه گردش‌کارها کجاست؟'],
        'workflow-definitions' => ['وضعیت سلامت گردش‌کارها چطور است؟'],
        'form-templates' => ['فیلدهای فرم پذیرش را نشان بده'],
        'orders' => ['فروش بلیت دیروز چقدر بود؟', 'آیا صندوق دیشب مغایرت داشت؟'],
    ];

    /**
     * @return list<array{label: string, examples: list<string>}>
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $guide = [];

        foreach (AiToolAccess::moduleLabels() as $module => $label) {
            if ($user->can("{$module}.view_any") && isset(self::EXAMPLES[$module])) {
                $guide[] = ['label' => $label, 'examples' => self::EXAMPLES[$module]];
            }
        }

        return $guide;
    }
}
