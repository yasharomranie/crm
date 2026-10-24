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
 * One specialist's answer in a team chat, when the chat runs its specialists
 * in parallel (config ai_assistant.team_chat_parallel). The last specialist to
 * finish queues the next step of the conversation.
 */
class RunMissionChatMemberJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $threadId, public int $userId, public int $profileId) {}

    public function handle(MissionChat $chat): void
    {
        $thread = AiMissionThread::find($this->threadId);
        $user = User::find($this->userId);

        if ($thread && $user) {
            $chat->runAskMember($thread, $user, $this->profileId);
        }
    }

    public function failed(Throwable $exception): void
    {
        $thread = AiMissionThread::find($this->threadId);

        if ($thread && $thread->status === AiMissionThreadStatus::Running) {
            $thread->update(['status' => AiMissionThreadStatus::Failed, 'last_error' => 'جواب یکی از اعضا با خطا متوقف شد؛ «ادامه» را بزنید.']);
        }
    }
}
