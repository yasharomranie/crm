<?php

namespace App\Domain\Ai;

/**
 * Sending ~70 tool definitions with every question is most of the prompt's
 * cost. When the manager's question clearly concerns some modules, only
 * those modules' tools (plus the cross-cutting ones, the action tools and
 * the tools already used earlier in the conversation) are offered. If no
 * module is recognised the full set is sent, so a vague question never
 * loses a tool it needs.
 */
class AiToolRouter
{
    /** @var array<string, list<string>> module => words that point at it */
    private const KEYWORDS = [
        'bookings' => ['رزرو', 'اتاق', 'اشغال', 'اقامت', 'مهمان', 'چک‌اوت', 'چک اوت', 'چک‌این', 'کنسل', 'لغو', 'ورودی', 'خروجی', 'درآمد'],
        'sales-channels' => ['کانال', 'اوتای', 'ota'],
        'guests' => ['مشتری', 'باشگاه', 'کیف پول', 'کیف‌پول', 'موبایل', 'vip', 'وفاداری', 'کش‌بک', 'کشبک'],
        'guest-tickets' => ['تیکت', 'پشتیبانی', 'sla'],
        'deals' => ['معامله', 'فروش', 'کارشناس', 'پایپ‌لاین', 'پایپ لاین', 'سرنخ', 'لید'],
        'guest-feedback' => ['رضایت', 'nps', 'بازخورد', 'نظرسنجی'],
        'attractions' => ['تریپ', 'سفر', 'جاذبه', 'گردشگر'],
        'seo-projects' => ['سئو', 'کلیدواژه', 'رتبه', 'ترافیک', 'گوگل', 'سرچ', 'ایندکس', 'بک‌لینک'],
        'projects' => ['پروژه', 'بودجه', 'فاز', 'هزینه', 'evm'],
        'attendance-records' => ['حضور', 'غیاب', 'تأخیر', 'تاخیر', 'اضافه‌کاری', 'اضافه کاری', 'شیفت', 'ورود نزد', 'خروج نزد', 'کارمند'],
        'tasks' => ['وظیفه', 'تسک', 'کارهای', 'عقب', 'اولویت'],
        'form-submissions' => ['فرم', 'درخواست', 'تأیید', 'تایید', 'گردش', 'مهلت', 'رد شد', 'چک‌لیست', 'چک لیست', 'صورتجلسه'],
        'workflow-definitions' => ['گردش', 'مرحله', 'تأییدکننده'],
        'form-templates' => ['فرم', 'فیلد', 'قالب', 'سند'],
        'orders' => ['بلیت', 'گیشه', 'صندوق', 'بلیط'],
    ];

    /**
     * @param  array<int, array{type: string, function: array{name: string}}>  $tools
     * @param  list<string>  $recentlyUsedTools  tool names already used in this conversation
     * @return array<int, array{type: string, function: array{name: string}}>
     */
    public function select(array $tools, string $question, array $recentlyUsedTools = []): array
    {
        if (! config('ai_assistant.tool_routing', true)) {
            return $tools;
        }

        $matched = $this->modulesMentioned($question);

        if ($matched === []) {
            return $tools;
        }

        foreach ($recentlyUsedTools as $name) {
            $module = AiToolAccess::moduleOf($name);

            if ($module !== null) {
                $matched[$module] = true;
            }
        }

        return array_values(array_filter($tools, function (array $tool) use ($matched) {
            $module = AiToolAccess::moduleOf($tool['function']['name']);

            return $module === null || isset($matched[$module]);
        }));
    }

    /**
     * @return array<string, true>
     */
    private function modulesMentioned(string $question): array
    {
        $text = mb_strtolower(str_replace(['ي', 'ك'], ['ی', 'ک'], $question));
        $matched = [];

        foreach (self::KEYWORDS as $module => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    $matched[$module] = true;

                    break;
                }
            }
        }

        return $matched;
    }
}
