<?php

namespace App\Domain\Seo;

use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoProject;
use Illuminate\Support\Collection;

/**
 * Writes the narrative a human SEO consultant would hand a client -
 * built on the same numbers PeriodicReportService already computes and
 * the same priority-ordered findings SeoActionQueueService already
 * assembles, never raw numbers alone. Uses SeoAnalystPersona for the
 * shared voice (observation vs hypothesis vs recommendation) so this
 * reads consistently with any other AI-driven analysis in مرکز سئو -
 * existing AI services (AiKeywordClassifier, AiKeywordDiscoveryService)
 * are left on their own prompts rather than migrated onto this.
 *
 * Best-effort by design: PeriodicReportService calls this and swallows
 * any failure (no active AI provider, a failed API call) so a report
 * that's otherwise useful never breaks over a missing narrative - the
 * same reasoning TechnicalSeoAuditor already applies to its own
 * PageSpeed check.
 */
class SeoAiAnalystService
{
    /**
     * @param  array{clicks: int, impressions: int, avg_position: ?float, percent_change: ?float}  $searchPerformance
     * @param  array{detected: int, resolved: int, improved: int, unchanged: int, declined: int}  $opportunitySummary
     * @param  Collection<int, SeoActionItem>  $actionItems
     */
    public function narrativeFor(SeoProject $project, array $searchPerformance, array $opportunitySummary, Collection $actionItems): string
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => SeoAnalystPersona::systemPromptFor($project)],
            ['role' => 'user', 'content' => $this->buildDataSummary($searchPerformance, $opportunitySummary, $actionItems)],
        ]);

        return trim((string) ($message['content'] ?? ''));
    }

    /**
     * @param  array{clicks: int, impressions: int, avg_position: ?float, percent_change: ?float}  $searchPerformance
     * @param  array{detected: int, resolved: int, improved: int, unchanged: int, declined: int}  $opportunitySummary
     * @param  Collection<int, SeoActionItem>  $actionItems
     */
    private function buildDataSummary(array $searchPerformance, array $opportunitySummary, Collection $actionItems): string
    {
        $topItems = $actionItems->isEmpty()
            ? '(در حال حاضر هیچ موردی در صف اقدام نیست)'
            : $actionItems->take(10)
                ->map(fn (SeoActionItem $item) => "- [{$item->source}] {$item->title}".($item->detail ? " ({$item->detail})" : ''))
                ->implode("\n");

        $percentChange = $searchPerformance['percent_change'] !== null ? "{$searchPerformance['percent_change']}%" : 'نامشخص (دوره‌ی قبل داده‌ای ندارد)';
        $avgPosition = $searchPerformance['avg_position'] !== null ? $searchPerformance['avg_position'] : 'نامشخص';

        return <<<TXT
            داده‌های این دوره:
            - کلیک: {$searchPerformance['clicks']} (تغییر نسبت به دوره‌ی قبل: {$percentChange})
            - ایمپرشن: {$searchPerformance['impressions']}
            - میانگین رتبه: {$avgPosition}
            - فرصت شناسایی‌شده در این دوره: {$opportunitySummary['detected']}
            - فرصت برطرف‌شده در این دوره: {$opportunitySummary['resolved']}
            - اقدامی که واقعاً نتیجه داد (بهبود اندازه‌گیری‌شده): {$opportunitySummary['improved']}

            مهم‌ترین موارد باز در صف اقدام فعلی پروژه (به ترتیب اولویت):
            {$topItems}

            یک گزارش روایی فارسی، حدوداً ۱۵۰ تا ۲۵۰ کلمه، برای صاحب کسب‌وکار بنویس که این داده‌ها را خلاصه کند و ۲ تا ۳ پیشنهاد عملی و مشخص از صف اقدام بالا ارائه دهد. فقط متن گزارش را برگردان، بدون مقدمه یا توضیح اضافه، بدون Markdown.
            TXT;
    }
}
