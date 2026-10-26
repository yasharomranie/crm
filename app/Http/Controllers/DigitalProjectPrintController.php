<?php

namespace App\Http\Controllers;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectLogKind;
use App\Models\DigitalProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Print-friendly pages for the digital projects: the brief of one project, and
 * a weekly report across all of them. "Save as PDF" in the browser's print
 * dialog turns either into a PDF.
 */
class DigitalProjectPrintController extends Controller
{
    public function show(Request $request, DigitalProject $project): View
    {
        abort_unless($request->user()?->can('digital-projects.view'), 403);

        $project->load('owner');

        return view('digital-projects.print-project', [
            'project' => $project,
            'actions' => $project->actions()->with('assignee')->orderByRaw("case status when 'done' then 1 else 0 end")->orderBy('due_on')->get(),
            'logs' => $project->logs()->with('author')->orderByDesc('logged_on')->orderByDesc('id')->limit(30)->get(),
            'changes' => $project->statusChanges()->with('author')->orderByDesc('id')->limit(15)->get(),
            'generatedBy' => $request->user()->name,
        ]);
    }

    public function weekly(Request $request): View
    {
        abort_unless($request->user()?->can('digital-projects.view_any'), 403);

        $days = min(max($request->integer('days', 7), 1), 90);
        $since = today()->subDays($days - 1);

        $projects = DigitalProject::query()
            ->with([
                'owner',
                'stats',
                'logs' => fn ($logs) => $logs->whereDate('logged_on', '>=', $since)->with('author')->orderBy('logged_on')->orderBy('id'),
                'statusChanges' => fn ($changes) => $changes->where('created_at', '>=', $since)->orderBy('id'),
                'actions' => fn ($actions) => $actions->whereIn('status', DigitalProjectActionStatus::unfinishedValues())->orderBy('due_on'),
            ])
            ->orderBy('name')
            ->get();

        return view('digital-projects.print-weekly', [
            'projects' => $projects,
            'since' => $since,
            'days' => $days,
            'kinds' => DigitalProjectLogKind::cases(),
            'generatedBy' => $request->user()->name,
            'totalLogs' => $projects->sum(fn (DigitalProject $project) => $project->logs->count()),
        ]);
    }
}
