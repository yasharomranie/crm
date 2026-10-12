<?php

namespace App\Domain\Ai;

use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Renders a PDF's pages to PNG images via `pdftoppm` (poppler-utils) -
 * the first half of OCR for a scanned PDF (ScannedPdfOcrExtractor runs
 * Tesseract over the images this produces). Deliberately shells out,
 * unlike DocumentTextExtractor's pure-PHP parsing: a scanned document
 * has no text layer at all, so there is no pure-PHP way to read it, and
 * this only ever runs from the OCR fallback path (a queued job), never
 * the fast synchronous upload path.
 */
class PdfPageRasterizer
{
    private const DPI = 200;

    /**
     * @return list<string> absolute paths to the rendered PNG files, in
     *                      page order - the caller owns cleanup (the temp
     *                      directory holding them, named by this method).
     */
    public function rasterize(string $pdfPath, int $maxPages): array
    {
        $outputDir = sys_get_temp_dir().'/pdf-ocr-'.Str::random(16);
        mkdir($outputDir, recursive: true);

        $prefix = $outputDir.'/page';

        $process = new Process([
            'pdftoppm', '-png', '-r', (string) self::DPI, '-l', (string) $maxPages, $pdfPath, $prefix,
        ]);
        $process->setTimeout(300);

        try {
            $process->run();
        } catch (ProcessException $exception) {
            throw new \RuntimeException('ابزار تبدیل PDF به تصویر (pdftoppm) روی سرور در دسترس نیست.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('تبدیل PDF به تصویر برای OCR با خطا مواجه شد: '.$process->getErrorOutput());
        }

        $pages = glob($prefix.'-*.png') ?: [];
        natsort($pages);

        return array_values($pages);
    }

    public function cleanup(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            @unlink($path);
        }

        $directories = array_unique(array_map('dirname', $imagePaths));

        foreach ($directories as $directory) {
            @rmdir($directory);
        }
    }
}
