<?php

namespace Tests\Feature;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Resources\DigitalProjects\Pages\CreateDigitalProject;
use App\Filament\Resources\DigitalProjects\Pages\EditDigitalProject;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use App\Models\DigitalProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects (phase 1): the websites, Instagram pages, Telegram
 * channels, LinkedIn and WhatsApp pages kept as short briefs and shown in one
 * table grouped by kind.
 */
class DigitalProjectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_module_has_its_own_permissions(): void
    {
        $this->artisan('access-control:sync');

        foreach (['view_any', 'view', 'create', 'update', 'delete', 'delete_any'] as $action) {
            $this->assertTrue(Permission::where('name', "digital-projects.{$action}")->exists(), $action);
        }
    }

    public function test_a_user_without_the_permission_cannot_open_the_list(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی پروژه دیجیتال']));

        $this->actingAs($user)->get(DigitalProjectResource::getUrl('index'))->assertForbidden();
    }

    public function test_the_table_groups_projects_by_kind_in_a_fixed_order_with_counts(): void
    {
        $site = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Website, 'name' => 'سایت اصلی']);
        DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Website, 'name' => 'سایت رزرو']);
        $linkedin = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Linkedin, 'name' => 'لینکدین شرکت']);
        $instagram = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Instagram, 'name' => 'پیج هتل']);

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->assertCanSeeTableRecords([$site, $instagram, $linkedin])
            ->assertSee('سایت‌ها (۲)')
            ->assertSee('اینستاگرام (۱)')
            ->assertSeeHtmlInOrder(['سایت‌ها', 'اینستاگرام', 'لینکدین']);
    }

    public function test_the_filters_narrow_by_status_kind_and_late_reviews(): void
    {
        $attention = DigitalProject::factory()->create(['status' => DigitalProjectStatus::NeedsAttention]);
        $active = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active]);
        $late = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active, 'next_review_at' => now()->subDays(3)]);
        $telegram = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Telegram, 'status' => DigitalProjectStatus::Planning]);
        $component = Livewire::actingAs($this->admin())->test(ListDigitalProjects::class);

        $component->filterTable('status', DigitalProjectStatus::NeedsAttention->value)
            ->assertCanSeeTableRecords([$attention])
            ->assertCanNotSeeTableRecords([$active, $late, $telegram]);

        $component->removeTableFilters()->filterTable('channel', DigitalProjectChannel::Telegram->value)
            ->assertCanSeeTableRecords([$telegram])
            ->assertCanNotSeeTableRecords([$attention, $active]);

        $component->removeTableFilters()->filterTable('review_overdue', true)
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$attention, $active, $telegram]);
    }

    public function test_only_open_projects_with_a_passed_review_date_count_as_late(): void
    {
        $late = DigitalProject::factory()->create(['status' => DigitalProjectStatus::NeedsAttention, 'next_review_at' => now()->subDay()]);
        $paused = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Paused, 'next_review_at' => now()->subDay()]);
        $done = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Done, 'next_review_at' => now()->subWeek()]);
        $future = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active, 'next_review_at' => now()->addDay()]);
        $undated = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active, 'next_review_at' => null]);

        $this->assertTrue($late->isReviewOverdue());
        $this->assertFalse($paused->isReviewOverdue());
        $this->assertFalse($done->isReviewOverdue());
        $this->assertFalse($future->isReviewOverdue());
        $this->assertFalse($undated->isReviewOverdue());
        $this->assertSame([$late->id], DigitalProject::query()->reviewOverdue()->pluck('id')->all());
    }

    public function test_a_project_is_created_with_its_kind_description_and_creator(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateDigitalProject::class)
            ->fillForm([
                'channel' => DigitalProjectChannel::Instagram->value,
                'name' => 'صفحهٔ اینستاگرام هتل',
                'handle' => '@hotel_hamedan',
                'status' => DigitalProjectStatus::Active->value,
                'description' => 'پست‌های هفتگی و استوری روزانه',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = DigitalProject::where('name', 'صفحهٔ اینستاگرام هتل')->firstOrFail();

        $this->assertSame(DigitalProjectChannel::Instagram, $project->channel);
        $this->assertSame(DigitalProjectStatus::Active, $project->status);
        $this->assertSame('@hotel_hamedan', $project->handle);
        $this->assertSame($admin->id, $project->created_by);
    }

    public function test_the_kind_and_name_are_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateDigitalProject::class)
            ->fillForm(['channel' => null, 'name' => null])
            ->call('create')
            ->assertHasFormErrors(['channel' => 'required', 'name' => 'required']);
    }

    public function test_the_status_can_be_changed_on_the_edit_page(): void
    {
        $project = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active]);

        Livewire::actingAs($this->admin())
            ->test(EditDigitalProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['status' => DigitalProjectStatus::NeedsAttention->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(DigitalProjectStatus::NeedsAttention, $project->refresh()->status);
    }

    public function test_the_project_page_shows_the_description(): void
    {
        $project = DigitalProject::factory()->create(['name' => 'کانال تلگرام', 'description' => 'اطلاع‌رسانی روزانهٔ مهمان‌ها']);

        $this->actingAs($this->admin())
            ->get(DigitalProjectResource::getUrl('view', ['record' => $project]))
            ->assertOk()
            ->assertSee('کانال تلگرام')
            ->assertSee('اطلاع‌رسانی روزانهٔ مهمان‌ها');
    }

    public function test_the_stats_above_the_table_count_projects_by_state(): void
    {
        DigitalProject::factory()->count(2)->create(['status' => DigitalProjectStatus::Active]);
        DigitalProject::factory()->create(['status' => DigitalProjectStatus::NeedsAttention]);
        DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active, 'next_review_at' => now()->subDays(2)]);

        Livewire::actingAs($this->admin())
            ->test(DigitalProjectsStatsWidget::class)
            ->assertSee('کل پروژه‌ها')
            ->assertSee('۴')
            ->assertSee('نیازمند توجه')
            ->assertSee('بررسی عقب‌افتاده');
    }

    public function test_the_list_and_the_dashboard_open_for_an_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(DigitalProjectResource::getUrl('index'))->assertOk()->assertSee('data-page-help="digital-projects"', false);
        $this->actingAs($admin)->get(Dashboard::getUrl())->assertOk()->assertDontSee('بررسی عقب‌افتاده');
    }
}
