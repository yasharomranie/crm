<?php

namespace App\Domain\Ai\Missions;

use App\Models\AiMissionAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Checks a finished round against the mission's approved strategy brief:
 * does each proposed decision and action keep to the red lines and
 * principles, and which of the strategy's goals did the round actually touch.
 * This class only builds the question and reads the answer; the checking
 * member is asked by MissionRunner. Nothing here blocks anything - a conflict
 * is a flag for the manager, who still decides.
 */
class MissionCompliance
{
    public const FORMAT = 'خروجی را فقط به‌صورت JSON بده: {"decisions":[{"n":1,"verdict":"ok|conflict|unclear","note":"دلیل کوتاه"}],"actions":[{"id":12,"verdict":"ok|conflict|unclear","note":"دلیل کوتاه"}],"goals":[{"g":1,"covered":true,"note":"چه کاری برای این هدف شد"}]}. conflict فقط وقتی که مورد صراحتاً یک خط قرمز یا اصل را نقض می‌کند؛ اگر مطمئن نیستی unclear بگو. covered فقط وقتی true که خروجی همین دور واقعاً برای آن هدف کاری یا یافته‌ای دارد.';

    /**
     * @param  array{summary: string, goals: list<string>, principles: list<string>, red_lines: list<string>, kpis: list<string>, facts: list<string>, conflicts: list<string>}  $brief
     * @param  list<array{title: string, detail: string}>  $decisions
     * @param  Collection<int, AiMissionAction>  $actions
     */
    public static function question(array $brief, string $summary, array $decisions, Collection $actions, string $board): string
    {
        $numbered = fn (array $items, string $prefix): string => collect($items)->map(fn (string $item, int $index) => $prefix.($index + 1).': '.$item)->implode("\n") ?: '(ندارد)';

        return implode("\n\n", [
            'بررسی انطباق با استراتژی: خروجی این دور را با خلاصهٔ استراتژی تأییدشده (در دستورهای بالا) بسنج.',
            "خلاصهٔ این دور: {$summary}",
            "تصمیم‌های پیشنهادی:\n".collect($decisions)->map(fn (array $decision, int $index) => 'D'.($index + 1).': '.$decision['title'].($decision['detail'] !== '' ? ' — '.$decision['detail'] : ''))->implode("\n") ?: "تصمیم‌های پیشنهادی:\n(ندارد)",
            "اقدام‌های پیشنهادی:\n".$actions->map(fn (AiMissionAction $action) => 'A'.$action->id.': '.$action->summary.($action->reason ? ' — '.$action->reason : ''))->implode("\n") ?: "اقدام‌های پیشنهادی:\n(ندارد)",
            "اهداف استراتژی:\n".$numbered($brief['goals'], 'G'),
            "یافته‌های تخته:\n".Str::limit($board, 6000, '…'),
            'برای هر تصمیم (شمارهٔ بعد از D) و هر اقدام (عدد بعد از A) بگو با خط قرمزها و اصول سازگار است یا نه، و برای هر هدف (شمارهٔ بعد از G) بگو این دور پوشش داده شده یا نه. فقط از متن بالا و ابزار فایل‌ها استفاده کن؛ حدس نزن.',
        ]);
    }

    /**
     * @return array{decisions: array<int, array{verdict: string, note: string}>, actions: array<int, array{verdict: string, note: string}>, goals: array<int, array{covered: bool, note: string}>}
     */
    public static function parse(string $answer): array
    {
        $decoded = preg_match('/\{.*\}/su', $answer, $match) ? json_decode($match[0], true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        $verdict = fn (mixed $row): array => [
            'verdict' => in_array($row['verdict'] ?? null, ['ok', 'conflict', 'unclear'], true) ? $row['verdict'] : 'unclear',
            'note' => Str::limit(trim((string) ($row['note'] ?? '')), 300, ''),
        ];
        $result = ['decisions' => [], 'actions' => [], 'goals' => []];

        foreach ($decoded['decisions'] ?? [] as $row) {
            if (is_array($row) && is_numeric($row['n'] ?? null)) {
                $result['decisions'][(int) $row['n']] = $verdict($row);
            }
        }

        foreach ($decoded['actions'] ?? [] as $row) {
            if (is_array($row) && is_numeric($row['id'] ?? null)) {
                $result['actions'][(int) $row['id']] = $verdict($row);
            }
        }

        foreach ($decoded['goals'] ?? [] as $row) {
            if (is_array($row) && is_numeric($row['g'] ?? null) && array_key_exists('covered', $row)) {
                $result['goals'][(int) $row['g']] = ['covered' => filter_var($row['covered'], FILTER_VALIDATE_BOOLEAN), 'note' => Str::limit(trim((string) ($row['note'] ?? '')), 300, '')];
            }
        }

        return $result;
    }
}
