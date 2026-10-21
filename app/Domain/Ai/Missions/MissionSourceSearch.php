<?php

namespace App\Domain\Ai\Missions;

use App\Models\AiMissionSourceChunk;

/**
 * Keyword search over the pieces of the given source files. Persian text is
 * normalised first (Arabic letter forms, half-spaces, digits) and a word
 * matches its own prefixes and extensions, so «اشغال» finds «اشغالی» and
 * «۷۵» finds "75". No embeddings: at the size of a mission's files this is
 * quick and needs nothing beyond the database.
 */
class MissionSourceSearch
{
    public const LIMIT = 5;

    private const STOPWORDS = ['و', 'در', 'به', 'از', 'که', 'این', 'آن', 'را', 'با', 'برای', 'است', 'بود', 'شد', 'شود', 'می', 'نمی', 'هم', 'یا', 'تا', 'بر', 'ها', 'های', 'یک', 'کرد', 'کند', 'دارد', 'باید', 'بین', 'همه', 'هر', 'اما', 'اگر', 'چه', 'خود', 'بعد', 'قبل'];

    /**
     * @param  list<int>  $sourceIds
     * @return list<array{chunk: AiMissionSourceChunk, score: float}>
     */
    public function search(array $sourceIds, string $query, int $limit = self::LIMIT): array
    {
        $queryTokens = array_values(array_unique($this->tokens($query)));

        if ($queryTokens === [] || $sourceIds === []) {
            return [];
        }

        $scored = [];

        foreach (AiMissionSourceChunk::query()->with('source:id,title')->whereIn('ai_mission_source_id', $sourceIds)->orderBy('ai_mission_source_id')->orderBy('position')->lazy(500) as $chunk) {
            $score = $this->score($queryTokens, $this->tokens($chunk->content.' '.$chunk->heading));

            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $chunkTokens
     */
    private function score(array $queryTokens, array $chunkTokens): float
    {
        $counts = array_count_values($chunkTokens);
        $score = 0.0;
        $matched = 0;

        foreach ($queryTokens as $token) {
            $points = ($counts[$token] ?? 0) * 3.0;

            if (mb_strlen($token) >= 3) {
                foreach ($counts as $word => $count) {
                    if ($word !== $token && mb_strlen($word) >= 3 && (str_starts_with($word, $token) || str_starts_with($token, $word))) {
                        $points += $count;
                    }
                }
            }

            if ($points > 0) {
                $matched++;
                $score += $points;
            }
        }

        return $matched === 0 ? 0.0 : $score * (0.5 + $matched / count($queryTokens));
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $text = strtr($text, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه', 'ـ' => '', "\u{200C}" => ' ',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $token) => mb_strlen($token) >= 2 && ! in_array($token, self::STOPWORDS, true),
        ));
    }
}
