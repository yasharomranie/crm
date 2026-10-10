<?php

namespace App\Domain\Ai;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts plain text from a file an assistant profile's owner uploads
 * (HasCustomAssistantChatEngine::processKnowledgeUpload) - the third
 * knowledge-building path alongside ordinary chat and the fetch_url tool.
 * .docx is read directly as a zip of XML (no extra dependency: Word's own
 * format already is one), .pdf goes through smalot/pdfparser (pure PHP,
 * no shell-out, so it works on hosts with proc_open disabled).
 */
class DocumentTextExtractor
{
    private const MAX_CONTENT_LENGTH = 20000;

    /**
     * @return array{content: string}|array{error: string}
     */
    public function extract(string $path, string $extension): array
    {
        try {
            $text = match (strtolower($extension)) {
                'txt' => $this->extractTxt($path),
                'docx' => $this->extractDocx($path),
                'pdf' => $this->extractPdf($path),
                default => throw new \RuntimeException('فرمت فایل پشتیبانی نمی‌شود.'),
            };
        } catch (\Throwable $exception) {
            return ['error' => 'خواندن فایل با خطا مواجه شد: '.$exception->getMessage()];
        }

        $text = trim($text);

        if ($text === '') {
            return ['error' => 'هیچ متنی از این فایل استخراج نشد.'];
        }

        return ['content' => mb_substr($text, 0, self::MAX_CONTENT_LENGTH)];
    }

    private function extractTxt(string $path): string
    {
        return (string) file_get_contents($path);
    }

    private function extractDocx(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('فایل Word قابل باز شدن نیست.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('محتوای سند در فایل Word یافت نشد.');
        }

        // Word stores text in <w:t> runs inside <w:p> paragraphs with no
        // whitespace between tags - insert a space at paragraph
        // boundaries first, or words from adjacent paragraphs would run
        // together once the tags themselves are stripped.
        $xml = str_replace('</w:p>', ' ', $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function extractPdf(string $path): string
    {
        $text = (new PdfParser)->parseFile($path)->getText();

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
