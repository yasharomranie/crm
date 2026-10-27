<?php

namespace App\Http\Controllers;

use App\Domain\DigitalProjects\DigitalProjectManagementReport;
use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectLogKind;
use App\Models\DigitalProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Morilog\Jalali\Jalalian;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function management(Request $request): View
    {
        abort_unless($request->user()?->can('digital-projects.view_any'), 403);

        return view('digital-projects.print-management', [
            'report' => $this->managementReport($request),
            'columnGroups' => DigitalProjectManagementReport::columnGroups(),
            'generatedBy' => $request->user()->name,
            'excelUrl' => route('digital-projects.management-report.excel', $request->only('date')),
        ]);
    }

    public function managementExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('digital-projects.view_any'), 403);

        $report = $this->managementReport($request);
        $groups = DigitalProjectManagementReport::columnGroups();
        $keys = DigitalProjectManagementReport::columnKeys();
        $title = 'وضعیت جامع مدیا — '.Number::toPersian(Jalalian::fromCarbon($report['end'])->format('%d %B %Y'));

        return response()->streamDownload(function () use ($report, $groups, $keys, $title): void {
            $path = tempnam(sys_get_temp_dir(), 'management_report_');
            $writer = new Writer;
            $writer->openToFile($path);

            $header = (new Style)->setFontBold()->setBackgroundColor('F3F4F6');

            $writer->addRow(Row::fromValues([$title], (new Style)->setFontBold()));
            $writer->addRow(Row::fromValues(['موضوع', 'نام پیج', ...collect($groups)->flatMap(fn (array $group) => array_column($group['columns'], 'label'))->all()], $header));

            foreach ($report['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    $writer->addRow(Row::fromValues([
                        $group['channel']->getLabel(),
                        $row['project']->name,
                        ...array_map(fn (string $key) => DigitalProjectManagementReport::format($key, $row['cells'][$key]), $keys),
                    ]));
                }
            }

            $writer->close();
            readfile($path);
            unlink($path);
        }, 'گزارش-مدیریتی-پروژه‌های-دیجیتال.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @return array{start: Carbon, end: Carbon, groups: Collection<int, array<string, mixed>>}
     */
    private function managementReport(Request $request): array
    {
        try {
            $day = $request->filled('date') ? Carbon::parse((string) $request->query('date')) : today();
        } catch (\Throwable) {
            $day = today();
        }

        [$start, $end] = DigitalProjectManagementReport::weekOf($day);

        return app(DigitalProjectManagementReport::class)->build($start, $end);
    }
}
