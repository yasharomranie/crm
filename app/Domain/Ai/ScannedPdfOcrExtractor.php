<?php

namespace App\Domain\Ai;

use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

/**
 * The OCR fallback for a PDF that DocumentTextExtractor could not read
 * any text from - almost always because it's a scan (a photographed or
 * scanned paper document saved as PDF, with no embedded text layer at
 * all, as opposed to a digitally-generated PDF DocumentTextExtractor
 * already handles for free). Rasterizes each page (PdfPageRasterizer)
 * and reads it with Tesseract, in Persian and English.
 *
 * OCR is slow - roughly a couple of seconds per page - so this only
 * ever runs from App\Jobs\OcrKnowledgeUploadJob, a queued job, never
 * inline in the web request that DocumentTextExtractor itself serves.
 */
class ScannedPdfOcrExtractor
{
    /**
     * Same ceiling as DocumentTextExtractor::MAX_CONTENT_LENGTH, kept as
     * its own constant rather than importing that one: this cap exists
     * for an entirely different reason here (bounding how many *pages*
     * get OCR'd, since each page costs real wall-clock time, not just
     * how much text is kept), so the two are allowed to drift
     * independently even though they start at the same value.
     */
    private const MAX_CONTENT_LENGTH = 200000;

    /**
     * A 120-page contract still takes several minutes to OCR at ~2s a
     * page - bounded so one upload can't tie up a queue worker
     * indefinitely. Past this, only the first pages are read.
     */
    private const MAX_OCR_PAGES = 60;

    public function __construct(private readonly PdfPageRasterizer $rasterizer) {}

    /**
     * @return array{content: string, pages_read: int}|array{error: string}
     */
    public function extract(string $pdfPath): array
    {
        try {
            $pages = $this->rasterizer->rasterize($pdfPath, self::MAX_OCR_PAGES);
        } catch (\RuntimeException $exception) {
            return ['error' => $exception->getMessage()];
        }

        if ($pages === []) {
            return ['error' => 'تبدیل PDF به تصویر برای OCR نتیجه‌ای نداد.'];
        }

        try {
            $text = collect($pages)
                ->map(fn (string $imagePath) => $this->ocrPage($imagePath))
                ->implode("\n\n");
        } finally {
            $this->rasterizer->cleanup($pages);
        }

        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return ['error' => 'حتی با OCR هم متنی از این فایل خوانده نشد - احتمالاً کیفیت اسکن پایین است.'];
        }

        return [
            'content' => mb_substr($text, 0, self::MAX_CONTENT_LENGTH),
            'pages_read' => count($pages),
        ];
    }

    private function ocrPage(string $imagePath): string
    {
        try {
            return (new TesseractOCR($imagePath))->lang('fas', 'eng')->psm(6)->run();
        } catch (TesseractOcrException) {
            // One unreadable page (corrupt render, blank page) shouldn't
            // sink the whole document - same tolerance this app already
            // gives one bad item in a batch everywhere else.
            return '';
        }
    }
}
