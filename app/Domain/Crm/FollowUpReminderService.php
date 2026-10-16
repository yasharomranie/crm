<?php

namespace App\Domain\Crm;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Notifications\FollowUpDueNotification;

/**
 * Notifies the assignee of every open CRM follow-up whose due time has
 * arrived - once per task (reminder_sent_at), marked as soon as it's
 * handed to the queue, the same "did we try once" guarantee
 * events:send-reminders gives an EventReminder.
 */
class FollowUpReminderService
{
    public function sendDueReminders(): int
    {
        $tasks = Task::query()
            ->where('task_type', TaskType::CrmFollowUp)
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Overdue])
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->with('assignee')
            ->get();

        foreach ($tasks as $task) {
            $task->assignee?->notify(new FollowUpDueNotification($task));
            $task->update(['reminder_sent_at' => now()]);
        }

        return $tasks->count();
    }
}
