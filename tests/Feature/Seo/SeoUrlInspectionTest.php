<?php

namespace Tests\Feature\Seo;

use App\Models\SeoUrlInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoUrlInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passing_verdict_explains_that_the_page_is_indexed(): void
    {
        $inspection = SeoUrlInspection::factory()->create([
            'verdict' => 'PASS',
            'robots_txt_state' => 'ALLOWED',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'SUCCESSFUL',
            'coverage_state' => 'Submitted and indexed',
        ]);

        $this->assertTrue($inspection->isIndexed());
        $this->assertStringContainsString('ایندکس شده', $inspection->explanationInPersian());
    }

    public function test_a_robots_txt_block_is_explained_as_the_reason(): void
    {
        $inspection = SeoUrlInspection::factory()->create([
            'verdict' => 'FAIL',
            'robots_txt_state' => 'DISALLOWED',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'SUCCESSFUL',
            'coverage_state' => null,
        ]);

        $explanation = $inspection->explanationInPersian();

        $this->assertFalse($inspection->isIndexed());
        $this->assertStringContainsString('ایندکس نشده', $explanation);
        $this->assertStringContainsString('robots.txt', $explanation);
    }

    public function test_a_noindex_meta_tag_is_explained_as_the_reason(): void
    {
        $inspection = SeoUrlInspection::factory()->create([
            'verdict' => 'FAIL',
            'robots_txt_state' => 'ALLOWED',
            'indexing_state' => 'BLOCKED_BY_META_TAG',
            'page_fetch_state' => 'SUCCESSFUL',
            'coverage_state' => null,
        ]);

        $this->assertStringContainsString('meta robots', $inspection->explanationInPersian());
    }

    public function test_a_404_fetch_error_is_explained_as_the_reason(): void
    {
        $inspection = SeoUrlInspection::factory()->create([
            'verdict' => 'FAIL',
            'robots_txt_state' => 'ALLOWED',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'NOT_FOUND',
            'coverage_state' => null,
        ]);

        $this->assertStringContainsString('۴۰۴', $inspection->explanationInPersian());
    }

    public function test_a_canonical_mismatch_is_mentioned(): void
    {
        $inspection = SeoUrlInspection::factory()->create([
            'verdict' => 'PASS',
            'robots_txt_state' => 'ALLOWED',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'SUCCESSFUL',
            'google_canonical' => 'https://ganjnameh.example.com/canonical-page',
            'user_canonical' => 'https://ganjnameh.example.com/telecabin',
            'coverage_state' => null,
        ]);

        $explanation = $inspection->explanationInPersian();

        $this->assertStringContainsString('canonical', $explanation);
        $this->assertStringContainsString('https://ganjnameh.example.com/canonical-page', $explanation);
    }
}
