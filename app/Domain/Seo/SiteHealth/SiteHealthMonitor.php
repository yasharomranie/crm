<?php

namespace App\Domain\Seo\SiteHealth;

use App\Domain\Seo\SeoAlertService;
use App\Models\SeoProject;
use App\Models\SeoSiteCheck;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks every active project's website "are you there?" and reads its
 * certificate's end date, then says something only when it is new: a site
 * that failed twice in a row went down, a down site answered again, a
 * certificate crossed 14, 7, 3 or 1 days. With a hundred sites that is what
 * keeps this useful instead of noisy.
 */
class SiteHealthMonitor
{
    /** A site is "down" after this many failed checks in a row, so one slow answer does not page anyone. */
    public const FAILURES_BEFORE_DOWN = 2;

    /** Days left at which a certificate is worth saying something about, largest first. */
    public const SSL_ALERT_DAYS = [14, 7, 3, 1];

    private const BATCH_SIZE = 10;

    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly SeoAlertService $alerts,
        private readonly SslExpiryReader $ssl,
    ) {}

    /**
     * @return int how many sites were checked
     */
    public function run(): int
    {
        $projects = SeoProject::query()
            ->where('is_active', true)
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '')
            ->get();

        foreach ($projects->chunk(self::BATCH_SIZE) as $batch) {
            $answers = $this->ask($batch);

            foreach ($batch as $project) {
                $this->record($project, $answers[$project->id] ?? null);
            }
        }

        return $projects->count();
    }

    /**
     * @param  Collection<int, SeoProject>  $batch
     * @return array<int, Response|Throwable|null>
     */
    private function ask(Collection $batch): array
    {
        $responses = Http::pool(fn (Pool $pool) => $batch->map(
            fn (SeoProject $project) => $pool->as((string) $project->id)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'FarsiWP-SiteMonitor/1.0'])
                ->get($this->address($project)),
        )->all());

        $byProject = [];

        foreach ($batch as $project) {
            $byProject[$project->id] = $responses[(string) $project->id] ?? null;
        }

        return $byProject;
    }

    private function record(SeoProject $project, Response|Throwable|null $answer): void
    {
        $check = SeoSiteCheck::firstOrNew(['seo_project_id' => $project->id]);
        $wasDown = $check->exists && $check->alerted_down_at !== null;

        $up = $answer instanceof Response && $answer->status() < 500;

        $check->fill([
            'is_up' => $up,
            'http_status' => $answer instanceof Response ? $answer->status() : null,
            'response_ms' => $answer instanceof Response ? (int) round(($answer->transferStats?->getTransferTime() ?? 0) * 1000) : null,
            'error' => $up ? null : $this->errorText($answer),
            'consecutive_failures' => $up ? 0 : ((int) $check->consecutive_failures) + 1,
            'checked_at' => now(),
        ]);

        $host = parse_url($this->address($project), PHP_URL_HOST);
        $isHttps = str_starts_with($this->address($project), 'https://');

        if ($up && $isHttps && $host) {
            $check->ssl_expires_at = $this->ssl->expiresAt($host) ?? $check->ssl_expires_at;
        }

        $check->save();

        if (! $up && $check->consecutive_failures >= self::FAILURES_BEFORE_DOWN && ! $wasDown) {
            $check->update(['alerted_down_at' => now()]);
            $this->alerts->notifySiteHealth($project, "🔴 سایت «{$project->name}» در دسترس نیست ({$check->error}).");
        } elseif ($up && $wasDown) {
            $check->update(['alerted_down_at' => null]);
            $this->alerts->notifySiteHealth($project, "🟢 سایت «{$project->name}» دوباره در دسترس است.");
        }

        $this->alertAboutCertificate($project, $check);
    }

    private function alertAboutCertificate(SeoProject $project, SeoSiteCheck $check): void
    {
        $daysLeft = $check->sslDaysLeft();

        if ($daysLeft === null) {
            return;
        }

        if ($daysLeft > self::SSL_ALERT_DAYS[0]) {
            if ($check->alerted_ssl_days !== null) {
                $check->update(['alerted_ssl_days' => null]);
            }

            return;
        }

        $threshold = collect(self::SSL_ALERT_DAYS)->filter(fn (int $days) => $daysLeft <= $days)->min();

        if ($check->alerted_ssl_days !== null && $check->alerted_ssl_days <= $threshold) {
            return;
        }

        $check->update(['alerted_ssl_days' => $threshold]);

        $this->alerts->notifySiteHealth($project, $daysLeft >= 0
            ? "🔒 گواهی SSL سایت «{$project->name}» {$daysLeft} روز دیگر منقضی می‌شود."
            : "🔒 گواهی SSL سایت «{$project->name}» منقضی شده است.");
    }

    private function address(SeoProject $project): string
    {
        $url = trim((string) $project->website_url);

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
    }

    private function errorText(Response|Throwable|null $answer): string
    {
        return match (true) {
            $answer instanceof Response => 'پاسخ HTTP '.$answer->status(),
            $answer instanceof Throwable => 'اتصال برقرار نشد',
            default => 'بدون پاسخ',
        };
    }
}
