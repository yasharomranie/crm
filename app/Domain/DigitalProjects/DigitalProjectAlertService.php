<?php

namespace App\Domain\DigitalProjects;

use App\Enums\DigitalProjectActionStatus;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\User;
use App\Notifications\DigitalProjectsDigestNotification;
use Illuminate\Support\Facades\DB;

/**
 * Sends each project owner (every admin for an ownerless project) one digest
 * of what needs them: open projects with no journal line for a week, projects
 * past their review date and actions past their deadline.
 *
 * Nobody is nagged daily: a project is flagged again only after a week, and an
 * action is flagged once (alerted_at is written straight to the table so the
 * model's updated_at is not bumped).
 */
class DigitalProjectAlertService
{
    /** Days before a project that is still in trouble is flagged again. */
    public const REPEAT_AFTER_DAYS = 7;

    /**
     * @return int how many projects and actions were included in alerts
     */
    public function notifyAttentionNeeded(): int
    {
        $renotifyBefore = now()->subDays(self::REPEAT_AFTER_DAYS);
        $notDueAgainYet = fn ($query) => $query->whereNull('alerted_at')->orWhere('alerted_at', '<=', $renotifyBefore);

        $unattended = DigitalProject::query()->withoutRecentLog()->where($notDueAgainYet)->get();
        $lateReviews = DigitalProject::query()->reviewOverdue()->where($notDueAgainYet)->get();
        $lateActions = DigitalProjectAction::query()
            ->whereIn('status', DigitalProjectActionStatus::unfinishedValues())
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today())
            ->whereNull('alerted_at')
            ->with('project')
            ->get();

        if ($unattended->isEmpty() && $lateReviews->isEmpty() && $lateActions->isEmpty()) {
            return 0;
        }

        $admins = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();

        /** @var array<int, array{user: User, unattended: list<string>, reviews: list<string>, actions: list<string>}> $buckets */
        $buckets = [];

        $add = function (?User $owner, string $kind, string $label) use (&$buckets, $admins): void {
            foreach ($owner ? collect([$owner]) : $admins as $recipient) {
                $buckets[$recipient->id] ??= ['user' => $recipient, 'unattended' => [], 'reviews' => [], 'actions' => []];
                $buckets[$recipient->id][$kind][] = $label;
            }
        };

        $unattended->load('owner')->each(fn (DigitalProject $project) => $add($project->owner, 'unattended', $project->name));
        $lateReviews->load('owner')->each(fn (DigitalProject $project) => $add($project->owner, 'reviews', $project->name));
        $lateActions->each(fn (DigitalProjectAction $action) => $add($action->assignee ?? $action->project?->owner, 'actions', $action->title));

        foreach ($buckets as $bucket) {
            $bucket['user']->notify(new DigitalProjectsDigestNotification($bucket['unattended'], $bucket['reviews'], $bucket['actions']));
        }

        $projectIds = $unattended->pluck('id')->merge($lateReviews->pluck('id'))->unique();
        DB::table('digital_projects')->whereIn('id', $projectIds)->update(['alerted_at' => now()]);
        DB::table('digital_project_actions')->whereIn('id', $lateActions->pluck('id'))->update(['alerted_at' => now()]);

        return $projectIds->count() + $lateActions->count();
    }
}
