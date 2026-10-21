<?php

namespace App\Domain\Ai\Missions;

use App\Enums\AiMissionSourceStatus;
use App\Models\AiMission;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use Illuminate\Support\Str;

/**
 * The one extra tool a mission member gets: search in the files attached to
 * the mission (and, if the owner limited this member, only in the files they
 * allowed). Every hit comes back with a ready-made citation that the member
 * writes as the source of its finding; the citations returned are kept so a
 * finding can be tied to the exact piece it quotes.
 */
class MissionSourceTools
{
    public const EXCERPT = 900;

    public function __construct(private readonly MissionSourceSearch $search, private readonly MissionSourceChunker $chunker) {}

    /**
     * Sources this member may read: those attached to the mission, narrowed
     * to the ones the owner allowed for this role when any were chosen.
     *
     * @return list<int>
     */
    public function allowedSourceIds(AiMission $mission, AiMissionRole $role): array
    {
        $attached = $mission->sources()->where('ai_mission_sources.status', AiMissionSourceStatus::Ready->value)->pluck('ai_mission_sources.id')->all();
        $restricted = $role->sources()->pluck('ai_mission_sources.id')->all();

        return array_values($restricted === [] ? $attached : array_intersect($attached, $restricted));
    }

    /**
     * @param  array<string, int>  $returned  filled with citation => chunk id for every hit handed to the member
     * @return array<string, array{description: string, parameters: array<string, mixed>, handler: callable}>
     */
    public function for(AiMission $mission, AiMissionRole $role, array &$returned): array
    {
        $ids = $this->allowedSourceIds($mission, $role);

        if ($ids === []) {
            return [];
        }

        // Files read before pieces existed are cut on first use.
        AiMissionSource::query()->whereIn('id', $ids)->whereDoesntHave('chunks')->whereNotNull('content')->get()->each(fn (AiMissionSource $source) => $this->chunker->store($source));

        return ['search_mission_sources' => [
            'description' => 'در فایل‌های این مأموریت (استراتژی، سیاست‌ها، گزارش‌ها، جدول‌ها) جست‌وجو می‌کند و بخش‌های مرتبط را با «citation» برمی‌گرداند. هر ادعایی که از فایل‌ها می‌آوری باید citation همان بخش را به‌عنوان منبعش بنویسی.',
            'parameters' => ['type' => 'object', 'properties' => [
                'query' => ['type' => 'string', 'description' => 'کلمه‌ها یا عبارت‌های کلیدی فارسی برای جست‌وجو.'],
            ], 'required' => ['query']],
            'handler' => function (array $arguments) use ($ids, &$returned): array {
                $query = trim((string) ($arguments['query'] ?? ''));
                $hits = $query === '' ? [] : $this->search->search($ids, $query);

                if ($hits === []) {
                    return ['results' => [], 'message' => 'در فایل‌ها چیزی پیدا نشد. ادعایی که منبع ندارد را «بدون منبع» بگو، نه با citation ساختگی.'];
                }

                return ['results' => array_map(function (array $hit) use (&$returned): array {
                    $returned[$hit['chunk']->citation()] = $hit['chunk']->id;

                    return ['citation' => $hit['chunk']->citation(), 'text' => Str::limit($hit['chunk']->content, self::EXCERPT, '…')];
                }, $hits)];
            },
        ]];
    }
}
