<?php

namespace App\Domain\Ai\Missions;

use App\Models\AiMissionSource;
use Illuminate\Support\Str;

/**
 * Cuts a source's text into pieces of about TARGET characters that can be
 * searched and cited. Headings (Markdown, spreadsheet sheets) travel with
 * their pieces and every piece of a sheet repeats the sheet's header row, so
 * a number is never read without its column name.
 */
class MissionSourceChunker
{
    public const TARGET = 1200;

    /**
     * @return list<array{heading: ?string, content: string}>
     */
    public function chunk(string $content): array
    {
        $chunks = [];
        $buffer = '';
        $bufferHeading = null;
        $heading = null;
        $header = null;
        $expectHeader = false;

        $flush = function () use (&$chunks, &$buffer, &$bufferHeading, &$header): void {
            $text = trim($buffer);

            if ($text !== '' && $text !== $header) {
                $chunks[] = ['heading' => $bufferHeading, 'content' => $text];
            }

            $buffer = '';
        };

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^کاربرگ «(.+)»$/u', $line, $match)) {
                $flush();
                $heading = Str::limit($match[1], 120, '');
                $header = null;
                $expectHeader = true;

                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/u', $line, $match)) {
                $flush();
                $heading = Str::limit(trim($match[1]), 120, '');
            }

            if ($expectHeader && str_contains($line, ' | ')) {
                $header = $line;
            }

            $expectHeader = false;

            foreach ($this->pieces($line) as $piece) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($piece) + 1 > self::TARGET) {
                    $flush();
                }

                if ($buffer === '') {
                    $bufferHeading = $heading;
                    $buffer = $header !== null && $piece !== $header ? $header."\n" : '';
                }

                $buffer .= ($buffer !== '' && ! str_ends_with($buffer, "\n") ? "\n" : '').$piece;
            }
        }

        $flush();

        return $chunks;
    }

    /** Replaces a source's pieces with a fresh cut of its text. */
    public function store(AiMissionSource $source): int
    {
        $source->chunks()->delete();

        foreach ($this->chunk((string) $source->content) as $index => $chunk) {
            $source->chunks()->create(['position' => $index + 1, 'heading' => $chunk['heading'], 'content' => $chunk['content']]);
        }

        return $source->chunks()->count();
    }

    /**
     * A line longer than TARGET (a Word or PDF text arrives as one long line)
     * is cut at sentence ends; a sentence still too long is cut by length.
     *
     * @return list<string>
     */
    private function pieces(string $line): array
    {
        if (mb_strlen($line) <= self::TARGET) {
            return [$line];
        }

        $pieces = [];

        foreach (preg_split('/(?<=[\.\!\?؟؛۔])\s+/u', $line) ?: [] as $sentence) {
            array_push($pieces, ...(mb_strlen($sentence) > self::TARGET ? mb_str_split($sentence, self::TARGET) : [$sentence]));
        }

        return $pieces;
    }
}
