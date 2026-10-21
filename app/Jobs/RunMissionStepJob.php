<?php

namespace App\Jobs;

use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionStatus;
use App\Models\AiMission;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One step of a mission round (a specialist, the critic or the coordinator).
 * Each step is its own short job and queues the next, so the host's queue
 * worker never has to hold one job for minutes, a stop request is noticed
 * between steps, and the page can show who is working right now.
 */
class RunMissionStepJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $missionId, public int $userId, public int $round, public int $step) {}

    public function handle(MissionRunner $runner): void
    {
        $mission = AiMission::find($this->missionId);
        $user = User::find($this->userId);

        if (! $mission || ! $user || $mission->status !== AiMissionStatus::Running) {
            return;
        }

        $result = $runner->runStep($mission, $this->step, $this->round, $user);

        if ($result['ok'] && ! ($result['done'] ?? false)) {
            static::dispatch($this->missionId, $this->userId, $this->round, $this->step + 1);
        }
    }

    public function failed(Throwable $exception): void
    {
        $mission = AiMission::find($this->missionId);

        if ($mission && $mission->status === AiMissionStatus::Running) {
            app(MissionRunner::class)->abandonRound($mission, $this->round, 'اجرای مأموریت با خطا متوقف شد.');
        }
    }
}
