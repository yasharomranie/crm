<?php

namespace App\Console\Commands;

use App\Domain\Ai\Missions\MissionRunner;
use App\Domain\Ai\Missions\MissionSchedule;
use App\Enums\AiMissionStatus;
use App\Jobs\RunMissionStepJob;
use App\Models\AiMission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai-missions:run-due')]
#[Description('Start a new round of every recurring AI mission whose scheduled time has arrived')]
class RunDueAiMissions extends Command
{
    /**
     * A recurring mission starts each cycle clean: the board, the round count and
     * the token meter reset (the previous result stays in its run history) and
     * one round is queued. A mission still running is left alone and retried on
     * the next pass; one that cannot start is reported to its owner and simply
     * waits for its next cycle.
     */
    public function handle(MissionRunner $runner, MissionSchedule $schedule): int
    {
        $due = AiMission::query()
            ->with('creator')
            ->whereNotNull('schedule')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        $started = 0;

        foreach ($due as $mission) {
            if (! $mission->creator) {
                continue;
            }

            if ($mission->status === AiMissionStatus::Running && ! $mission->canRunAnotherRound()) {
                continue;
            }

            $mission->cards()->delete();
            $mission->update(['rounds_run' => 0, 'tokens_used' => 0, 'report' => null, 'budget_alerted' => false, 'status' => AiMissionStatus::Draft]);

            $result = $runner->start($mission, $mission->creator, 'scheduled');

            $mission->update([
                'last_scheduled_at' => now(),
                'next_run_at' => $schedule->nextRun($mission->schedule, $mission->schedule_time, $mission->schedule_weekday, $mission->schedule_day),
            ]);

            if ($result['ok']) {
                RunMissionStepJob::dispatch($mission->id, $mission->creator->id, $result['round'], 0);
                $started++;
            } else {
                $runner->alert($mission, 'failed', 'اجرای زمان‌بندی‌شده شروع نشد: '.($result['error'] ?? 'نامشخص'));
            }
        }

        $this->info("Done. {$started} recurring mission(s) started.");

        return self::SUCCESS;
    }
}
