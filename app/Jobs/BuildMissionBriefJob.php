<?php

namespace App\Jobs;

use App\Domain\Ai\Missions\MissionBriefBuilder;
use App\Enums\AiMissionBriefStatus;
use App\Models\AiMission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Builds a mission's draft strategy brief from its attached files. */
class BuildMissionBriefJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $missionId) {}

    public function handle(MissionBriefBuilder $builder): void
    {
        $mission = AiMission::find($this->missionId);

        if ($mission && $mission->brief_status === AiMissionBriefStatus::Building) {
            $builder->build($mission);
        }
    }

    public function failed(Throwable $exception): void
    {
        AiMission::whereKey($this->missionId)->update(['brief_status' => AiMissionBriefStatus::Failed->value, 'brief_error' => 'ساخت خلاصه با خطا متوقف شد.']);
    }
}
