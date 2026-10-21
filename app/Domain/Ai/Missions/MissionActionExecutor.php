<?php

namespace App\Domain\Ai\Missions;

use App\Domain\Ai\AiToolAccess;
use App\Enums\AiMissionActionStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AiMissionAction;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;
use Throwable;

/**
 * Turns the team's proposals into real changes, but only the kinds listed in
 * ACTION_TOOLS and only after the mission's owner approves them. The owner's
 * own permissions are checked again at approval time, so a mission can never
 * do more than the person who approved it could do by hand.
 */
class MissionActionExecutor
{
    public const MAX_PROPOSALS_PER_ROUND = 5;

    /** Tools a mission may propose; each is run for real only on approval. */
    public const ACTION_TOOLS = ['create_task'];

    /**
     * Cleans one proposal from the coordinator; null when it is not something
     * the owner could approve (unknown tool, no title, no permission).
     *
     * @param  array<string, mixed>  $proposal
     * @return array{tool: string, arguments: array<string, mixed>, summary: string, reason: ?string}|null
     */
    public function sanitize(array $proposal, User $owner): ?array
    {
        $tool = (string) ($proposal['tool'] ?? 'create_task');

        if (! in_array($tool, self::ACTION_TOOLS, true) || ! $this->canAct($owner, $tool)) {
            return null;
        }

        $title = Str::limit(trim((string) ($proposal['title'] ?? '')), 200, '');

        if ($title === '') {
            return null;
        }

        $arguments = ['title' => $title];

        if (filled($proposal['department_name'] ?? null)) {
            $arguments['department_name'] = Str::limit(trim((string) $proposal['department_name']), 120, '');
        }

        if (filled($proposal['due_at'] ?? null)) {
            $arguments['due_at'] = Str::limit(trim((string) $proposal['due_at']), 30, '');
        }

        if (TaskPriority::tryFrom((string) ($proposal['priority'] ?? '')) !== null) {
            $arguments['priority'] = (string) $proposal['priority'];
        }

        return [
            'tool' => $tool,
            'arguments' => $arguments,
            'summary' => $this->summarize($tool, $arguments),
            'reason' => filled($proposal['reason'] ?? null) ? Str::limit(trim((string) $proposal['reason']), 400, '') : null,
        ];
    }

    public function canAct(User $user, string $tool): bool
    {
        return $user->can('ai-assistant.act') && AiToolAccess::allowsTool($user, $tool);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function summarize(string $tool, array $arguments): string
    {
        return 'ایجاد وظیفهٔ «'.$arguments['title'].'»'
            .(filled($arguments['department_name'] ?? null) ? ' برای دپارتمان '.$arguments['department_name'] : '')
            .(filled($arguments['due_at'] ?? null) ? ' با مهلت '.$arguments['due_at'] : '');
    }

    /**
     * Runs a pending action for its owner. An action is decided once: a second
     * call (a double click, a stale page) does nothing.
     */
    public function approve(AiMissionAction $action, User $user): AiMissionAction
    {
        if (! $this->claim($action, $user, AiMissionActionStatus::Executed)) {
            return $action;
        }

        if (! $this->canAct($user, $action->tool)) {
            return $this->fail($action, 'اجازهٔ انجام این اقدام را ندارید.');
        }

        try {
            $result = $this->run($action->tool, $action->arguments);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($action, 'اجرای اقدام با خطای غیرمنتظره روبه‌رو شد.');
        }

        if (isset($result['error'])) {
            return $this->fail($action, (string) $result['error']);
        }

        $action->update(['status' => AiMissionActionStatus::Executed, 'result' => $result, 'error' => null]);

        return $action;
    }

    public function reject(AiMissionAction $action, User $user): AiMissionAction
    {
        $this->claim($action, $user, AiMissionActionStatus::Rejected);

        return $action;
    }

    /**
     * Marks a pending action as decided in one atomic update, so only the
     * first of two racing requests goes on to run it.
     */
    private function claim(AiMissionAction $action, User $user, AiMissionActionStatus $status): bool
    {
        $claimed = AiMissionAction::query()
            ->whereKey($action->id)
            ->where('status', AiMissionActionStatus::Pending->value)
            ->update(['status' => $status->value, 'decided_by' => $user->id, 'decided_at' => now()]);

        $action->refresh();

        return $claimed === 1;
    }

    private function fail(AiMissionAction $action, string $error): AiMissionAction
    {
        $action->update(['status' => AiMissionActionStatus::Failed, 'error' => $error]);

        return $action;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function run(string $tool, array $arguments): array
    {
        return match ($tool) {
            'create_task' => $this->createTask($arguments),
            default => ['error' => 'این نوع اقدام پشتیبانی نمی‌شود.'],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createTask(array $arguments): array
    {
        $department = null;

        if (filled($arguments['department_name'] ?? null)) {
            $name = trim((string) $arguments['department_name']);
            $department = Department::query()->where('name', $name)->first() ?? Department::query()->where('name', 'like', "%{$name}%")->first();

            if (! $department) {
                return ['error' => "دپارتمانی با نام «{$name}» پیدا نشد؛ وظیفه‌ای ساخته نشد."];
            }
        }

        $dueAt = null;

        if (filled($arguments['due_at'] ?? null)) {
            try {
                $dueAt = Carbon::parse($arguments['due_at']);
            } catch (Throwable) {
                return ['error' => "تاریخ «{$arguments['due_at']}» قابل تشخیص نبود؛ وظیفه‌ای ساخته نشد."];
            }
        }

        $task = Task::create([
            'title' => $arguments['title'],
            'task_type' => TaskType::Manual,
            'department_id' => $department?->id,
            'priority' => TaskPriority::tryFrom($arguments['priority'] ?? '') ?? TaskPriority::Neither,
            'status' => TaskStatus::Todo,
            'due_at' => $dueAt,
        ]);

        return [
            'task_id' => $task->id,
            'title' => $task->title,
            'department' => $department?->name,
            'due_at_jalali' => $task->due_at ? Jalalian::fromCarbon($task->due_at)->format('%d %B %Y') : null,
        ];
    }
}
