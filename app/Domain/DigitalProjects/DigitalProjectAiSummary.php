<?php

namespace App\Domain\DigitalProjects;

use App\Enums\DigitalProjectActionStatus;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\DigitalProject;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

/**
 * A short Persian summary of the last days of work across the digital
 * projects, for a manager who has no time to read every journal line.
 *
 * Token-conscious by construction: only a few short lines per project, a hard
 * cap on how many projects are sent, and only on demand (never scheduled).
 */
class DigitalProjectAiSummary
{
    /** Projects sent to the model; the busiest and most troubled come first. */
    public const MAX_PROJECTS = 25;

    private const LOG_LINES_PER_PROJECT = 4;

    private const LINE_CHARS = 160;

    /**
     * @throws AiProviderException
     */
    public function summarize(int $days = 7): string
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => 'تو دستیار مدیر فناوری یک مجموعه هستی و گزارش کار پروژه‌های دیجیتال (سایت و شبکه‌های اجتماعی) را برای مدیر خلاصه می‌کنی. فقط از داده‌هایی که می‌دهم استفاده کن، چیزی از خودت نساز و اگر داده‌ای نیست همین را بگو. فارسی، ساده، بدون Markdown.'],
            ['role' => 'user', 'content' => $this->buildPrompt($days)],
        ]);

        return trim((string) ($message['content'] ?? ''));
    }

    public function buildPrompt(int $days = 7): string
    {
        $since = today()->subDays($days - 1);

        $projects = DigitalProject::query()
            ->with([
                'logs' => fn ($logs) => $logs->whereDate('logged_on', '>=', $since)->orderByDesc('logged_on')->orderByDesc('id'),
                'actions' => fn ($actions) => $actions->whereIn('status', DigitalProjectActionStatus::unfinishedValues()),
                'stats',
                'seoProject.siteCheck',
            ])
            ->whereNotIn('status', ['done'])
            ->get()
            ->sortByDesc(fn (DigitalProject $project) => $project->logs->count() + ($project->status->value === 'needs_attention' ? 100 : 0))
            ->take(self::MAX_PROJECTS);

        $blocks = $projects->map(function (DigitalProject $project): string {
            $lines = ["پروژه «{$project->name}» ({$project->channel->getLabel()}، وضعیت: {$project->status->getLabel()})"];

            foreach ($project->logs->take(self::LOG_LINES_PER_PROJECT) as $log) {
                $lines[] = '  - '.Jalalian::fromCarbon($log->logged_on)->format('m/d').' ['.$log->kind->getLabel().'] '.Str::limit(preg_replace('/\s+/u', ' ', $log->body), self::LINE_CHARS);
            }

            if ($project->logs->isEmpty()) {
                $lines[] = '  - در این بازه گزارشی ثبت نشده';
            }

            $overdue = $project->actions->filter->isOverdue();

            if ($project->actions->isNotEmpty()) {
                $lines[] = '  - اقدام باز: '.$project->actions->count().($overdue->isNotEmpty() ? " (از مهلت گذشته: {$overdue->count()})" : '');
            }

            if ($trends = $project->statTrendsText()) {
                $lines[] = "  - آمار: {$trends}";
            }

            if ($health = $project->siteHealth()) {
                $lines[] = "  - وضعیت سایت: {$health['label']}";
            }

            return implode("\n", $lines);
        });

        $body = $blocks->isEmpty() ? '(پروژه‌ای ثبت نشده است)' : $blocks->implode("\n\n");

        return "گزارش {$days} روز گذشته:\n\n{$body}\n\n".'یک خلاصهٔ ۱۵۰ تا ۲۵۰ کلمه‌ای بنویس: اول کلیت هفته، بعد پروژه‌هایی که مشکل یا عقب‌افتادگی دارند، بعد ۲ تا ۳ پیشنهاد مشخص برای هفتهٔ بعد. فقط متن خلاصه را برگردان.';
    }
}
