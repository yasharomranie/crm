<?php

namespace App\Domain\Ai;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts plain text from a file an assistant profile's owner uploads
 * (HasCustomAssistantChatEngine::processKnowledgeUpload) - the third
 * knowledge-building path alongside ordinary chat and the fetch_url tool.
 * .docx is read directly as a zip of XML (no extra dependency: Word's own
 * format already is one), .pdf goes through smalot/pdfparser (pure PHP,
 * no shell-out, so it works on hosts with proc_open disabled). Mission
 * sources also bring .xlsx (read the same way, as a zip of XML) and .csv/.md.
 * Spreadsheets come out as one "cell | cell" line per row under a sheet
 * heading, so numbers stay next to their labels.
 */
class DocumentTextExtractor
{
    /**
     * A hard ceiling on how much of a document is ever considered, not a
     * per-call size - HasCustomAssistantChatEngine sends this in chunks
     * across several AI calls, so this only needs to be generous enough
     * to cover a genuinely long upload (a few hundred pages of dense
     * text) rather than sized to fit one request.
     */
    private const MAX_CONTENT_LENGTH = 200000;

    /**
     * @return array{content: string}|array{error: string}
     */
    public function extract(string $path, string $extension): array
    {
        try {
            $text = match (strtolower($extension)) {
                'txt', 'md' => $this->extractTxt($path),
                'csv' => $this->extractCsv($path),
                'xlsx' => $this->extractXlsx($path),
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

    /** Rows kept per sheet or CSV, so one huge export cannot swamp the text limit. */
    private const MAX_TABLE_ROWS = 3000;

    private const MAX_TABLE_COLUMNS = 40;

    private function extractCsv(string $path): string
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        // Persian Excel often saves CSV in Windows-1256 rather than UTF-8.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
        }

        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $candidate) => substr_count($firstLine, $candidate))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $rows = [];

        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false && count($rows) < self::MAX_TABLE_ROWS) {
            $rows[] = $cells;
        }

        fclose($stream);

        return $this->tableText($rows);
    }

    private function extractXlsx(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('فایل Excel قابل باز شدن نیست.');
        }

        $read = fn (string $name): ?\DOMXPath => ($xml = $zip->getFromName($name)) === false ? null : $this->xpath($xml);

        $shared = [];

        if ($strings = $read('xl/sharedStrings.xml')) {
            foreach ($strings->query("//*[local-name()='si']") as $item) {
                $shared[] = collect($strings->query(".//*[local-name()='t']", $item))->map(fn (\DOMNode $node) => $node->textContent)->implode('');
            }
        }

        $workbook = $read('xl/workbook.xml');
        $relations = $read('xl/_rels/workbook.xml.rels');

        if (! $workbook) {
            $zip->close();

            throw new \RuntimeException('محتوای کاربرگ‌ها در فایل Excel یافت نشد.');
        }

        $targets = [];

        foreach ($relations?->query("//*[local-name()='Relationship']") ?? [] as $relation) {
            $targets[$relation->getAttribute('Id')] = ltrim(str_replace('\\', '/', $relation->getAttribute('Target')), '/');
        }

        $sections = [];

        foreach ($workbook->query("//*[local-name()='sheet']") as $index => $sheet) {
            $relationId = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $target = $targets[$relationId] ?? 'worksheets/sheet'.($index + 1).'.xml';
            $sheetXml = $zip->getFromName(str_starts_with($target, 'xl/') ? $target : 'xl/'.$target);

            if ($sheetXml === false) {
                continue;
            }

            $rows = $this->sheetRows($this->xpath($sheetXml), $shared);

            if ($rows !== []) {
                $sections[] = 'کاربرگ «'.$sheet->getAttribute('name')."»\n".$this->tableText($rows);
            }
        }

        $zip->close();

        return implode("\n\n", $sections);
    }

    private function xpath(string $xml): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private function sheetRows(\DOMXPath $sheet, array $shared): array
    {
        $rows = [];

        foreach ($sheet->query("//*[local-name()='row']") as $row) {
            if (count($rows) >= self::MAX_TABLE_ROWS) {
                break;
            }

            $cells = [];

            foreach ($sheet->query("*[local-name()='c']", $row) as $cell) {
                $column = $this->columnIndex($cell->getAttribute('r'));

                if ($column >= self::MAX_TABLE_COLUMNS) {
                    continue;
                }

                $type = $cell->getAttribute('t');
                $value = $sheet->query("*[local-name()='v']", $cell)->item(0)?->textContent ?? '';

                $cells[$column] = match ($type) {
                    's' => $shared[(int) $value] ?? '',
                    'inlineStr' => $sheet->query(".//*[local-name()='t']", $cell)->item(0)?->textContent ?? '',
                    'b' => $value === '1' ? 'بله' : 'خیر',
                    default => $value,
                };
            }

            if ($cells !== []) {
                ksort($cells);
                $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
            }
        }

        return $rows;
    }

    private function columnIndex(string $reference): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $reference) ?? '');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function tableText(array $rows): string
    {
        return collect($rows)
            ->map(fn (array $cells) => collect($cells)->take(self::MAX_TABLE_COLUMNS)->map(fn ($cell) => trim(preg_replace('/\s+/u', ' ', (string) $cell) ?? ''))->implode(' | '))
            ->filter(fn (string $line) => trim(str_replace('|', '', $line)) !== '')
            ->implode("\n");
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
