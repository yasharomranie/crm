<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the eight attendance reporting tools added on top of the
 * pre-existing get_attendance_summary: record search, a live "who hasn't
 * checked in yet today" list, per-user and per-department breakdowns,
 * unusual-device flags, forgotten checkouts, overtime/undertime vs each
 * employee's shift, and a this-month-vs-last-month trend.
 */
class AiAssistantAttendanceToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل حضور و غیاب']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report',
        ])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());

        $user = User::factory()->create(['name' => 'مدیر آزمایشی']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeToolCallThenAnswer(string $toolName, array $arguments, string $finalAnswer = 'باشه.'): void
    {
        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => $finalAnswer],
                ]],
            ]);
    }

    private function toolResultPayload(): array
    {
        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);

        return json_decode($toolMessage->content, true);
    }

    public function test_search_attendance_records_filters_by_user_and_status(): void
    {
        AiProvider::factory()->active()->create();
        $target = User::factory()->create(['name' => 'نیلوفر احمدی']);
        AttendanceRecord::factory()->create(['user_id' => $target->id, 'status' => AttendanceStatus::Late, 'late_minutes' => 15]);
        AttendanceRecord::factory()->create(['status' => AttendanceStatus::OnTime]); // unrelated user

        $this->fakeToolCallThenAnswer('search_attendance_records', ['user_name' => 'نیلوفر', 'status' => 'late']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'رکوردهای تأخیر نیلوفر رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame('نیلوفر احمدی', $payload['records'][0]['user']);
        $this->assertSame(15, $payload['records'][0]['late_minutes']);
    }

    public function test_not_checked_in_today_lists_the_employee_by_name(): void
    {
        AiProvider::factory()->active()->create();
        // Freeze to a guaranteed non-Friday day so the test is deterministic.
        Carbon::setTestNow(today()->next(Carbon::MONDAY));

        $shift = WorkShift::factory()->create();
        $missing = User::factory()->create(['name' => 'کارمند غایب', 'work_shift_id' => $shift->id]);
        $present = User::factory()->create(['name' => 'کارمند حاضر', 'work_shift_id' => $shift->id]);
        AttendanceRecord::factory()->create([
            'user_id' => $present->id, 'work_shift_id' => $shift->id,
            'date' => today(), 'check_in_at' => today()->setTime(9, 0), 'status' => AttendanceStatus::OnTime,
        ]);

        $this->fakeToolCallThenAnswer('get_not_checked_in_today', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'امروز کیا هنوز ورود نزدن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['not_checked_in_count']);
        $this->assertSame('کارمند غایب', $payload['employees'][0]['name']);
    }

    public function test_attendance_by_user_computes_rate_and_late_minutes(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'کارمند نمونه']);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'date' => today(), 'status' => AttendanceStatus::OnTime, 'worked_minutes' => 480,
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'date' => today()->subDay(), 'status' => AttendanceStatus::Late,
            'late_minutes' => 20, 'worked_minutes' => 460,
        ]);

        $this->fakeToolCallThenAnswer('get_attendance_by_user', ['user_name' => 'کارمند نمونه']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت حضور کارمند نمونه این ماه چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = $payload['users'][0];

        $this->assertSame(1, $row['on_time_days']);
        $this->assertSame(1, $row['late_days']);
        $this->assertSame(0, $row['absent_days']);
        $this->assertSame(20, $row['total_late_minutes']);
        $this->assertEqualsWithDelta(15.7, $row['total_worked_hours'], 0.01);
        $this->assertEquals(100.0, $row['attendance_rate_percent']);
    }

    public function test_department_attendance_summary_ranks_the_worst_on_time_rate_first(): void
    {
        AiProvider::factory()->active()->create();
        $sales = Department::factory()->create(['name' => 'فروش']);
        $support = Department::factory()->create(['name' => 'پشتیبانی']);

        $salesUser = User::factory()->create(['department_id' => $sales->id]);
        $supportUser = User::factory()->create(['department_id' => $support->id]);

        AttendanceRecord::factory()->create(['user_id' => $salesUser->id, 'status' => AttendanceStatus::OnTime]);
        AttendanceRecord::factory()->create(['user_id' => $supportUser->id, 'status' => AttendanceStatus::Late, 'late_minutes' => 30]);

        $this->fakeToolCallThenAnswer('get_department_attendance_summary', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام دپارتمان بدترین وضعیت حضور رو داره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('پشتیبانی', $payload['departments'][0]['department']);
        $this->assertEquals(0, $payload['departments'][0]['on_time_rate_percent']);
        $this->assertEquals(30.0, $payload['departments'][0]['average_late_minutes']);
    }

    public function test_unusual_device_checkins_flags_a_changed_fingerprint(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'کارمند دستگاه']);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'date' => today()->subDays(2), 'check_in_device' => 'device-A',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'date' => today(), 'check_in_device' => 'device-B',
        ]);

        $this->fakeToolCallThenAnswer('get_unusual_device_checkins', ['days' => 7]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'ورود با دستگاه غیرعادی داشتیم؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['flagged_count']);
        $this->assertSame('کارمند دستگاه', $payload['checkins'][0]['user']);
        $this->assertSame('device-B', $payload['checkins'][0]['check_in_device']);
    }

    public function test_missing_checkouts_lists_a_forgotten_checkout(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'کارمند فراموشکار']);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'check_in_at' => today()->setTime(9, 0), 'check_out_at' => null,
        ]);

        $this->fakeToolCallThenAnswer('get_missing_checkouts', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چه کسی چک‌اوت نزده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame('کارمند فراموشکار', $payload['records'][0]['user']);
    }

    public function test_overtime_undertime_summary_compares_actual_to_shift_hours(): void
    {
        AiProvider::factory()->active()->create();
        $shift = WorkShift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00']);
        $user = User::factory()->create(['name' => 'کارمند اضافه‌کار', 'work_shift_id' => $shift->id]);

        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'work_shift_id' => $shift->id, 'date' => today(), 'worked_minutes' => 500,
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'work_shift_id' => $shift->id, 'date' => today()->subDay(), 'worked_minutes' => 400,
        ]);

        $this->fakeToolCallThenAnswer('get_overtime_undertime_summary', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کی کم‌کاری داشته این ماه؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = $payload['users'][0];

        $this->assertEquals(16.0, $row['expected_hours']);
        $this->assertEquals(15.0, $row['actual_hours']);
        $this->assertEquals(-1.0, $row['difference_hours']);
    }

    public function test_attendance_trend_separates_this_month_from_last_month(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create();
        AttendanceRecord::factory()->create(['user_id' => $user->id, 'date' => today(), 'status' => AttendanceStatus::OnTime]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id, 'date' => today()->subMonthNoOverflow(), 'status' => AttendanceStatus::Late, 'late_minutes' => 10,
        ]);

        $this->fakeToolCallThenAnswer('get_attendance_trend', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت حضور این ماه نسبت به ماه قبل چطور شده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['comparison']['این ماه']['on_time']);
        $this->assertEquals(100.0, $payload['comparison']['این ماه']['on_time_rate_percent']);
        $this->assertSame(1, $payload['comparison']['ماه قبل']['late']);
        $this->assertEquals(0.0, $payload['comparison']['ماه قبل']['on_time_rate_percent']);
    }

    public function test_a_user_without_the_report_permission_cannot_use_an_attendance_tool(): void
    {
        AiProvider::factory()->active()->create();
        AttendanceRecord::factory()->create();

        $this->fakeToolCallThenAnswer('search_attendance_records', []);

        // A roleless user keeps unrestricted access under this app's
        // Gate::before bypass (AppServiceProvider::registerAccessControl),
        // so the restriction only takes effect once a role is assigned.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر حضور و غیاب']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'رکوردهای حضور رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }
}
