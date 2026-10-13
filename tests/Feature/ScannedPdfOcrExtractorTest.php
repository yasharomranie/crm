<?php

namespace Tests\Feature;

use App\Domain\Ai\PdfPageRasterizer;
use App\Domain\Ai\ScannedPdfOcrExtractor;
use Tests\Concerns\BuildsScannedPdfFixtures;
use Tests\TestCase;

class ScannedPdfOcrExtractorTest extends TestCase
{
    use BuildsScannedPdfFixtures;

    /**
     * Defaults to '' rather than being left uninitialized: PHPUnit still
     * runs tearDown() for a test that skipUnlessOcrToolsAvailable() just
     * skipped, and reading an uninitialized typed property there would be
     * a fatal error, not just a skip.
     */
    private string $scratchDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessOcrToolsAvailable();

        $this->scratchDir = sys_get_temp_dir().'/scanned-pdf-ocr-test-'.uniqid();
        mkdir($this->scratchDir);
    }

    protected function tearDown(): void
    {
        if ($this->scratchDir !== '') {
            foreach (glob($this->scratchDir.'/*') as $file) {
                unlink($file);
            }
            rmdir($this->scratchDir);
        }

        parent::tearDown();
    }

    public function test_it_reads_text_from_a_scanned_pdf_via_ocr(): void
    {
        $path = $this->scratchDir.'/scan.pdf';
        $this->buildScannedPdf($path, ['Commission rate is five percent.']);

        $result = app(ScannedPdfOcrExtractor::class)->extract($path);

        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('Commission', $result['content']);
        $this->assertSame(1, $result['pages_read']);
    }

    public function test_it_reports_an_error_when_the_pdf_has_no_pages_to_rasterize(): void
    {
        $path = $this->scratchDir.'/not-a-pdf.pdf';
        file_put_contents($path, 'this is not a valid pdf at all');

        $result = app(ScannedPdfOcrExtractor::class)->extract($path);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_the_rasterizer_cleans_up_its_temp_files(): void
    {
        $path = $this->scratchDir.'/scan.pdf';
        $this->buildScannedPdf($path, ['Cleanup check line.']);

        $rasterizer = app(PdfPageRasterizer::class);
        $images = $rasterizer->rasterize($path, maxPages: 5);

        $this->assertNotEmpty($images);
        foreach ($images as $image) {
            $this->assertFileExists($image);
        }

        $directory = dirname($images[0]);
        $rasterizer->cleanup($images);

        foreach ($images as $image) {
            $this->assertFileDoesNotExist($image);
        }
        $this->assertDirectoryDoesNotExist($directory);
    }
}
