<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoProject;
use App\Models\SeoWpPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What a project's own WordPress site already contains, as the connector
 * plugin reported it, put to work where ideas are made: the prompt of the
 * keyword and content generators lists the pages that exist (so nothing is
 * suggested twice), and a suggestion that is exactly a page's focus keyword
 * is dropped.
 */
class WpContentInventory
{
    private const STOP_WORDS = ['و', 'به', 'از', 'در', 'با', 'که', 'را', 'برای', 'این', 'آن', 'یا', 'تا', 'بر', 'هم', 'ها', 'های'];

    /** @var list<array{0: string, 1: int}> a suffix and how many letters must remain after cutting it */
    private const SUFFIXES = [['ترین', 3], ['های', 2], ['ها', 2], ['ان', 4], ['ات', 4], ['ی', 3]];

    private const PROMPT_PAGES = 150;

    private const PROMPT_DRAFTS = 40;

    /**
     * @return string a block for an AI prompt, or '' when the project has no WordPress content yet
     */
    public function promptSection(SeoProject $project): string
    {
        $published = $this->published($project)->take(self::PROMPT_PAGES);

        if ($published->isEmpty()) {
            return '';
        }

        $lines = $published->map(function (SeoWpPost $post) {
            $focus = $post->seo['focus_keyword'] ?? '';
            $line = '- '.Str::limit($post->title, 90, '…');

            if ($focus !== '') {
                $line .= " — کلمه کانونی: {$focus}";
            }

            if ($post->gsc_clicks !== null) {
                $line .= " — {$post->gsc_clicks} کلیک در ۲۸ روز";
            }

            return $line;
        })->implode("\n");

        $drafts = $this->unpublished($project)->take(self::PROMPT_DRAFTS)
            ->map(fn (SeoWpPost $post) => '- '.Str::limit($post->title, 90, '…'))->implode("\n");

        $section = <<<TXT
            Pages this business has ALREADY published on its own WordPress site (the real inventory its WordPress plugin sent: title, focus keyword and Google clicks over the last 28 days). Never suggest a keyword or topic one of these pages already targets - if an existing page covers the idea, the right move is to improve that page, not to write a new one, so leave the idea out. Prefer angles these pages do not cover:
            {$lines}
            TXT;

        if ($drafts !== '') {
            $section .= <<<TXT


                Drafts and scheduled posts already in progress on the site (never duplicate them either):
                {$drafts}
                TXT;
        }

        return $section;
    }

    /**
     * Whether a published page already has exactly this focus keyword.
     */
    public function isTargeted(SeoProject $project, string $keyword): bool
    {
        return in_array(self::canonical($keyword), $this->focusKeywordSet($project), true);
    }

    /**
     * @return list<string> normalised focus keywords of the project's published pages
     */
    public function focusKeywordSet(SeoProject $project): array
    {
        return $this->published($project)
            ->map(fn (SeoWpPost $post) => self::canonical((string) ($post->seo['focus_keyword'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One spelling for comparing Persian text: Arabic ي/ك become ی/ک, half-spaces
     * and extra spaces go, digits become Latin, case is ignored.
     */
    public static function normalize(string $text): string
    {
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', "\u{200C}" => ' ', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? '');
    }

    /**
     * The words of a text as they are compared: split on anything that is
     * not a letter or digit, filler words dropped, and each word reduced to
     * a light Persian stem so «راهنمای سفر» and «راهنما سفر»,
     * «غارها» and «غار» are the same. Deliberately conservative - both sides
     * of a comparison go through the same function, so what matters is
     * consistency, not linguistic correctness.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stems = [];

        foreach ($words as $word) {
            if (in_array($word, self::STOP_WORDS, true)) {
                continue;
            }

            $stems[] = self::stem($word);
        }

        return $stems;
    }

    /**
     * One string for comparing two phrases: their stems joined by a space.
     */
    public static function canonical(string $text): string
    {
        return implode(' ', self::tokens($text));
    }

    private static function stem(string $word): string
    {
        foreach (self::SUFFIXES as [$suffix, $minRemaining]) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= $minRemaining) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * @return Collection<int, SeoWpPost>
     */
    private function published(SeoProject $project): Collection
    {
        return $this->posts($project)
            ->where('status', 'publish')
            ->whereIn('post_type', ['post', 'page', 'product'])
            ->orderByRaw('gsc_clicks is null')
            ->orderByDesc('gsc_clicks')
            ->orderByDesc('modified_at')
            ->get(['title', 'seo', 'gsc_clicks', 'status']);
    }

    /**
     * @return Collection<int, SeoWpPost>
     */
    private function unpublished(SeoProject $project): Collection
    {
        return $this->posts($project)
            ->where('status', '!=', 'publish')
            ->orderByDesc('modified_at')
            ->get(['title', 'status']);
    }

    private function posts(SeoProject $project)
    {
        return SeoWpPost::query()->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id));
    }
}
