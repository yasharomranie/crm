<?php

namespace App\Domain\System;

use Illuminate\Support\Str;

/**
 * Reads the newest entries of the application log for the admin screen
 * «گزارش خطاهای سرور», so a 500 can be diagnosed without a terminal.
 * Only the tail of the file is read, and anything that looks like a
 * secret is masked before it is shown.
 */
class ServerLogReader
{
    /** Bytes of the log's end that are read; a day's errors fit well within it. */
    private const TAIL_BYTES = 600000;

    /** Longest entry text shown; a stack trace past this adds nothing. */
    private const ENTRY_LIMIT = 5000;

    private const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * The newest log file inside a directory, or null when there is none.
     */
    public function newestFile(string $directory): ?string
    {
        $files = glob(rtrim($directory, '/').'/*.log') ?: [];

        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    /**
     * @return list<array{time: string, level: string, headline: string, details: string}>
     */
    public function recent(string $path, int $limit = 20, bool $errorsOnly = true): array
    {
        $text = $this->tail($path);

        if ($text === '') {
            return [];
        }

        $entries = [];

        foreach (preg_split('/(?=^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/m', $text, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            if (! preg_match('/^\[(?<time>[^\]]+)\] (?<channel>\w+)\.(?<level>\w+): (?<body>.*)$/s', $chunk, $match)) {
                continue;
            }

            $level = strtoupper($match['level']);

            if ($errorsOnly && ! in_array($level, self::ERROR_LEVELS, true)) {
                continue;
            }

            $body = $this->mask(trim($match['body']));

            $entries[] = [
                'time' => $match['time'],
                'level' => $level,
                'headline' => Str::limit(strtok($body, "\n") ?: $body, 400),
                'details' => Str::limit($body, self::ENTRY_LIMIT, "\n…"),
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    private function tail(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }

        $text = (string) stream_get_contents($handle);
        fclose($handle);

        return $text;
    }

    /**
     * Hides keys, tokens and passwords that can end up in an error message.
     */
    private function mask(string $text): string
    {
        $text = preg_replace('/\bsk-[A-Za-z0-9_\-]{10,}/', 'sk-••••', $text) ?? $text;
        $text = preg_replace('/(Bearer\s+)[A-Za-z0-9._\-]{8,}/i', '$1••••', $text) ?? $text;
        $text = preg_replace('/((?:api[_-]?key|token|secret|password|passwd|authorization)["\']?\s*[:=]\s*["\']?)[^\s"\',;&]+/i', '$1••••', $text) ?? $text;

        return $text;
    }
}
