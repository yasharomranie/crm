<?php

namespace App\Domain\Ai\Missions;

use App\Enums\AiMissionStatus;
use App\Models\AiMission;
use App\Models\AiMissionRun;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The operations room at a glance: what needs the manager's decision, what is
 * running right now, which missions repeat on their own and what the latest
 * results were. Only the user's own missions are ever counted.
 */
class MissionDashboard
{
    /**
     * @return array{tiles: array<string, int>, attention: list<array<string, mixed>>, running: list<array<string, mixed>>, recurring: list<array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function build(User $user): array
    {
        $missions = AiMission::query()->ownedBy($user)->get();
        $runs = AiMissionRun::query()->whereIn('ai_mission_id', $missions->pluck('id'))->get();
        $monthStart = now()->startOfMonth();

        return [
            'tiles' => [
                'running' => $missions->where('status', AiMissionStatus::Running)->count(),
                'waiting' => $missions->where('status', AiMissionStatus::WaitingApproval)->count(),
                'problems' => $missions->whereIn('status', [AiMissionStatus::Failed, AiMissionStatus::Stopped])->count(),
                'recurring' => $missions->whereNotNull('schedule')->count(),
                'tokens_month' => (int) $runs->filter(fn (AiMissionRun $run) => $run->finished_at && $run->finished_at->gte($monthStart))->sum('tokens_used')
                    + (int) $missions->where('status', AiMissionStatus::Running)->sum('tokens_used'),
            ],
            'attention' => $missions
                ->whereIn('status', [AiMissionStatus::WaitingApproval, AiMissionStatus::Failed, AiMissionStatus::Stopped])
                ->sortBy(fn (AiMission $mission) => [$mission->status === AiMissionStatus::WaitingApproval ? 0 : 1, -$mission->updated_at->timestamp])
                ->values()
                ->map(fn (AiMission $mission) => [
                    'id' => $mission->id,
                    'title' => $mission->title,
                    'status' => $mission->status->getLabel(),
                    'tone' => $mission->status === AiMissionStatus::WaitingApproval ? 'warn' : 'bad',
                    'detail' => $mission->status === AiMissionStatus::WaitingApproval
                        ? count($mission->report['decisions'] ?? []).' پیشنهاد منتظر نظر شماست'
                        : Str::limit((string) $mission->last_error, 110),
                    'at' => $mission->updated_at->diffForHumans(),
                ])->all(),
            'running' => $missions->where('status', AiMissionStatus::Running)->values()->map(fn (AiMission $mission) => [
                'id' => $mission->id,
                'title' => $mission->title,
                'step' => $mission->current_step ?? 'در حال کار…',
                'percent' => $mission->step_total > 0 ? min(100, (int) round($mission->step_index / $mission->step_total * 100)) : 0,
            ])->all(),
            'recurring' => $missions->whereNotNull('schedule')->sortBy('next_run_at')->values()->map(fn (AiMission $mission) => [
                'id' => $mission->id,
                'title' => $mission->title,
                'every' => app(MissionSchedule::class)->describe($mission->schedule, $mission->schedule_time, $mission->schedule_weekday, $mission->schedule_day),
                'next' => $mission->next_run_at?->copy()->timezone(MissionSchedule::TIMEZONE)->toJalali(),
                'last' => $mission->last_scheduled_at?->diffForHumans(),
            ])->all(),
            'recent' => $runs->sortByDesc(fn (AiMissionRun $run) => $run->finished_at?->timestamp ?? 0)->take(6)->values()->map(fn (AiMissionRun $run) => [
                'mission_id' => $run->ai_mission_id,
                'title' => $missions->firstWhere('id', $run->ai_mission_id)?->title,
                'when' => $run->finished_at?->copy()->timezone(MissionSchedule::TIMEZONE)->toJalali(),
                'scheduled' => $run->trigger === 'scheduled',
                'decisions' => count($run->report['decisions'] ?? []),
                'summary' => Str::limit((string) $run->summary, 100),
            ])->all(),
        ];
    }
}
