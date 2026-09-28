<?php

namespace App\Domain\Seo;

use App\Models\SeoKeywordRankCheck;
use App\Models\SeoTrackedKeyword;

/**
 * Runs one tracked keyword's daily check: searches via
 * GoogleCustomSearchClient, then looks for the project's own domain
 * among the result URLs (matched by host, www-insensitive) to determine
 * today's position. A domain not found in the checked results is
 * recorded as position=null rather than guessed at - "not in the top
 * 10" is itself a real, honest result.
 */
class RankCheckService
{
    public function __construct(private readonly GoogleCustomSearchClient $client) {}

    public function check(SeoTrackedKeyword $trackedKeyword): SeoKeywordRankCheck
    {
        $urls = $this->client->search($trackedKeyword->keyword);
        $targetHost = $this->hostOf($trackedKeyword->project->website_url);

        $position = null;
        $foundUrl = null;

        foreach ($urls as $index => $url) {
            if ($targetHost !== null && $this->hostOf($url) === $targetHost) {
                $position = $index + 1;
                $foundUrl = $url;
                break;
            }
        }

        // Not updateOrCreate(): the "date" column is cast to a Carbon date,
        // which stores as "Y-m-d H:i:s" on write, but a plain "Y-m-d"
        // search value never matches that when Eloquent looks for an
        // existing row - it would insert a fresh row every time and hit
        // the unique(seo_tracked_keyword_id, date) constraint on a second
        // check the same day. whereDate() compares by date component
        // regardless of stored format.
        $check = SeoKeywordRankCheck::where('seo_tracked_keyword_id', $trackedKeyword->id)
            ->whereDate('date', now()->toDateString())
            ->first() ?? new SeoKeywordRankCheck([
                'seo_tracked_keyword_id' => $trackedKeyword->id,
                'date' => now()->toDateString(),
            ]);

        $check->fill(['position' => $position, 'found_url' => $foundUrl, 'checked_at' => now()])->save();

        return $check;
    }

    private function hostOf(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        if (! $host) {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
