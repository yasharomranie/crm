<?php

namespace Tests\Feature;

use App\Support\SafeMarkdown;
use Tests\TestCase;

class SafeMarkdownTest extends TestCase
{
    public function test_markdown_formatting_is_kept(): void
    {
        $html = SafeMarkdown::toHtml("**مهم**\n\n| a | b |\n|---|---|\n| 1 | 2 |");

        $this->assertStringContainsString('<strong>مهم</strong>', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function test_raw_html_and_script_are_stripped(): void
    {
        $html = SafeMarkdown::toHtml('سلام <script>alert(1)</script> <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_javascript_links_are_dropped(): void
    {
        $html = SafeMarkdown::toHtml('[کلیک](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_null_renders_as_empty(): void
    {
        $this->assertSame('', trim(SafeMarkdown::toHtml(null)));
    }
}
