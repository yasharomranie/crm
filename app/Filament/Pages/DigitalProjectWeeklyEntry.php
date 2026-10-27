<?php

namespace App\Filament\Pages;

use App\Domain\DigitalProjects\DigitalProjectManagementReport;
use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectMetric;
use App\Enums\DigitalProjectStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\DigitalProject;
use App\Models\DigitalProjectStat;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * «ورود سریع آمار هفته»: the numbers of the weekly management report typed in
 * one grid instead of project by project. Each cell is one reading dated the
 * last day (Friday) of the chosen week; clearing a cell removes that reading.
 */
class DigitalProjectWeeklyEntry extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.digital-project-weekly-entry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'پروژه‌های دیجیتال';

    protected static ?string $navigationLabel = 'آمار هفتگی';

    protected static ?string $title = 'ورود سریع آمار هفته';

    /** The Friday that ends the chosen week, as Y-m-d. */
    public string $weekEnd = '';

    /** @var array<int|string, array<string, int|string|null>> project id => metric => value */
    public array $values = [];

    public static function accessControlPermission(): string
    {
        return 'digital-project-weekly-entry.access';
    }

    public function mount(): void
    {
        $this->weekEnd = DigitalProjectManagementReport::weekOf(today())[1]->toDateString();
        $this->loadValues();
    }

    /**
     * The numbers typed here; follower growth is worked out from the follower count.
     *
     * @return list<DigitalProjectMetric>
     */
    public function metrics(): array
    {
        return [
            DigitalProjectMetric::Followers,
            DigitalProjectMetric::Posts,
            DigitalProjectMetric::Stories,
            DigitalProjectMetric::Visitors,
            DigitalProjectMetric::Purchases,
        ];
    }

    public function weekEndDate(): Carbon
    {
        try {
            return Carbon::parse($this->weekEnd)->startOfDay();
        } catch (\Throwable) {
            return DigitalProjectManagementReport::weekOf(today())[1];
        }
    }

    /**
     * @return Collection<int, array{channel: DigitalProjectChannel, projects: Collection<int, DigitalProject>}>
     */
    public function groups(): Collection
    {
        $projects = DigitalProject::query()->where('status', '!=', DigitalProjectStatus::Done->value)->orderBy('name')->get();

        return collect(DigitalProjectChannel::cases())
            ->map(fn (DigitalProjectChannel $channel) => ['channel' => $channel, 'projects' => $projects->filter(fn (DigitalProject $project) => $project->channel === $channel)->values()])
            ->filter(fn (array $group) => $group['projects']->isNotEmpty())
            ->values();
    }

    public function shiftWeek(int $direction): void
    {
        $this->weekEnd = $this->weekEndDate()->addWeeks($direction)->toDateString();
        $this->loadValues();
    }

    public function loadValues(): void
    {
        $this->values = [];

        DigitalProjectStat::query()
            ->whereDate('recorded_on', $this->weekEndDate())
            ->whereIn('metric', array_map(fn (DigitalProjectMetric $metric) => $metric->value, $this->metrics()))
            ->get()
            ->each(function (DigitalProjectStat $stat): void {
                $this->values[$stat->digital_project_id][$stat->metric->value] = $stat->value;
            });
    }

    public function save(): void
    {
        $this->validate(['values.*.*' => ['nullable', 'integer', 'min:0']], [], ['values.*.*' => 'عدد']);

        $date = $this->weekEndDate();
        $projectIds = DigitalProject::query()->pluck('id')->all();
        $saved = 0;

        foreach ($this->metrics() as $metric) {
            foreach ($projectIds as $projectId) {
                $value = $this->values[$projectId][$metric->value] ?? null;

                $reading = DigitalProjectStat::query()
                    ->where('digital_project_id', $projectId)
                    ->where('metric', $metric->value)
                    ->whereDate('recorded_on', $date);

                if ($value === null || $value === '') {
                    $reading->delete();

                    continue;
                }

                $existing = $reading->first();

                if ($existing) {
                    $existing->update(['value' => (int) $value]);
                } else {
                    DigitalProjectStat::create([
                        'digital_project_id' => $projectId,
                        'metric' => $metric->value,
                        'value' => (int) $value,
                        'recorded_on' => $date,
                        'user_id' => auth()->id(),
                    ]);
                }

                $saved++;
            }
        }

        Notification::make()->title($saved.' عدد ذخیره شد.')->success()->send();
    }

    public function reportUrl(): string
    {
        return route('digital-projects.management-report', ['date' => $this->weekEndDate()->toDateString()]);
    }
}
