<?php

namespace App\Domain\DigitalProjects;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectMetric;
use App\Enums\DigitalProjectStatus;
use App\Enums\DigitalProjectWorkCategory;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\DigitalProjectLog;
use App\Models\DigitalProjectStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * The weekly matrix the manager hands in: one row per digital project grouped
 * by kind, one column per number, in three coloured column groups
 * (وضعیت، توسعه، عملکرد).
 *
 * - posts, stories, visitors, purchases: the sum of the readings entered in the week;
 * - follower growth: this week's last follower reading minus the last one before the week;
 * - the development and performance columns: finished work of that category in
 *   the week (actions finished + "done" journal lines).
 *
 * A cell with nothing behind it is null (printed empty), never a made-up zero.
 */
class DigitalProjectManagementReport
{
    /** Column key of the computed follower growth. */
    public const FOLLOWER_GROWTH = 'followers_growth';

    /**
     * The week (Saturday to Friday) that contains the given day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function weekOf(Carbon $day): array
    {
        $start = $day->copy()->startOfDay()->startOfWeek(Carbon::SATURDAY);

        return [$start, $start->copy()->addDays(6)];
    }

    /**
     * @return list<array{title: string, columns: list<array{key: string, label: string}>}>
     */
    public static function columnGroups(): array
    {
        $category = fn (DigitalProjectWorkCategory $category): array => ['key' => $category->value, 'label' => $category->getLabel()];

        return [
            ['title' => 'وضعیت', 'columns' => [
                ['key' => DigitalProjectMetric::Posts->value, 'label' => DigitalProjectMetric::Posts->getLabel()],
                ['key' => DigitalProjectMetric::Stories->value, 'label' => DigitalProjectMetric::Stories->getLabel()],
                ['key' => self::FOLLOWER_GROWTH, 'label' => 'رشد فالور'],
                ['key' => DigitalProjectMetric::Visitors->value, 'label' => 'بازدیدکننده'],
                ['key' => DigitalProjectMetric::Purchases->value, 'label' => DigitalProjectMetric::Purchases->getLabel()],
            ]],
            ['title' => 'توسعه', 'columns' => [
                $category(DigitalProjectWorkCategory::VisualImprovement),
                $category(DigitalProjectWorkCategory::Coding),
                $category(DigitalProjectWorkCategory::Security),
            ]],
            ['title' => 'عملکرد', 'columns' => [
                $category(DigitalProjectWorkCategory::Seo),
                $category(DigitalProjectWorkCategory::Articles),
                $category(DigitalProjectWorkCategory::ContentProduction),
                $category(DigitalProjectWorkCategory::IndexMonitoring),
            ]],
        ];
    }

    /**
     * @return list<string> the column keys in print order
     */
    public static function columnKeys(): array
    {
        return collect(self::columnGroups())->flatMap(fn (array $group) => array_column($group['columns'], 'key'))->all();
    }

    /**
     * @return array{start: Carbon, end: Carbon, groups: Collection<int, array{channel: DigitalProjectChannel, rows: list<array{project: DigitalProject, cells: array<string, ?int>}>}>}
     */
    public function build(Carbon $start, Carbon $end): array
    {
        $projects = DigitalProject::query()
            ->where('status', '!=', DigitalProjectStatus::Done->value)
            ->orderBy('name')
            ->get();

        $ids = $projects->pluck('id');

        $stats = DigitalProjectStat::query()->whereIn('digital_project_id', $ids)->whereDate('recorded_on', '<=', $end)->get()->groupBy('digital_project_id');

        $logs = DigitalProjectLog::query()
            ->whereIn('digital_project_id', $ids)
            ->where('kind', DigitalProjectLogKind::Done->value)
            ->whereNotNull('work_category')
            ->whereDate('logged_on', '>=', $start)
            ->whereDate('logged_on', '<=', $end)
            ->get(['digital_project_id', 'work_category'])
            ->groupBy('digital_project_id');

        $actions = DigitalProjectAction::query()
            ->whereIn('digital_project_id', $ids)
            ->where('status', 'done')
            ->whereNotNull('work_category')
            ->where('completed_at', '>=', $start->copy()->startOfDay())
            ->where('completed_at', '<=', $end->copy()->endOfDay())
            ->get(['digital_project_id', 'work_category'])
            ->groupBy('digital_project_id');

        $groups = collect(DigitalProjectChannel::cases())
            ->map(fn (DigitalProjectChannel $channel) => [
                'channel' => $channel,
                'rows' => $projects
                    ->filter(fn (DigitalProject $project) => $project->channel === $channel)
                    ->map(fn (DigitalProject $project) => [
                        'project' => $project,
                        'cells' => $this->cellsFor($start, $stats->get($project->id, collect()), $logs->get($project->id, collect()), $actions->get($project->id, collect())),
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $group) => $group['rows'] !== [])
            ->values();

        return ['start' => $start, 'end' => $end, 'groups' => $groups];
    }

    /**
     * @param  Collection<int, DigitalProjectStat>  $stats  every reading up to the end of the week
     * @param  Collection<int, DigitalProjectLog>  $logs
     * @param  Collection<int, DigitalProjectAction>  $actions
     * @return array<string, ?int>
     */
    private function cellsFor(Carbon $start, Collection $stats, Collection $logs, Collection $actions): array
    {
        $cells = array_fill_keys(self::columnKeys(), null);

        foreach ([DigitalProjectMetric::Posts, DigitalProjectMetric::Stories, DigitalProjectMetric::Visitors, DigitalProjectMetric::Purchases] as $metric) {
            $inWeek = $stats->filter(fn (DigitalProjectStat $stat) => $stat->metric === $metric && $stat->recorded_on->gte($start));

            $cells[$metric->value] = $inWeek->isEmpty() ? null : (int) $inWeek->sum('value');
        }

        $followers = $stats
            ->filter(fn (DigitalProjectStat $stat) => $stat->metric === DigitalProjectMetric::Followers)
            ->sortBy(fn (DigitalProjectStat $stat) => $stat->recorded_on->format('Ymd').str_pad((string) $stat->id, 12, '0', STR_PAD_LEFT))
            ->values();

        $current = $followers->last();
        $before = $followers->filter(fn (DigitalProjectStat $stat) => $stat->recorded_on->lt($start))->last();

        if ($current && $before && $current->recorded_on->gte($start)) {
            $cells[self::FOLLOWER_GROWTH] = $current->value - $before->value;
        }

        $counted = $logs->concat($actions)->countBy(fn ($row) => $row->work_category->value);

        foreach (DigitalProjectWorkCategory::cases() as $category) {
            $count = (int) ($counted[$category->value] ?? 0);
            $cells[$category->value] = $count > 0 ? $count : null;
        }

        return $cells;
    }

    /** What a cell prints: empty when there is nothing, Persian digits, and a sign on follower growth. */
    public static function format(string $key, ?int $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = Number::toPersian(number_format(abs($value)));

        if ($key !== self::FOLLOWER_GROWTH) {
            return $text;
        }

        return match (true) {
            $value > 0 => '+'.$text,
            $value < 0 => '−'.$text,
            default => $text,
        };
    }
}
