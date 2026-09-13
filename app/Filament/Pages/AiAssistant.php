<?php

namespace App\Filament\Pages;

use App\Enums\AiMessageRole;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\FormSubmissionStatus;
use App\Enums\ProjectStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Exceptions\AiProviderException;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Deal;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\GuestTicket;
use App\Models\Project;
use App\Models\Room;
use App\Models\SalesChannel;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowInstance;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;

class AiAssistant extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.ai-assistant';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'دستیار هوشمند';

    protected static ?string $title = 'دستیار هوشمند مدیریتی';

    public static function accessControlPermission(): string
    {
        return 'ai-assistant.access';
    }

    public ?int $conversationId = null;

    public string $body = '';

    public bool $isThinking = false;

    public function mount(): void
    {
        $this->conversationId = $this->getConversations()->first()?->id;
    }

    /**
     * @return Collection<int, AiConversation>
     */
    public function getConversations(): Collection
    {
        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->latest('updated_at')
            ->get();
    }

    public function getSelectedConversation(): ?AiConversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->find($this->conversationId);
    }

    public function getMessages(): Collection
    {
        return $this->getSelectedConversation()?->messages()->get() ?? new Collection;
    }

    public function newConversation(): void
    {
        $conversation = AiConversation::create(['user_id' => Auth::id()]);

        $this->conversationId = $conversation->id;
        $this->body = '';
    }

    public function selectConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->body = '';
    }

    /**
     * Sends the user's message, then drives the tool-calling loop until the
     * model returns a final answer (or gives up after a few rounds).
     */
    public function send(): void
    {
        $body = trim($this->body);

        if ($body === '') {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $conversation = $this->getSelectedConversation() ?? AiConversation::create(['user_id' => Auth::id()]);
        $this->conversationId = $conversation->id;

        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => $body]);
        $this->body = '';

        if (! $conversation->title) {
            $conversation->update(['title' => $conversation->deriveTitle()]);
        }

        $this->isThinking = true;

        try {
            $this->runAgentLoop($conversation, $provider);
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } finally {
            $this->isThinking = false;
        }

        $this->dispatch('message-sent');
    }

    private function runAgentLoop(AiConversation $conversation, AiProvider $provider): void
    {
        $messages = $this->buildChatHistory($conversation);
        $tools = $this->getToolDefinitions();

        for ($round = 0; $round < 5; $round++) {
            $assistantMessage = $provider->chatCompletion($messages, $tools);
            $toolCalls = $assistantMessage['tool_calls'] ?? null;

            $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $toolCalls,
            ]);

            $messages[] = $assistantMessage;

            if (! $toolCalls) {
                return;
            }

            foreach ($toolCalls as $toolCall) {
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                $result = $this->executeTool($toolCall['function']['name'], $arguments);
                $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE);

                $conversation->messages()->create([
                    'role' => AiMessageRole::Tool,
                    'content' => $encodedResult,
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolCall['function']['name'],
                ]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => $encodedResult,
                ];
            }
        }

        Notification::make()->title('دستیار پس از چند تلاش هنوز پاسخ نهایی ارائه نداد. دوباره تلاش کنید.')->warning()->send();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChatHistory(AiConversation $conversation): array
    {
        $system = ['role' => 'system', 'content' => $this->systemPrompt()];

        $history = $conversation->messages()->get()->map(fn ($message) => $message->toChatPayload())->all();

        return [$system, ...$history];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            تو دستیار هوشمند مدیریتی یک سامانهٔ یکپارچهٔ مدیریت اقامتگاه، پروژه، فروش و منابع انسانی هستی. کاربر تو، مدیر یا صاحب کسب‌وکار است.
            همیشه به فارسی و روان پاسخ بده. برای هر سؤالی که نیاز به داده دارد، حتماً از ابزارهای موجود استفاده کن - هیچ‌وقت عدد یا آمار را حدس نزن.
            وقتی داده‌های عددی داری، آن‌ها را در قالب جدول Markdown نمایش بده. بعد از هر گزارش، در صورت لزوم یک جمع‌بندی کوتاه و یک پیشنهاد/هشدار عملی اضافه کن.
            اگر کاربر خواست کاری در سیستم انجام شود (مثلاً ایجاد وظیفه)، از ابزار مربوطه استفاده کن و نتیجه را به‌وضوح تأیید کن.
            مبالغ همیشه به تومان هستند.
            PROMPT;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function executeTool(string $name, array $arguments): array
    {
        return match ($name) {
            'get_accommodation_summary' => $this->accommodationSummary(),
            'get_crm_summary' => $this->crmSummary(),
            'get_project_health_summary' => ['projects' => $this->projectHealthSummary()],
            'get_attendance_summary' => $this->attendanceSummary(),
            'get_task_summary' => $this->taskSummary(),
            'get_channel_manager_summary' => ['channels' => $this->channelManagerSummary()],
            'list_alerts' => ['alerts' => static::computeAlerts()],
            'get_form_submissions_summary' => $this->formSubmissionsSummary(),
            'get_daily_checklist_compliance' => $this->dailyChecklistCompliance($arguments),
            'create_task' => $this->createTaskTool($arguments),
            default => ['error' => "ابزار ناشناخته: {$name}"],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        $noParams = ['type' => 'object', 'properties' => (object) [], 'required' => []];

        return [
            $this->toolSchema('get_accommodation_summary', 'Get current room occupancy, today\'s check-ins/outs, and this month\'s accommodation revenue.', $noParams),
            $this->toolSchema('get_crm_summary', 'Get the sales pipeline (deals grouped by stage with counts and values) and support ticket / SLA status.', $noParams),
            $this->toolSchema('get_project_health_summary', 'Get earned-value (EVM) health for every active project: earned/planned value percent, cost variance, schedule variance.', $noParams),
            $this->toolSchema('get_attendance_summary', 'Get this calendar month\'s staff attendance: on-time/late/absent counts and the users with the most lateness.', $noParams),
            $this->toolSchema('get_task_summary', 'Get the overdue task count and a breakdown of all tasks by status.', $noParams),
            $this->toolSchema('get_channel_manager_summary', 'Get commission owed and active booking counts per OTA sales channel.', $noParams),
            $this->toolSchema('list_alerts', 'Get the system\'s current rule-based alerts across all modules (overdue tasks, over-budget projects, breached ticket SLAs, chronic lateness, low occupancy).', $noParams),
            $this->toolSchema('get_form_submissions_summary', 'Get a system-wide snapshot of the dynamic form builder & approval workflow module: how many submissions are pending approval (overall and per form), this month\'s approved/rejected counts, and how many in-progress approvals have breached their step\'s SLA.', $noParams),
            $this->toolSchema('get_daily_checklist_compliance', 'For a specific recurring/daily form template (e.g. an employee\'s daily checklist), check which days in "this_week" or "this_month" (Jalali calendar) have no submission - i.e. which days were missed. Optionally scope to one employee by name; otherwise every employee who has ever submitted that form is checked. Use this for questions like "which days did an employee not submit their daily checklist this month".', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Name (or partial name) of the form template to check'],
                    'user_name' => ['type' => 'string', 'description' => 'Name (or partial name) of a specific employee to scope the check to - omit to check every employee who has submitted this form'],
                    'period' => ['type' => 'string', 'enum' => ['this_week', 'this_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => ['form_template_name'],
            ]),
            $this->toolSchema('create_task', 'Create a new task/action item and assign it to a department - use this when the user explicitly asks to create, assign, or schedule a task.', [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Task title'],
                    'department_name' => ['type' => 'string', 'description' => 'Name (or partial name) of the department to assign this to'],
                    'priority' => ['type' => 'string', 'enum' => ['urgent_important', 'not_urgent_important', 'urgent_not_important', 'neither']],
                    'due_at' => ['type' => 'string', 'description' => 'Due date/time as an ISO 8601 or natural date string'],
                ],
                'required' => ['title'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function toolSchema(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }

    // --- Tool implementations -------------------------------------------------

    private function accommodationSummary(): array
    {
        $totalRooms = Room::where('is_active', true)->count();
        $occupied = Room::where('is_active', true)->where('status', RoomStatus::Occupied)->count();
        $available = Room::where('is_active', true)->where('status', RoomStatus::Available)->count();
        $needsCleaning = Room::where('is_active', true)->where('status', RoomStatus::NeedsCleaning)->count();

        $checkInsToday = Booking::whereDate('check_in_date', today())
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->count();

        $checkOutsToday = Booking::whereDate('check_out_date', today())
            ->where('status', BookingStatus::CheckedIn)
            ->count();

        $revenueThisMonth = (int) BookingCharge::whereHas('booking', function ($query) {
            $query->where('status', BookingStatus::CheckedOut)
                ->whereMonth('actual_check_out_at', now()->month)
                ->whereYear('actual_check_out_at', now()->year);
        })->get()->sum(fn (BookingCharge $charge) => $charge->quantity * $charge->unit_amount);

        return [
            'total_rooms' => $totalRooms,
            'occupied' => $occupied,
            'available' => $available,
            'needs_cleaning' => $needsCleaning,
            'occupancy_rate_percent' => $totalRooms > 0 ? round($occupied / $totalRooms * 100, 1) : 0,
            'check_ins_today' => $checkInsToday,
            'check_outs_today' => $checkOutsToday,
            'revenue_this_month_toman' => $revenueThisMonth,
        ];
    }

    private function crmSummary(): array
    {
        $dealsByStage = Deal::query()
            ->selectRaw('stage, count(*) as deal_count, sum(value) as total_value')
            ->groupBy('stage')
            ->get()
            ->map(fn ($row) => [
                'stage' => $row->stage->getLabel(),
                'count' => (int) $row->deal_count,
                'total_value_toman' => (int) $row->total_value,
            ]);

        $openTickets = GuestTicket::whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->count();
        $breachedTickets = GuestTicket::whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->where('sla_due_at', '<', now())
            ->count();

        return [
            'deals_by_stage' => $dealsByStage->all(),
            'open_tickets' => $openTickets,
            'sla_breached_tickets' => $breachedTickets,
        ];
    }

    private function projectHealthSummary(): array
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Planning, ProjectStatus::InProgress])
            ->get()
            ->map(fn (Project $project) => [
                'title' => $project->title,
                'earned_value_percent' => round($project->earnedValuePercent(), 1),
                'planned_value_percent' => round($project->plannedValuePercent(), 1),
                'cost_variance_toman' => (int) $project->costVariance(),
                'schedule_variance_toman' => (int) $project->scheduleVariance(),
            ])
            ->all();
    }

    private function attendanceSummary(): array
    {
        $records = AttendanceRecord::with('user')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();

        $topLateUsers = $records->where('status', AttendanceStatus::Late)
            ->groupBy('user_id')
            ->map(fn (Collection $group) => [
                'user' => $group->first()->user?->name,
                'late_days' => $group->count(),
                'total_late_minutes' => (int) $group->sum('late_minutes'),
            ])
            ->sortByDesc('total_late_minutes')
            ->take(5)
            ->values();

        return [
            'on_time_count' => $records->where('status', AttendanceStatus::OnTime)->count(),
            'late_count' => $records->where('status', AttendanceStatus::Late)->count(),
            'absent_count' => $records->where('status', AttendanceStatus::Absent)->count(),
            'top_late_users' => $topLateUsers->all(),
        ];
    }

    private function taskSummary(): array
    {
        $overdue = Task::whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $byStatus = Task::query()
            ->selectRaw('status, count(*) as task_count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->getLabel() => (int) $row->task_count]);

        return [
            'overdue_count' => $overdue,
            'by_status' => $byStatus->all(),
        ];
    }

    private function channelManagerSummary(): array
    {
        return SalesChannel::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (SalesChannel $channel) => [
                'name' => $channel->name,
                'commission_rate_percent' => (float) $channel->commission_rate,
                'active_bookings' => $channel->bookings()->where('status', '!=', BookingStatus::Cancelled)->count(),
                'commission_owed_toman' => (int) $channel->bookings()->sum('commission_amount'),
            ])
            ->all();
    }

    private function formSubmissionsSummary(): array
    {
        $pendingByTemplate = FormSubmission::query()
            ->where('status', FormSubmissionStatus::PendingApproval)
            ->with('formTemplate')
            ->get()
            ->groupBy(fn (FormSubmission $submission) => $submission->formTemplate->title)
            ->map(fn (Collection $group) => $group->count());

        $thisMonthByStatus = FormSubmission::query()
            ->whereIn('status', [FormSubmissionStatus::Approved, FormSubmissionStatus::Rejected])
            ->whereMonth('submitted_at', now()->month)
            ->whereYear('submitted_at', now()->year)
            ->selectRaw('status, count(*) as submission_count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->getLabel() => (int) $row->submission_count]);

        // A step's SLA is measured from when the instance last moved to it -
        // there's no dedicated "entered current step at" column, but
        // updated_at is bumped by the very same act() call that advances
        // current_step_id, so it's an accurate stand-in.
        $slaBreached = WorkflowInstance::query()
            ->where('status', WorkflowInstanceStatus::InProgress)
            ->with('currentStep')
            ->get()
            ->filter(fn (WorkflowInstance $instance) => $instance->currentStep?->sla_hours
                && $instance->updated_at->diffInHours(now()) > $instance->currentStep->sla_hours)
            ->count();

        return [
            'pending_total' => $pendingByTemplate->sum(),
            'pending_by_form' => $pendingByTemplate->all(),
            'this_month_by_status' => $thisMonthByStatus->all(),
            'sla_breached_count' => $slaBreached,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dailyChecklistCompliance(array $arguments): array
    {
        $templateName = trim((string) ($arguments['form_template_name'] ?? ''));
        $template = FormTemplate::query()->where('title', 'like', "%{$templateName}%")->first();

        if (! $template) {
            return ['error' => "فرمی با نام مشابه «{$templateName}» پیدا نشد."];
        }

        $period = $arguments['period'] ?? 'this_month';
        $anchor = Jalalian::now();
        [$start, $end] = $period === 'this_week'
            ? [$anchor->getFirstDayOfWeek(), $anchor->getEndDayOfWeek()]
            : [$anchor->getFirstDayOfMonth(), $anchor->getEndDayOfMonth()];

        $startDate = $start->toCarbon()->startOfDay();
        // Never flag a future day as "missed" - clamp the range's end to now.
        $endDate = $end->toCarbon()->endOfDay()->min(now());

        $usersQuery = User::query();
        $userNameFilter = trim((string) ($arguments['user_name'] ?? ''));

        if ($userNameFilter !== '') {
            $usersQuery->where('name', 'like', "%{$userNameFilter}%");
        } else {
            // No employee named - fall back to everyone who has ever
            // submitted this form, as a proxy for "who it applies to".
            $usersQuery->whereHas('formSubmissions', fn ($query) => $query->where('form_template_id', $template->id));
        }

        $users = $usersQuery->get();

        if ($users->isEmpty()) {
            return ['error' => $userNameFilter !== ''
                ? "کارمندی با نام مشابه «{$userNameFilter}» پیدا نشد."
                : 'هیچ کارمندی تاکنون این فرم را ثبت نکرده است.'];
        }

        $expectedDates = collect();

        for ($day = $startDate->copy(); $day->lte($endDate); $day->addDay()) {
            $expectedDates->push($day->toDateString());
        }

        $compliance = $users->map(function (User $user) use ($template, $startDate, $endDate, $expectedDates) {
            $submittedDates = FormSubmission::query()
                ->where('form_template_id', $template->id)
                ->where('submitted_by', $user->id)
                ->whereBetween('submitted_at', [$startDate, $endDate])
                ->pluck('submitted_at')
                ->map(fn ($date) => $date->toDateString())
                ->unique();

            $missedDates = $expectedDates->diff($submittedDates)
                ->map(fn (string $date) => Jalalian::fromCarbon(Carbon::parse($date))->format('%d %B'))
                ->values();

            return [
                'user' => $user->name,
                'expected_days' => $expectedDates->count(),
                'submitted_days' => $submittedDates->count(),
                'missed_days' => $missedDates->all(),
            ];
        });

        return [
            'form' => $template->title,
            'period' => $period === 'this_week' ? 'این هفته' : 'این ماه',
            'compliance' => $compliance->all(),
        ];
    }

    private function createTaskTool(array $arguments): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));

        if ($title === '') {
            return ['error' => 'عنوان وظیفه الزامی است.'];
        }

        $department = null;

        if (filled($arguments['department_name'] ?? null)) {
            $department = Department::where('name', 'like', '%'.$arguments['department_name'].'%')->first();
        }

        $task = Task::create([
            'title' => $title,
            'task_type' => TaskType::Manual,
            'department_id' => $department?->id,
            'priority' => TaskPriority::tryFrom($arguments['priority'] ?? '') ?? TaskPriority::Neither,
            'status' => TaskStatus::Todo,
            'due_at' => filled($arguments['due_at'] ?? null) ? Carbon::parse($arguments['due_at']) : null,
        ]);

        return [
            'success' => true,
            'task_id' => $task->id,
            'title' => $task->title,
            'department' => $department?->name,
            'message' => "وظیفهٔ «{$task->title}» با موفقیت ایجاد شد.",
        ];
    }

    /**
     * Deterministic, rule-based alerts across every module - not generated
     * by the language model, so they're reliable even before the user asks
     * anything. The chat can reference these via the list_alerts tool; the
     * page also renders them directly in a sidebar panel.
     *
     * @return array<int, array{severity: string, message: string}>
     */
    public static function computeAlerts(): array
    {
        $alerts = [];

        $overdueTasks = Task::whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        if ($overdueTasks > 0) {
            $alerts[] = ['severity' => 'warning', 'message' => "{$overdueTasks} وظیفه از مهلت انجام خود گذشته است."];
        }

        Project::whereIn('status', [ProjectStatus::Planning, ProjectStatus::InProgress])
            ->get()
            ->each(function (Project $project) use (&$alerts) {
                if ($project->costVariance() < 0) {
                    $alerts[] = ['severity' => 'danger', 'message' => "پروژهٔ «{$project->title}» بیش از بودجهٔ برنامه‌ریزی‌شده هزینه کرده است."];
                }
            });

        $breachedTickets = GuestTicket::whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->where('sla_due_at', '<', now())
            ->count();

        if ($breachedTickets > 0) {
            $alerts[] = ['severity' => 'danger', 'message' => "{$breachedTickets} تیکت پشتیبانی از مهلت SLA عبور کرده است."];
        }

        $chronicallyLateCount = AttendanceRecord::where('status', AttendanceStatus::Late)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('user_id, count(*) as late_days')
            ->groupBy('user_id')
            ->having('late_days', '>=', 3)
            ->get()
            ->count();

        if ($chronicallyLateCount > 0) {
            $alerts[] = ['severity' => 'warning', 'message' => "{$chronicallyLateCount} نفر بیش از ۳ بار در این ماه با تأخیر حضور یافته‌اند."];
        }

        $totalRooms = Room::where('is_active', true)->count();

        if ($totalRooms > 0) {
            $occupied = Room::where('is_active', true)->where('status', RoomStatus::Occupied)->count();

            if (($occupied / $totalRooms) < 0.3) {
                $alerts[] = ['severity' => 'info', 'message' => 'نرخ اشغال اقامتگاه‌ها زیر ۳۰٪ است.'];
            }
        }

        return $alerts;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = count(static::computeAlerts());

        return $count > 0 ? (string) $count : null;
    }
}
