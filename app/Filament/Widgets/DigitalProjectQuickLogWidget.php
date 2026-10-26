<?php

namespace App\Filament\Widgets;

use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectStatus;
use App\Models\DigitalProject;
use App\Models\DigitalProjectLog;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * "What did you do today?": a one-box journal entry above the project table,
 * so a day's work is written down in ten seconds without opening a project.
 */
class DigitalProjectQuickLogWidget extends Widget
{
    protected string $view = 'filament.widgets.digital-project-quick-log';

    protected int|string|array $columnSpan = 'full';

    public ?int $projectId = null;

    public string $kind = 'done';

    public string $body = '';

    public function save(): void
    {
        $this->validate([
            'projectId' => ['required', 'integer', 'exists:digital_projects,id'],
            'kind' => ['required', 'in:'.implode(',', array_column(DigitalProjectLogKind::cases(), 'value'))],
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'projectId' => 'پروژه',
            'kind' => 'نوع',
            'body' => 'گزارش',
        ]);

        DigitalProjectLog::create([
            'digital_project_id' => $this->projectId,
            'kind' => $this->kind,
            'body' => $this->body,
            'logged_on' => today(),
            'user_id' => auth()->id(),
        ]);

        $this->reset('body');
        $this->dispatch('digital-project-log-saved');

        Notification::make()->title('گزارش امروز ثبت شد.')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection<int, DigitalProject> $projects */
        $projects = DigitalProject::query()
            ->where('status', '!=', DigitalProjectStatus::Done->value)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'projects' => $projects,
            'kinds' => DigitalProjectLogKind::cases(),
        ];
    }
}
