<?php

namespace App\Domain\Crm;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\Task;

/**
 * Applies the admin-defined DealAutomationRule rows to a deal that has just
 * been created in, or moved to, a stage: each matching active rule
 * schedules a CRM follow-up task (the same kind staff create by hand, so
 * it shows on the deal, in the assignee's dashboard widget and gets the
 * due-time reminder push).
 */
class DealAutomationService
{
    public function handleStageEntered(Deal $deal): int
    {
        $rules = DealAutomationRule::query()
            ->where('is_active', true)
            ->where('trigger_stage', $deal->stage)
            ->get();

        $created = 0;

        foreach ($rules as $rule) {
            $title = str_replace('{deal}', $deal->title, $rule->task_title);

            // Moving a deal back and forth must not stack up duplicates of a
            // follow-up nobody has done yet.
            $alreadyPending = $deal->followUpTasks()
                ->where('title', $title)
                ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Overdue])
                ->exists();

            if ($alreadyPending) {
                continue;
            }

            Task::create([
                'title' => $title,
                'task_type' => TaskType::CrmFollowUp,
                'source_type' => Deal::class,
                'source_id' => $deal->id,
                'assigned_to' => $rule->assigned_to ?? $deal->assigned_to,
                'due_at' => now()->addHours($rule->due_after_hours),
            ]);

            $created++;
        }

        return $created;
    }
}
