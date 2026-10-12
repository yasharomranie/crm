<?php

namespace Tests\Concerns;

/**
 * Builds a PDF with no text layer at all - just a JPEG image of text,
 * embedded as a page XObject - to stand in for a real scanned document in
 * tests of the OCR path (ScannedPdfOcrExtractor, OcrKnowledgeUploadJob).
 * smalot/pdfparser genuinely finds zero text in a file built this way,
 * exactly like a real scan, which is what makes it a meaningful fixture
 * rather than a shortcut around the thing being tested.
 */
trait BuildsScannedPdfFixtures
{
    /**
     * OCR tests shell out to real `tesseract`/`pdftoppm` binaries rather
     * than mocking them, since the whole point is verifying the actual
     * external toolchain is wired correctly - so they skip, rather than
     * fail, on an environment that doesn't have them installed.
     */
    protected function skipUnlessOcrToolsAvailable(): void
    {
        foreach (['tesseract', 'pdftoppm'] as $binary) {
            if (trim((string) shell_exec('which '.escapeshellarg($binary))) === '') {
                $this->markTestSkipped("'{$binary}' is not installed in this environment.");
            }
        }
    }

    /**
     * @param  list<string>  $lines
     */
    protected function buildScannedPdf(string $path, array $lines): void
    {
        $image = imagecreatetruecolor(800, 400);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        foreach ($lines as $index => $line) {
            imagestring($image, 5, 20, 30 + $index * 30, $line, $black);
        }

        ob_start();
        imagejpeg($image, quality: 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        [$width, $height] = [800, 400];

        $objects = [];
        $objects[1] = "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $objects[2] = "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $objects[3] = "3 0 obj<</Type/Page/Parent 2 0 R/Resources<</XObject<</Im1 5 0 R>>>>/MediaBox[0 0 {$width} {$height}]/Contents 4 0 R>>endobj\n";
        $stream = "q {$width} 0 0 {$height} 0 0 cm /Im1 Do Q";
        $objects[4] = '4 0 obj<</Length '.strlen($stream).">>\nstream\n{$stream}\nendstream\nendobj\n";
        $objects[5] = "5 0 obj<</Type/XObject/Subtype/Image/Width {$width}/Height {$height}/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode/Length ".strlen($jpeg).">>\nstream\n{$jpeg}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $body = '';
        $offsets = [0];
        $position = strlen($header);

        foreach ($objects as $number => $objectText) {
            $offsets[$number] = $position;
            $body .= $objectText;
            $position += strlen($objectText);
        }

        $xrefStart = $position;
        $xref = "xref\n0 6\n0000000000 65535 f \n";

        for ($number = 1; $number <= 5; $number++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $trailer = "trailer<</Size 6/Root 1 0 R>>\nstartxref\n{$xrefStart}\n%%EOF";

        file_put_contents($path, $header.$body.$xref.$trailer);
    }
}
