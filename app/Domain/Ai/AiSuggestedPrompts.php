<?php

namespace App\Domain\Ai;

/**
 * The starter questions shown in an empty assistant conversation. They follow
 * the module the manager was last working in (see AiPageContextResolver) so
 * the first click is already relevant; with no known module the general set
 * is shown.
 */
class AiSuggestedPrompts
{
    private const GENERAL = [
        'وضعیت کلی امروز رو خلاصه کن و هشدارها رو بگو',
        'گزارش اشغال و درآمد اقامتگاه این ماه',
        'وضعیت پروژه‌های فعال از نظر بودجه و زمان‌بندی چطوره؟',
        'کارمندهایی که این ماه بیشترین تأخیر رو داشتن کیا هستن؟',
    ];

    private const BY_MODEL = [
        'Booking' => [
            'ورودی‌ها و خروجی‌های امروز و فردا کدام مهمان‌هاست؟',
            'نرخ اشغال ۷ روز آینده چقدر است؟',
            'کدام مهمان‌ها باید چک‌اوت می‌کردند و نکرده‌اند؟',
        ],
        'Guest' => [
            'مشتریان VIP این ماه چه کسانی هستند؟',
            'رضایت مشتریان (NPS) این ماه چطور است؟',
            'کدام تیکت‌های پشتیبانی از مهلتشان گذشته؟',
        ],
        'Deal' => [
            'عملکرد فروش هر کارشناس را مقایسه کن',
            'مشتریان از چه راه‌هایی به ما می‌رسند؟',
        ],
        'Task' => [
            'کدام وظایف عقب افتاده‌اند و مسئولشان کیست؟',
            'بار کاری افراد را با هم مقایسه کن',
        ],
        'Project' => [
            'وضعیت بودجه و زمان‌بندی پروژه‌ها چطور است؟',
            'کدام فاز پروژه‌ها عقب افتاده است؟',
        ],
        'AttendanceRecord' => [
            'امروز چه کسانی هنوز ورود نزده‌اند؟',
            'بیشترین تأخیر این ماه با چه کسانی است؟',
            'چه کسانی خروج نزده‌اند؟',
        ],
        'FormSubmission' => [
            'کدام درخواست‌ها از مهلتشان گذشته و مسئولشان کیست؟',
            'گلوگاه گردش‌کارها کجاست؟',
        ],
        'FormTemplate' => [
            'کدام درخواست‌ها از مهلتشان گذشته و مسئولشان کیست؟',
            'گلوگاه گردش‌کارها کجاست؟',
        ],
        'SeoProject' => [
            'وضعیت سئو را خلاصه کن',
            'کدام کلیدواژه‌ها بیشترین بهبود یا افت رتبه را داشتند؟',
            'چه فرصت‌های سئویی باز مانده است؟',
        ],
    ];

    /**
     * @param  array{resource?: ?class-string, record_title?: ?string, module?: string}|null  $context
     * @return list<string>
     */
    public static function for(?array $context): array
    {
        $resource = $context['resource'] ?? null;
        $model = $resource && method_exists($resource, 'getModel') ? class_basename($resource::getModel()) : null;
        $prompts = self::BY_MODEL[$model] ?? null;

        if ($prompts === null) {
            return self::GENERAL;
        }

        $title = $context['record_title'] ?? null;

        return array_values(array_filter([
            filled($title) ? "درباره‌ی «{$title}» خلاصه و نکات مهم را بگو" : null,
            ...$prompts,
            self::GENERAL[0],
        ]));
    }
}
