<?php

namespace App\Domain\Seo\WpConnector;

use Illuminate\Support\Carbon;

/**
 * What is worth fixing on a WordPress page, from what the plugin reported
 * and what Search Console says about it. Only published pages are judged,
 * and a check that needs data the site owner did not share (or that Search
 * Console has not delivered) is skipped rather than guessed.
 */
class WpPostIssues
{
    public const THIN_WORDS = 300;

    /** @var array<string, array{label: string, color: string}> */
    public const ISSUES = [
        'noindex' => ['label' => 'noindex فعال است', 'color' => 'danger'],
        'not_in_search' => ['label' => 'بدون نمایش در گوگل', 'color' => 'danger'],
        'traffic_drop' => ['label' => 'افت ترافیک', 'color' => 'danger'],
        'orphan' => ['label' => 'یتیم (بدون لینک داخلی)', 'color' => 'warning'],
        'no_meta_description' => ['label' => 'بدون توضیح متا', 'color' => 'warning'],
        'no_focus_keyword' => ['label' => 'بدون کلمه کلیدی کانونی', 'color' => 'warning'],
        'low_ctr' => ['label' => 'CTR پایین در صفحه اول', 'color' => 'warning'],
        'thin_content' => ['label' => 'محتوای کم', 'color' => 'gray'],
        'images_missing_alt' => ['label' => 'تصویر بدون alt', 'color' => 'gray'],
        'striking_distance' => ['label' => 'نزدیک به صفحه اول', 'color' => 'success'],
    ];

    /**
     * @param  array{status: string, post_type: string, url: string, word_count: int, seo: ?array, images: ?array, inbound: ?int, published_at: ?Carbon, gsc: ?array{clicks: int, impressions: int, ctr: float, position: float, prev_clicks: int}, has_gsc: bool}  $facts
     * @return list<string> issue codes, most serious first
     */
    public static function detect(array $facts): array
    {
        if ($facts['status'] !== 'publish') {
            return [];
        }

        $issues = [];
        $seo = $facts['seo'];
        $gsc = $facts['gsc'];
        $isArticle = $facts['post_type'] === 'post';
        $isFront = WpUrl::isFrontPage($facts['url']);

        if ($seo !== null && ! empty($seo['noindex'])) {
            $issues[] = 'noindex';
        }

        $oldEnough = $facts['published_at'] && $facts['published_at']->lt(now()->subDays(30));

        if ($facts['has_gsc'] && $oldEnough && ($gsc === null || $gsc['impressions'] === 0) && empty($seo['noindex'])) {
            $issues[] = 'not_in_search';
        }

        if ($gsc && $gsc['prev_clicks'] >= 20 && $gsc['clicks'] <= $gsc['prev_clicks'] * 0.6) {
            $issues[] = 'traffic_drop';
        }

        if ($facts['inbound'] === 0 && ! $isFront) {
            $issues[] = 'orphan';
        }

        if ($seo !== null && blank($seo['description'] ?? null)) {
            $issues[] = 'no_meta_description';
        }

        if ($seo !== null && blank($seo['focus_keyword'] ?? null) && in_array($facts['post_type'], ['post', 'product'], true)) {
            $issues[] = 'no_focus_keyword';
        }

        if ($gsc && $gsc['position'] <= 10 && $gsc['impressions'] >= 200 && $gsc['ctr'] < 0.01) {
            $issues[] = 'low_ctr';
        }

        if ($isArticle && $facts['word_count'] < self::THIN_WORDS) {
            $issues[] = 'thin_content';
        }

        if ($facts['images'] !== null && ($facts['images']['missing_alt'] ?? 0) > 0) {
            $issues[] = 'images_missing_alt';
        }

        if ($gsc && $gsc['position'] > 10 && $gsc['position'] <= 20 && $gsc['impressions'] >= 50) {
            $issues[] = 'striking_distance';
        }

        return $issues;
    }

    public static function label(string $code): string
    {
        return self::ISSUES[$code]['label'] ?? $code;
    }

    public static function color(string $code): string
    {
        return self::ISSUES[$code]['color'] ?? 'gray';
    }
}
