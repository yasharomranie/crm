<?php

namespace App\Jobs;

use App\Domain\Ai\Missions\MissionChat;
use App\Enums\AiMissionThreadStatus;
use App\Models\AiMissionThread;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One step of a team conversation (the plan, one member's reply, the critique
 * or the final answer). Each step is its own short job and queues the next, so
 * the page can show the team talking live and no single job lasts for minutes.
 */
class RunMissionChatStepJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $threadId, public int $userId) {}

    public function handle(MissionChat $chat): void
    {
        $thread = AiMissionThread::find($this->threadId);
        $user = User::find($this->userId);

        if (! $thread || ! $user || $thread->status !== AiMissionThreadStatus::Running) {
            return;
        }

        $result = $chat->runStep($thread, $user);

        if ($result['ok'] && ! $result['done'] && ! ($result['wait'] ?? false)) {
            static::dispatch($this->threadId, $this->userId);
        }
    }

    public function failed(Throwable $exception): void
    {
        $thread = AiMissionThread::find($this->threadId);

        if ($thread && $thread->status === AiMissionThreadStatus::Running) {
            $thread->update(['status' => AiMissionThreadStatus::Failed, 'last_error' => 'گفت‌وگو با خطا متوقف شد؛ «ادامه» را بزنید.']);
        }
    }
}
