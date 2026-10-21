<?php

namespace App\Domain\Ai\Missions;

use App\Enums\AiMissionBriefStatus;
use App\Models\AiMission;
use Illuminate\Support\Str;

/**
 * The strategy brief of a mission: a short, manager-approved digest of its
 * source files that every member reads before working. Only an approved
 * brief that still matches the attached files is ever given to the team, and
 * a mission with sources will not run without one.
 */
class MissionBrief
{
    public const MAX_ITEMS = 10;

    /**
     * @var array<string, string> list key => Persian label
     */
    public const LISTS = [
        'goals' => 'اهداف',
        'principles' => 'اصول و روش‌ها',
        'red_lines' => 'خط قرمزها (نباید نقض شود)',
        'kpis' => 'شاخص‌ها و هدف‌های عددی',
        'facts' => 'واقعیت‌ها و اعداد مهم',
        'conflicts' => 'تناقض‌ها (اول روشن شود)',
    ];

    /**
     * @return array{summary: string, goals: list<string>, principles: list<string>, red_lines: list<string>, kpis: list<string>, facts: list<string>, conflicts: list<string>}
     */
    public static function normalize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $brief = ['summary' => Str::limit(trim((string) ($raw['summary'] ?? '')), 1200, '')];

        foreach (array_keys(self::LISTS) as $key) {
            $items = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $brief[$key] = collect($items)
                ->filter(fn ($item) => is_string($item) || is_numeric($item))
                ->map(fn ($item) => Str::limit(trim((string) $item), 300, ''))
                ->filter()
                ->unique()
                ->take(self::MAX_ITEMS)
                ->values()
                ->all();
        }

        return $brief;
    }

    /**
     * Identifies the attached files (and the kind the manager gave each), so
     * a brief approved for one set of files is not mistaken for another.
     */
    public static function sourcesHash(AiMission $mission): string
    {
        return sha1($mission->sources()->get()
            ->map(fn ($source) => $source->id.':'.$source->content_hash.':'.$source->kind->value)
            ->sort()
            ->implode('|'));
    }

    /** Why the mission cannot run yet because of its sources, or null when it can. */
    public static function blocksRun(AiMission $mission): ?string
    {
        if (! $mission->sources()->exists()) {
            return null;
        }

        if ($mission->brief_status !== AiMissionBriefStatus::Approved) {
            return 'برای این مأموریت منبع گذاشته‌اید؛ اول «خلاصهٔ استراتژی» را بسازید و تأیید کنید.';
        }

        if ($mission->brief_sources_hash !== self::sourcesHash($mission)) {
            return 'منابع مأموریت بعد از تأیید خلاصه تغییر کرده؛ خلاصه را دوباره بسازید و تأیید کنید.';
        }

        return null;
    }

    /** The text every member reads, or an empty string when there is no usable brief. */
    public static function promptBlock(AiMission $mission): string
    {
        if (self::blocksRun($mission) !== null || ! $mission->sources()->exists()) {
            return '';
        }

        $brief = self::normalize($mission->brief);
        $lines = ['خلاصهٔ استراتژی این مأموریت (تأییدشده توسط مدیر؛ منبع معتبر برای جهت‌گیری و محدودیت‌ها):'];

        if ($brief['summary'] !== '') {
            $lines[] = $brief['summary'];
        }

        foreach (self::LISTS as $key => $label) {
            if ($brief[$key] !== []) {
                $lines[] = $label.':';
                array_push($lines, ...array_map(fn (string $item) => "- {$item}", $brief[$key]));
            }
        }

        $lines[] = 'پیشنهاد یا یافته‌ای که با خط قرمزها در تضاد باشد را صریح بگو و علامت بزن؛ آن را بی‌صدا نادیده نگیر.';

        return implode("\n", $lines);
    }
}
