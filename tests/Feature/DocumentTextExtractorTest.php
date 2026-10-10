<?php

namespace Tests\Feature;

use App\Domain\Ai\DocumentTextExtractor;
use Tests\TestCase;

class DocumentTextExtractorTest extends TestCase
{
    private string $scratchDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratchDir = sys_get_temp_dir().'/document-text-extractor-test-'.uniqid();
        mkdir($this->scratchDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratchDir.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->scratchDir);

        parent::tearDown();
    }

    public function test_it_extracts_text_from_a_plain_text_file(): void
    {
        $path = $this->scratchDir.'/note.txt';
        file_put_contents($path, 'نرخ کمیسیون فروش ۵ درصد است.');

        $result = app(DocumentTextExtractor::class)->extract($path, 'txt');

        $this->assertSame('نرخ کمیسیون فروش ۵ درصد است.', $result['content']);
    }

    public function test_it_extracts_text_from_a_docx_file(): void
    {
        $path = $this->scratchDir.'/note.docx';
        $this->writeMinimalDocx($path, ['سلام دنیا', 'متن دوم']);

        $result = app(DocumentTextExtractor::class)->extract($path, 'docx');

        $this->assertStringContainsString('سلام دنیا', $result['content']);
        $this->assertStringContainsString('متن دوم', $result['content']);
    }

    public function test_it_extracts_text_from_a_pdf_file(): void
    {
        $path = $this->scratchDir.'/note.pdf';
        $this->writeMinimalPdf($path, 'Hello World');

        $result = app(DocumentTextExtractor::class)->extract($path, 'pdf');

        $this->assertStringContainsString('Hello World', $result['content']);
    }

    public function test_it_rejects_an_unsupported_extension(): void
    {
        $path = $this->scratchDir.'/note.exe';
        file_put_contents($path, 'binary-ish content');

        $result = app(DocumentTextExtractor::class)->extract($path, 'exe');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_reports_an_error_for_an_empty_text_file(): void
    {
        $path = $this->scratchDir.'/empty.txt';
        file_put_contents($path, '   ');

        $result = app(DocumentTextExtractor::class)->extract($path, 'txt');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_truncates_very_long_content(): void
    {
        $path = $this->scratchDir.'/long.txt';
        file_put_contents($path, str_repeat('الف ', 10000));

        $result = app(DocumentTextExtractor::class)->extract($path, 'txt');

        $this->assertLessThanOrEqual(20000, mb_strlen($result['content']));
    }

    public function test_it_reports_an_error_for_a_corrupt_docx_file(): void
    {
        $path = $this->scratchDir.'/corrupt.docx';
        file_put_contents($path, 'this is not a zip file');

        $result = app(DocumentTextExtractor::class)->extract($path, 'docx');

        $this->assertArrayHasKey('error', $result);
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function writeMinimalDocx(string $path, array $paragraphs): void
    {
        $body = collect($paragraphs)
            ->map(fn (string $text) => '<w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>')
            ->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body.'</w:body></w:document>';

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }

    private function writeMinimalPdf(string $path, string $text): void
    {
        $objects = [
            1 => "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n",
            2 => "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n",
            3 => "3 0 obj<</Type/Page/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/MediaBox[0 0 200 200]/Contents 5 0 R>>endobj\n",
            4 => "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n",
        ];
        $stream = "BT /F1 24 Tf 10 100 Td ({$text}) Tj ET";
        $objects[5] = '5 0 obj<</Length '.strlen($stream).">>\nstream\n{$stream}\nendstream\nendobj\n";

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
