<?php

namespace App\Domain\Ai;

use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Renders a PDF's pages to PNG images - the first half of OCR for a
 * scanned PDF (ScannedPdfOcrExtractor runs Tesseract over the images
 * this produces). Two independent rendering paths, tried in order:
 *
 * 1. `pdftoppm` (poppler-utils), shelled out via Symfony Process - the
 *    default, since it needs nothing beyond the OCR toolchain already
 *    required anyway.
 * 2. The Imagick PHP extension's own PDF delegate (Ghostscript), called
 *    directly from inside the compiled C extension rather than through
 *    a PHP userland function - this is what lets rasterization still
 *    work on budget shared hosting that disables proc_open/exec for
 *    security (a real, hit-in-production case) but still ships
 *    ext-imagick, since disable_functions only gates PHP's own
 *    function-call dispatch, not a C extension's internal calls.
 *
 * Neither path is assumed to be present: rasterize() tries pdftoppm
 * first and only falls back to Imagick once that's actually ruled out,
 * so a host with both still gets pdftoppm's behavior unchanged.
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
        if (function_exists('proc_open')) {
            try {
                return $this->rasterizeWithPdftoppm($pdfPath, $maxPages);
            } catch (\RuntimeException) {
                // Fall through to Imagick - a missing pdftoppm binary or
                // a proc_open blocked some other way (open_basedir, a
                // sibling disable_functions entry) shouldn't be fatal if
                // Imagick can still do the job.
            }
        }

        if (class_exists(Imagick::class)) {
            return $this->rasterizeWithImagick($pdfPath, $maxPages);
        }

        throw new \RuntimeException('نه pdftoppm در دسترس است و نه افزونهٔ Imagick نصب است - تبدیل PDF به تصویر برای OCR ممکن نیست.');
    }

    /**
     * @return list<string>
     */
    public function rasterizeWithPdftoppm(string $pdfPath, int $maxPages): array
    {
        $outputDir = $this->makeOutputDir();
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
            throw new \RuntimeException('تبدیل PDF به تصویر با pdftoppm با خطا مواجه شد: '.$process->getErrorOutput());
        }

        $pages = glob($prefix.'-*.png') ?: [];
        natsort($pages);

        if ($pages === []) {
            throw new \RuntimeException('pdftoppm هیچ صفحه‌ای تولید نکرد.');
        }

        return array_values($pages);
    }

    /**
     * @return list<string>
     */
    public function rasterizeWithImagick(string $pdfPath, int $maxPages): array
    {
        $outputDir = $this->makeOutputDir();

        try {
            $ping = new Imagick;
            $ping->pingImage($pdfPath);
            $totalPages = max(1, $ping->getNumberImages());
            $ping->clear();

            $lastPage = min($maxPages, $totalPages) - 1;

            $imagick = new Imagick;
            $imagick->setResolution(self::DPI, self::DPI);
            $imagick->readImage("{$pdfPath}[0-{$lastPage}]");
        } catch (ImagickException $exception) {
            throw new \RuntimeException('تبدیل PDF به تصویر با Imagick با خطا مواجه شد - احتمالاً پشتیبانی PDF (Ghostscript) روی سرور نصب نیست.', previous: $exception);
        }

        $images = [];

        foreach ($imagick as $index => $page) {
            $page->setImageBackgroundColor('white');
            $page->setImageFormat('png');
            $path = $outputDir.'/page-'.($index + 1).'.png';
            $page->writeImage($path);
            $images[] = $path;
        }

        $imagick->clear();

        if ($images === []) {
            throw new \RuntimeException('Imagick هیچ صفحه‌ای از این PDF تولید نکرد.');
        }

        return $images;
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

    private function makeOutputDir(): string
    {
        $outputDir = sys_get_temp_dir().'/pdf-ocr-'.Str::random(16);
        mkdir($outputDir, recursive: true);

        return $outputDir;
    }
}
