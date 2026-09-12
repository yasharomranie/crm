<?php

namespace App\Console\Commands;

use App\Enums\ChecklistRecurrence;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('checklists:generate')]
#[Description('Generate today\'s task instance for every active recurring checklist template that has not been generated yet for its period')]
class GenerateRecurringChecklistTasks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $templates = TaskChecklistTemplate::query()
            ->where('is_active', true)
            ->where('recurrence', '!=', ChecklistRecurrence::None->value)
            ->with('items')
            ->get();

        $created = 0;

        foreach ($templates as $template) {
            if ($this->hasBeenGeneratedForCurrentPeriod($template, $now)) {
                continue;
            }

            if ($template->items->isEmpty()) {
                $this->warn("Skipped [{$template->name}]: template has no checklist items.");

                continue;
            }

            $task = Task::create([
                'title' => $template->name,
                'task_type' => TaskType::ChecklistGenerated,
                'source_id' => $template->id,
                'source_type' => TaskChecklistTemplate::class,
                'department_id' => $template->department_id,
                'priority' => TaskPriority::NotUrgentImportant,
                'status' => TaskStatus::Todo,
                'due_at' => $now->copy()->endOfDay(),
            ]);

            foreach ($template->items as $item) {
                $task->checklistItems()->create([
                    'checklist_template_item_id' => $item->id,
                    'label' => $item->label,
                ]);
            }

            $created++;
            $this->info("Generated task #{$task->id} from template [{$template->name}].");
        }

        $this->info("Done. {$created} task(s) generated out of {$templates->count()} active template(s).");

        return self::SUCCESS;
    }

    /**
     * A template should only spawn one task per "period" (day for
     * daily/per_shift, calendar week for weekly). We don't need a
     * dedicated column for this: the generated task's own source_id
     * already points back at the template, so we just check whether one
     * was already created in the current period.
     */
    private function hasBeenGeneratedForCurrentPeriod(TaskChecklistTemplate $template, Carbon $now): bool
    {
        $query = Task::query()
            ->where('source_type', TaskChecklistTemplate::class)
            ->where('source_id', $template->id);

        $query = match ($template->recurrence) {
            ChecklistRecurrence::Weekly => $query->whereBetween('created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]),
            // Daily and per-shift both generate at most once per calendar
            // day for now; per-shift will need its own time windows once
            // the shift/attendance module (a later phase) exists.
            default => $query->whereDate('created_at', $now->toDateString()),
        };

        return $query->exists();
    }
}
