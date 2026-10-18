<?php

namespace App\Domain\Ai;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Pulls the Markdown tables out of an assistant answer and writes them to an
 * Excel workbook (one sheet per table), so a report the manager read in chat
 * can be kept or forwarded without retyping it.
 */
class MarkdownTables
{
    /**
     * @return list<list<list<string>>> tables, each a list of rows (header first) of cell texts
     */
    public static function extract(string $markdown): array
    {
        $tables = [];
        $current = [];

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, '|') && substr_count($line, '|') >= 2) {
                $current[] = $line;

                continue;
            }

            $tables = self::closeTable($tables, $current);
            $current = [];
        }

        return self::closeTable($tables, $current);
    }

    public static function hasTable(?string $markdown): bool
    {
        return $markdown !== null && str_contains($markdown, '|') && self::extract($markdown) !== [];
    }

    /**
     * @param  list<list<list<string>>>  $tables
     * @return string path of a temporary .xlsx file (the caller deletes it)
     */
    public static function toWorkbook(array $tables): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ai_tables_');
        $writer = new Writer;
        $writer->openToFile($path);

        foreach (array_values($tables) as $index => $rows) {
            $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('جدول '.($index + 1));

            $header = array_shift($rows);
            $writer->addRow(Row::fromValues($header, (new Style)->setFontBold()->setBackgroundColor('F3F4F6')));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<list<list<string>>>  $tables
     * @param  list<string>  $lines
     * @return list<list<list<string>>>
     */
    private static function closeTable(array $tables, array $lines): array
    {
        // A table needs a header, the |---| separator and at least one row.
        if (count($lines) < 3 || ! preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', $lines[1])) {
            return $tables;
        }

        $rows = [];

        foreach ($lines as $position => $line) {
            if ($position === 1) {
                continue;
            }

            $cells = array_map(
                fn (string $cell) => trim(str_replace(['**', '`'], '', $cell)),
                explode('|', trim($line, '|')),
            );
            $rows[] = $cells;
        }

        $width = max(array_map('count', $rows));
        $tables[] = array_map(fn (array $row) => array_pad($row, $width, ''), $rows);

        return $tables;
    }
}
