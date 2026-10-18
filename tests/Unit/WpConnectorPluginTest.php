<?php

namespace Tests\Unit;

use App\Domain\Seo\WpConnector\WpSignature;
use FarsiWP\SeoConnector\HtmlAnalyzer;
use FarsiWP\SeoConnector\Signer;
use PHPUnit\Framework\TestCase;

/**
 * The parts of the WordPress plugin that need no WordPress, plus a check that
 * the plugin's signature is exactly the one the CRM verifies.
 */
class WpConnectorPluginTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $directory = dirname(__DIR__, 2).'/wordpress-plugin/farsiwp-seo-connector/includes';

        require_once $directory.'/Signer.php';
        require_once $directory.'/HtmlAnalyzer.php';
    }

    public function test_the_plugin_signature_is_accepted_by_the_crm(): void
    {
        $body = json_encode(['posts' => [['title' => 'همدان']]], JSON_UNESCAPED_UNICODE);
        $timestamp = time();

        $signature = Signer::sign('shared-secret', $timestamp, $body);

        $this->assertTrue(WpSignature::isValid('shared-secret', $timestamp, $body, $signature));
        $this->assertFalse(WpSignature::isValid('another-secret', $timestamp, $body, $signature));
        $this->assertFalse(WpSignature::isValid('shared-secret', $timestamp, $body.' ', $signature));
    }

    public function test_words_are_counted_with_half_spaces_kept_inside_a_word(): void
    {
        $this->assertSame(3, HtmlAnalyzer::countWords('می‌روم به همدان'));
        $this->assertSame(0, HtmlAnalyzer::countWords("  \n "));
    }

    public function test_paragraphs_are_not_glued_together_when_counting(): void
    {
        $result = HtmlAnalyzer::analyze('<h1>سفر به همدان</h1><p>می‌روم به همدان</p><script>var a = "one two three";</script>', 'https://example.test');

        $this->assertSame(6, $result['word_count']);
    }

    public function test_headings_are_listed_with_their_levels(): void
    {
        $result = HtmlAnalyzer::analyze('<h1>سفر</h1><h2>جاذبه‌ها</h2><h2> </h2><h3>غار</h3>', 'https://example.test');

        $this->assertSame([
            ['level' => 1, 'text' => 'سفر'],
            ['level' => 2, 'text' => 'جاذبه‌ها'],
            ['level' => 3, 'text' => 'غار'],
        ], $result['headings']);
    }

    public function test_links_are_split_into_internal_and_external(): void
    {
        $html = '<a href="/ganjnameh">گنجنامه</a> <a href="https://www.example.test/x" rel="nofollow">صفحه</a>'
            .' <a href="https://other.test/y">منبع</a> <a href="mailto:a@b.c">ایمیل</a> <a href="#top">بالا</a>'
            .' <a href="/pic"><img src="c.jpg" alt="تصویر لینک"></a>';

        $result = HtmlAnalyzer::analyze($html, 'https://example.test');

        $this->assertSame(
            ['https://example.test/ganjnameh', 'https://www.example.test/x', 'https://example.test/pic'],
            array_column($result['internal_links'], 'url'),
        );
        $this->assertSame(['گنجنامه', 'صفحه', 'تصویر لینک'], array_column($result['internal_links'], 'anchor'));
        $this->assertSame([false, true, false], array_column($result['internal_links'], 'nofollow'));
        $this->assertSame(['https://other.test/y'], array_column($result['external_links'], 'url'));
    }

    public function test_images_without_alt_text_are_counted(): void
    {
        $result = HtmlAnalyzer::analyze('<img src="a.jpg" alt="غار"><img src="b.jpg"><img src="c.jpg" alt=" ">', 'https://example.test');

        $this->assertSame(['count' => 3, 'missing_alt' => 2], $result['images']);
    }

    public function test_empty_content_gives_empty_results(): void
    {
        $result = HtmlAnalyzer::analyze('', 'https://example.test');

        $this->assertSame(0, $result['word_count']);
        $this->assertSame([], $result['headings']);
        $this->assertSame(['count' => 0, 'missing_alt' => 0], $result['images']);
    }
}
