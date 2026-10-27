<?php

namespace Tests\Feature;

use App\Domain\DigitalProjects\DigitalProjectManagementReport;
use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectMetric;
use App\Enums\DigitalProjectStatus;
use App\Enums\DigitalProjectWorkCategory;
use App\Filament\Pages\DigitalProjectWeeklyEntry;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Filament\Resources\DigitalProjects\RelationManagers\LogsRelationManager;
use App\Filament\Widgets\DigitalProjectQuickLogWidget;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\DigitalProjectLog;
use App\Models\DigitalProjectStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects: the weekly management matrix (A4 landscape), its quick
 * entry grid and the work categories behind the «توسعه» and «عملکرد» columns.
 */
class DigitalProjectManagementReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function week(): array
    {
        return DigitalProjectManagementReport::weekOf(Carbon::parse('2026-10-28'));
    }

    private function cells(DigitalProject $project): array
    {
        [$start, $end] = $this->week();

        $report = app(DigitalProjectManagementReport::class)->build($start, $end);

        foreach ($report['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['project']->is($project)) {
                    return $row['cells'];
                }
            }
        }

        $this->fail('project is not in the report');
    }

    public function test_a_week_runs_from_saturday_to_friday(): void
    {
        [$start, $end] = $this->week();

        $this->assertSame(Carbon::SATURDAY, $start->dayOfWeek);
        $this->assertSame(Carbon::FRIDAY, $end->dayOfWeek);
        $this->assertSame(6, (int) $start->diffInDays($end));
        $this->assertTrue($start->lte(Carbon::parse('2026-10-28')) && $end->gte(Carbon::parse('2026-10-28')));
    }

    public function test_the_new_kinds_exist_and_sort_in_the_reports_order(): void
    {
        $labels = collect(DigitalProjectChannel::cases())->map->getLabel()->all();

        $this->assertSame(['سایت', 'اینستاگرام', 'تلگرام', 'بله', 'پروژه', 'برون‌سپاری'], array_slice($labels, 0, 6));
        $this->assertSame('متفرقه', DigitalProjectChannel::Other->getLabel());
    }

    public function test_flow_numbers_are_summed_over_the_week_and_ignore_other_weeks(): void
    {
        [$start, $end] = $this->week();
        $project = DigitalProject::factory()->create();
        $make = fn (DigitalProjectMetric $metric, int $value, Carbon $on) => DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'metric' => $metric, 'value' => $value, 'recorded_on' => $on]);
        $make(DigitalProjectMetric::Posts, 3, $start->copy()->addDay());
        $make(DigitalProjectMetric::Posts, 2, $end);
        $make(DigitalProjectMetric::Posts, 99, $start->copy()->subDay());
        $make(DigitalProjectMetric::Stories, 10, $end);
        $make(DigitalProjectMetric::Purchases, 4, $end);

        $cells = $this->cells($project);

        $this->assertSame(5, $cells['posts']);
        $this->assertSame(10, $cells['stories']);
        $this->assertSame(4, $cells['purchases']);
        $this->assertNull($cells['visitors']);
    }

    public function test_follower_growth_is_this_weeks_count_minus_the_last_one_before_the_week(): void
    {
        [$start, $end] = $this->week();
        $project = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1000, 'recorded_on' => $start->copy()->subDays(3)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1075, 'recorded_on' => $end]);

        $this->assertSame(75, $this->cells($project)['followers_growth']);
    }

    public function test_growth_is_empty_without_a_reading_before_or_inside_the_week(): void
    {
        [$start, $end] = $this->week();
        $onlyThisWeek = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $onlyThisWeek->id, 'value' => 500, 'recorded_on' => $end]);
        $stale = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $stale->id, 'value' => 400, 'recorded_on' => $start->copy()->subDays(20)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $stale->id, 'value' => 450, 'recorded_on' => $start->copy()->subDays(10)]);

        $this->assertNull($this->cells($onlyThisWeek)['followers_growth']);
        $this->assertNull($this->cells($stale)['followers_growth']);
    }

    public function test_a_drop_in_followers_is_negative(): void
    {
        [$start, $end] = $this->week();
        $project = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1000, 'recorded_on' => $start->copy()->subDay()]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 960, 'recorded_on' => $end]);

        $this->assertSame(-40, $this->cells($project)['followers_growth']);
        $this->assertSame('−۴۰', DigitalProjectManagementReport::format('followers_growth', -40));
        $this->assertSame('+۷۵', DigitalProjectManagementReport::format('followers_growth', 75));
        $this->assertSame('۱۲', DigitalProjectManagementReport::format('posts', 12));
        $this->assertSame('', DigitalProjectManagementReport::format('posts', null));
    }

    public function test_finished_work_is_counted_per_category_from_actions_and_done_journal_lines(): void
    {
        [$start, $end] = $this->week();
        $project = DigitalProject::factory()->create();
        $inWeek = $start->copy()->addDays(2);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'status' => DigitalProjectActionStatus::Done, 'completed_at' => $inWeek, 'work_category' => DigitalProjectWorkCategory::Security]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'status' => DigitalProjectActionStatus::Done, 'completed_at' => $end->copy()->endOfDay(), 'work_category' => DigitalProjectWorkCategory::Security]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'status' => DigitalProjectActionStatus::Done, 'completed_at' => $start->copy()->subDay(), 'work_category' => DigitalProjectWorkCategory::Security]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'work_category' => DigitalProjectWorkCategory::Security]);
        DigitalProjectLog::factory()->count(3)->create(['digital_project_id' => $project->id, 'logged_on' => $inWeek, 'work_category' => DigitalProjectWorkCategory::Seo]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'logged_on' => $inWeek, 'kind' => DigitalProjectLogKind::Problem, 'work_category' => DigitalProjectWorkCategory::Seo]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'logged_on' => $inWeek]);

        $cells = $this->cells($project);

        $this->assertSame(2, $cells['security']);
        $this->assertSame(3, $cells['seo']);
        $this->assertNull($cells['coding']);
        $this->assertNull($cells['articles']);
    }

    public function test_finished_projects_are_left_out_and_empty_kinds_are_skipped(): void
    {
        DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Website, 'name' => 'سایت فعال']);
        DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Bale, 'name' => 'کانال بله', 'status' => DigitalProjectStatus::Done]);
        [$start, $end] = $this->week();

        $groups = app(DigitalProjectManagementReport::class)->build($start, $end)['groups'];

        $this->assertSame([DigitalProjectChannel::Website], $groups->pluck('channel')->all());
    }

    public function test_the_columns_match_the_manager_sheet(): void
    {
        $groups = DigitalProjectManagementReport::columnGroups();

        $this->assertSame(['وضعیت', 'توسعه', 'عملکرد'], array_column($groups, 'title'));
        $this->assertSame(
            ['تعداد پست', 'تعداد استوری', 'رشد فالور', 'بازدیدکننده', 'خرید از سایت'],
            array_column($groups[0]['columns'], 'label'),
        );
        $this->assertSame(['بهبود بصری', 'کدنویسی', 'امنیت'], array_column($groups[1]['columns'], 'label'));
        $this->assertSame(['سئو', 'مقالات', 'تولید محتوا', 'نظارت ایندکسی'], array_column($groups[2]['columns'], 'label'));
        $this->assertCount(12, DigitalProjectManagementReport::columnKeys());
    }

    public function test_the_report_page_is_a4_landscape_with_the_projects_and_their_numbers(): void
    {
        [$start, $end] = $this->week();
        $project = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Instagram, 'name' => 'پیج هتل بوتیک آوا']);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'metric' => DigitalProjectMetric::Posts, 'value' => 7, 'recorded_on' => $end]);

        $this->actingAs($this->admin())
            ->get(route('digital-projects.management-report', ['date' => $end->toDateString()]))
            ->assertOk()
            ->assertSee('size: A4 landscape', false)
            ->assertSee('وضعیت جامع مدیا')
            ->assertSee('پیج هتل بوتیک آوا')
            ->assertSee('اینستاگرام')
            ->assertSee('نظارت ایندکسی')
            ->assertSee('۷');
    }

    public function test_the_excel_export_downloads(): void
    {
        DigitalProject::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('digital-projects.management-report.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_the_report_needs_the_list_permission(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی گزارش مدیریتی']));

        $this->actingAs($user)->get(route('digital-projects.management-report'))->assertForbidden();
        $this->actingAs($user)->get(route('digital-projects.management-report.excel'))->assertForbidden();
    }

    public function test_the_list_page_offers_the_report_and_the_entry_page_has_its_own_permission(): void
    {
        $this->artisan('access-control:sync');

        $this->assertTrue(Permission::where('name', 'digital-project-weekly-entry.access')->exists());

        Livewire::actingAs($this->admin())->test(ListDigitalProjects::class)->assertActionExists('managementReport');
    }

    public function test_the_entry_grid_saves_one_reading_per_cell_dated_the_end_of_the_week(): void
    {
        $admin = $this->admin();
        $project = DigitalProject::factory()->create();

        $page = Livewire::actingAs($admin)->test(DigitalProjectWeeklyEntry::class);
        $weekEnd = $page->get('weekEnd');

        $page
            ->set("values.{$project->id}.posts", '5')
            ->set("values.{$project->id}.followers", '1200')
            ->call('save')
            ->assertHasNoErrors();

        $posts = DigitalProjectStat::where('digital_project_id', $project->id)->where('metric', 'posts')->firstOrFail();

        $this->assertSame(5, $posts->value);
        $this->assertSame($weekEnd, $posts->recorded_on->toDateString());
        $this->assertSame($admin->id, $posts->user_id);
        $this->assertSame(1200, DigitalProjectStat::where('metric', 'followers')->firstOrFail()->value);
        $this->assertSame(2, DigitalProjectStat::count());
    }

    public function test_saving_again_updates_the_cell_and_clearing_it_deletes_the_reading(): void
    {
        $project = DigitalProject::factory()->create();
        $page = Livewire::actingAs($this->admin())->test(DigitalProjectWeeklyEntry::class);

        $page->set("values.{$project->id}.posts", '5')->call('save');
        $page->set("values.{$project->id}.posts", '8')->call('save');

        $this->assertSame(1, DigitalProjectStat::count());
        $this->assertSame(8, DigitalProjectStat::firstOrFail()->value);

        $page->set("values.{$project->id}.posts", '')->call('save');

        $this->assertSame(0, DigitalProjectStat::count());
    }

    public function test_the_grid_prefills_the_weeks_saved_numbers_and_moves_between_weeks(): void
    {
        $project = DigitalProject::factory()->create();
        $first = Livewire::actingAs($this->admin())->test(DigitalProjectWeeklyEntry::class);
        $first->set("values.{$project->id}.stories", '12')->call('save');

        $fresh = Livewire::actingAs($this->admin())->test(DigitalProjectWeeklyEntry::class);
        $this->assertSame(12, $fresh->get("values.{$project->id}.stories"));

        $fresh->call('shiftWeek', -1);
        $this->assertNull($fresh->get("values.{$project->id}.stories"));
    }

    public function test_a_negative_or_decimal_number_is_rejected(): void
    {
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(DigitalProjectWeeklyEntry::class)
            ->set("values.{$project->id}.posts", '-3')
            ->call('save')
            ->assertHasErrors(["values.{$project->id}.posts"]);

        $this->assertSame(0, DigitalProjectStat::count());
    }

    public function test_the_work_category_is_saved_from_the_journal_and_the_quick_entry(): void
    {
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(LogsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->mountTableAction('create')
            ->setTableActionData(['kind' => DigitalProjectLogKind::Done->value, 'work_category' => DigitalProjectWorkCategory::Coding->value, 'body' => 'رفع باگ فرم'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        Livewire::actingAs($this->admin())
            ->test(DigitalProjectQuickLogWidget::class)
            ->set('projectId', $project->id)
            ->set('workCategory', DigitalProjectWorkCategory::Seo->value)
            ->set('body', 'بهینه‌سازی عنوان‌ها')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            [DigitalProjectWorkCategory::Coding, DigitalProjectWorkCategory::Seo],
            $project->logs()->orderBy('id')->get()->map->work_category->all(),
        );
    }
}
