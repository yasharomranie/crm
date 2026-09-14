<?php

namespace App\Filament\Pages;

use App\Enums\AiMessageRole;
use App\Enums\ApproverType;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\FormFieldType;
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
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;

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
        $base = <<<'PROMPT'
            تو دستیار هوشمند مدیریتی یک سامانهٔ یکپارچهٔ مدیریت اقامتگاه، پروژه، فروش و منابع انسانی هستی. کاربر تو، مدیر یا صاحب کسب‌وکار است.
            همیشه به فارسی و روان پاسخ بده. برای هر سؤالی که نیاز به داده دارد، حتماً از ابزارهای موجود استفاده کن - هیچ‌وقت عدد یا آمار را حدس نزن.
            وقتی داده‌های عددی داری، آن‌ها را در قالب جدول Markdown نمایش بده. بعد از هر گزارش، در صورت لزوم یک جمع‌بندی کوتاه و یک پیشنهاد/هشدار عملی اضافه کن.
            اگر کاربر خواست کاری در سیستم انجام شود (مثلاً ایجاد وظیفه)، از ابزار مربوطه استفاده کن و نتیجه را به‌وضوح تأیید کن.
            مبالغ همیشه به تومان هستند.

            دربارهٔ طراحی گردش‌کار و قالب فرم (design_workflow_draft و design_form_template_draft):
            - اگر فرم درخواستی نیاز به چرخهٔ تأیید چندمرحله‌ای دارد، اول با design_workflow_draft گردش‌کار را بساز و بعد workflow_definition_id برگشتی را به design_form_template_draft بده. اگر فرم ساده است و نیاز به تأیید ندارد، فقط design_form_template_draft کافی است.
            - هیچ‌وقت نام دپارتمان، کاربر یا نقش سازمانی را حدس نزن یا با نام مشابه/نزدیک جایگزین نکن؛ همیشه از لیستی که در همین پیام سیستم به تو داده شده استفاده کن. اگر چیزی که کاربر گفته دقیقاً در لیست نبود، اسم ندادنش را به کاربر بگو و از او بخواه دقیق‌تر مشخص کند یا نام‌های نزدیک موجود را پیشنهاد بده - هرگز آن را حدسی نساز.
            - هر گردش‌کار و فرمی که با این ابزارها ساخته می‌شود، همیشه و بدون استثنا به‌صورت «غیرفعال» (پیش‌نویس) ذخیره می‌شود و تا وقتی یک انسان آن را از پنل بررسی و فعال نکند، در هیچ ثبت واقعی‌ای استفاده نخواهد شد - این محدودیت را همیشه صریح به کاربر یادآوری کن تا غافلگیر نشود.
            PROMPT;

        // The model has no reliable notion of "today" on its own (it can
        // guess a stale or wrong date from training), which was causing
        // wrong due_at values whenever the user said a relative date like
        // "فردا" or "جمعه". Telling it the real current date/time on every
        // request, and requiring create_task's due_at in a fixed Gregorian
        // format, removes that guesswork.
        $now = now();
        $dateNote = sprintf(
            'تاریخ و ساعت فعلی سیستم: %s میلادی (برابر با %s شمسی). هر تاریخ نسبی که کاربر می‌گوید (فردا، جمعه، دو روز دیگر، هفتهٔ آینده و...) را دقیقاً بر اساس همین تاریخ محاسبه کن، نه بر اساس حدس یا اطلاعات قدیمی خودت. هنگام صدا زدن ابزار create_task، مقدار due_at را همیشه به‌صورت تاریخ کامل میلادی با فرمت Y-m-d H:i بفرست. بعد از اجرای create_task، اگر due_at ثبت شده بود، برای گفتن تاریخ به کاربر دقیقاً از همان رشتهٔ due_at_jalali که خود ابزار در خروجی برمی‌گرداند استفاده کن - هیچ‌وقت خودت میلادی را به شمسی تبدیل نکن، چون این تبدیل دستی همیشه اشتباه از آب درمی‌آید.',
            $now->format('Y-m-d H:i (l)'),
            Jalalian::fromCarbon($now)->format('%d %B %Y ساعت %H:%M'),
        );

        // The model can't see the departments table, so if it invents a
        // plausible-but-wrong department name, create_task's lookup was
        // silently leaving the task's department empty. Giving it the real
        // list up front makes it use an exact, existing name instead of
        // guessing one.
        $departments = Department::query()->orderBy('name')->pluck('name');
        $departmentNote = $departments->isNotEmpty()
            ? 'دپارتمان‌های موجود در سیستم (برای پارامتر department_name دقیقاً از یکی از همین نام‌ها استفاده کن، نه نام حدسی یا مشابه): '.$departments->implode('، ').'.'
            : null;

        // design_workflow_draft needs real, exact user/role names to
        // resolve approver_user_name/approver_role_name - same guessing
        // problem as departments above, just for workflow steps instead of
        // tasks.
        $users = User::query()->orderBy('name')->pluck('name');
        $userNote = $users->isNotEmpty()
            ? 'کاربران موجود در سیستم (برای approver_user_name دقیقاً از یکی از همین نام‌ها استفاده کن): '.$users->implode('، ').'.'
            : null;

        $roles = Role::query()->orderBy('name')->pluck('name');
        $roleNote = $roles->isNotEmpty()
            ? 'نقش‌های سازمانی موجود در سیستم (برای approver_role_name دقیقاً از یکی از همین نام‌ها استفاده کن): '.$roles->implode('، ').'.'
            : null;

        return collect([$base, $dateNote, $departmentNote, $userNote, $roleNote])->filter()->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * Reporting tools require "ai-assistant.report"; action tools (that
     * change data) require "ai-assistant.act". Both getToolDefinitions()
     * (what the model is even offered) and executeTool() (a defense-in-depth
     * check in case a stale tool_call from history is ever replayed) enforce
     * this, so a user who loses a permission can never get an action tool to
     * actually run even if the model still asks for it.
     */
    private function executeTool(string $name, array $arguments): array
    {
        $actionTools = ['create_task', 'design_workflow_draft', 'design_form_template_draft'];

        if (in_array($name, $actionTools, true) && ! Auth::user()?->can('ai-assistant.act')) {
            return ['error' => 'شما اجازهٔ استفاده از این ابزار (اقدام) را ندارید.'];
        }

        if (! in_array($name, $actionTools, true) && ! Auth::user()?->can('ai-assistant.report')) {
            return ['error' => 'شما اجازهٔ استفاده از این ابزار (گزارش‌گیری) را ندارید.'];
        }

        // design_workflow_draft / design_form_template_draft create real
        // structural records (even though always as an inactive draft), so
        // beyond the general "ai-assistant.act" gate above, each also
        // requires the acting user's own normal resource permission - the
        // AI must never let someone create through chat what they couldn't
        // create through the regular panel.
        $requiredResourcePermission = match ($name) {
            'design_workflow_draft' => 'workflow-definitions.create',
            'design_form_template_draft' => 'form-templates.create',
            default => null,
        };

        if ($requiredResourcePermission && ! Auth::user()?->can($requiredResourcePermission)) {
            return ['error' => 'شما اجازهٔ ایجاد این نوع رکورد را در سیستم ندارید.'];
        }

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
            'design_workflow_draft' => $this->designWorkflowDraftTool($arguments),
            'design_form_template_draft' => $this->designFormTemplateDraftTool($arguments),
            default => ['error' => "ابزار ناشناخته: {$name}"],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        $noParams = ['type' => 'object', 'properties' => (object) [], 'required' => []];
        $user = Auth::user();
        $tools = [];

        if ($user?->can('ai-assistant.report')) {
            $tools[] = $this->toolSchema('get_accommodation_summary', 'Get current room occupancy, today\'s check-ins/outs, and this month\'s accommodation revenue.', $noParams);
            $tools[] = $this->toolSchema('get_crm_summary', 'Get the sales pipeline (deals grouped by stage with counts and values) and support ticket / SLA status.', $noParams);
            $tools[] = $this->toolSchema('get_project_health_summary', 'Get earned-value (EVM) health for every active project: earned/planned value percent, cost variance, schedule variance.', $noParams);
            $tools[] = $this->toolSchema('get_attendance_summary', 'Get this calendar month\'s staff attendance: on-time/late/absent counts and the users with the most lateness.', $noParams);
            $tools[] = $this->toolSchema('get_task_summary', 'Get the overdue task count and a breakdown of all tasks by status.', $noParams);
            $tools[] = $this->toolSchema('get_channel_manager_summary', 'Get commission owed and active booking counts per OTA sales channel.', $noParams);
            $tools[] = $this->toolSchema('list_alerts', 'Get the system\'s current rule-based alerts across all modules (overdue tasks, over-budget projects, breached ticket SLAs, chronic lateness, low occupancy).', $noParams);
            $tools[] = $this->toolSchema('get_form_submissions_summary', 'Get a system-wide snapshot of the dynamic form builder & approval workflow module: how many submissions are pending approval (overall and per form), this month\'s approved/rejected counts, and how many in-progress approvals have breached their step\'s SLA.', $noParams);
            $tools[] = $this->toolSchema('get_daily_checklist_compliance', 'For a specific recurring/daily form template (e.g. an employee\'s daily checklist), check which days in "this_week" or "this_month" (Jalali calendar) have no submission - i.e. which days were missed. Optionally scope to one employee by name; otherwise every employee who has ever submitted that form is checked. Use this for questions like "which days did an employee not submit their daily checklist this month".', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Name (or partial name) of the form template to check'],
                    'user_name' => ['type' => 'string', 'description' => 'Name (or partial name) of a specific employee to scope the check to - omit to check every employee who has submitted this form'],
                    'period' => ['type' => 'string', 'enum' => ['this_week', 'this_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => ['form_template_name'],
            ]);
        }

        if ($user?->can('ai-assistant.act')) {
            $tools[] = $this->toolSchema('create_task', 'Create a new task/action item and assign it to a department - use this when the user explicitly asks to create, assign, or schedule a task.', [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Task title'],
                    'department_name' => ['type' => 'string', 'description' => 'Exact name of an existing department to assign this to - use one of the department names given to you in the system prompt, never a guessed or paraphrased name'],
                    'priority' => ['type' => 'string', 'enum' => ['urgent_important', 'not_urgent_important', 'urgent_not_important', 'neither']],
                    'due_at' => ['type' => 'string', 'description' => 'Due date/time in Y-m-d H:i Gregorian format, calculated from the current date given to you in the system prompt - never guess the year'],
                ],
                'required' => ['title'],
            ]);

            if ($user->can('workflow-definitions.create')) {
                $tools[] = $this->toolSchema('design_workflow_draft', 'Design a new multi-step approval workflow as an INACTIVE draft - use when the manager asks to create/design a گردش‌کار (workflow) for form approvals. Always created disabled; a human must review and activate it manually in the panel before any form can use it. Call this before design_form_template_draft when the form needs an approval cycle.', [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Workflow name'],
                        'steps' => [
                            'type' => 'array',
                            'description' => 'Ordered list of approval steps, in the order they happen',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'description' => 'Step name, e.g. "تأیید دفتر فنی"'],
                                    'approver_type' => [
                                        'type' => 'string',
                                        'enum' => ['specific_user', 'department_manager', 'role'],
                                        'description' => 'department_manager = the manager of whichever department submitted the form (no name needed); specific_user = one named person (requires approver_user_name, an exact name from the system prompt); role = anyone holding a named organizational role (requires approver_role_name, an exact name from the system prompt)',
                                    ],
                                    'approver_user_name' => ['type' => 'string', 'description' => 'Required when approver_type is specific_user - must exactly match a name given to you in the system prompt'],
                                    'approver_role_name' => ['type' => 'string', 'description' => 'Required when approver_type is role - must exactly match a name given to you in the system prompt'],
                                    'sla_hours' => ['type' => 'integer', 'description' => 'Optional response deadline in hours for this step'],
                                ],
                                'required' => ['name', 'approver_type'],
                            ],
                        ],
                    ],
                    'required' => ['name', 'steps'],
                ]);
            }

            if ($user->can('form-templates.create')) {
                $tools[] = $this->toolSchema('design_form_template_draft', 'Design a new form template with its fields as an INACTIVE draft - use when the manager asks to create/design a فرم (form). Always created disabled; a human must review, adjust if needed, and activate it manually in the panel before employees can submit it. If the form needs an approval cycle, call design_workflow_draft first and pass its returned workflow_definition_id here.', [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'description' => 'Optional short description of what this form is for'],
                        'department_name' => ['type' => 'string', 'description' => 'Optional - exact name of an existing department this form belongs to, from the system prompt'],
                        'workflow_definition_id' => ['type' => 'integer', 'description' => 'Optional - the id returned by a design_workflow_draft call made earlier in this same conversation'],
                        'fields' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field_key' => ['type' => 'string', 'description' => 'English snake_case technical key, unique within the form, e.g. subject or requested_amount'],
                                    'label' => ['type' => 'string', 'description' => 'Persian display label'],
                                    'field_type' => ['type' => 'string', 'enum' => ['text', 'textarea', 'number', 'date_jalali', 'select', 'radio', 'checkbox', 'file', 'signature', 'gps']],
                                    'is_required' => ['type' => 'boolean'],
                                    'options' => ['type' => 'object', 'description' => 'Required only for select/radio fields - a map of stored value to Persian label, e.g. {"low": "کم", "high": "زیاد"}'],
                                    'workflow_step_name' => ['type' => 'string', 'description' => 'Optional - if set, this field is only filled in by whoever acts at that named step of the linked workflow (e.g. "نظر دفتر فنی"), instead of by the original submitter. Must exactly match a step name passed to design_workflow_draft.'],
                                ],
                                'required' => ['field_key', 'label', 'field_type'],
                            ],
                        ],
                    ],
                    'required' => ['title', 'fields'],
                ]);
            }
        }

        return $tools;
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
            $department = $this->findDepartmentByName($arguments['department_name']);

            // Never silently drop a department the user explicitly asked
            // for - that used to create the task anyway with an empty
            // department and no indication anything went wrong. Refuse and
            // hand back the real list so the model retries with an exact
            // name (or asks the user) instead of guessing.
            if (! $department) {
                return [
                    'error' => "دپارتمانی با نام «{$arguments['department_name']}» پیدا نشد. هیچ وظیفه‌ای ساخته نشد.",
                    'available_departments' => Department::query()->orderBy('name')->pluck('name')->all(),
                ];
            }
        }

        $dueAt = null;

        if (filled($arguments['due_at'] ?? null)) {
            try {
                $dueAt = Carbon::parse($arguments['due_at']);
            } catch (\Throwable) {
                return ['error' => "تاریخ «{$arguments['due_at']}» قابل تشخیص نبود. هیچ وظیفه‌ای ساخته نشد."];
            }
        }

        $task = Task::create([
            'title' => $title,
            'task_type' => TaskType::Manual,
            'department_id' => $department?->id,
            'priority' => TaskPriority::tryFrom($arguments['priority'] ?? '') ?? TaskPriority::Neither,
            'status' => TaskStatus::Todo,
            'due_at' => $dueAt,
        ]);

        return [
            'success' => true,
            'task_id' => $task->id,
            'title' => $task->title,
            'department' => $department?->name,
            'due_at_gregorian' => $task->due_at?->format('Y-m-d H:i'),
            // Pre-converted so the model never does its own Gregorian ->
            // Jalali math when telling the user the due date back - that
            // conversion is exactly where it kept getting the date wrong,
            // even though due_at itself was already saved correctly.
            'due_at_jalali' => $task->due_at ? Jalalian::fromCarbon($task->due_at)->format('%d %B %Y') : null,
            'message' => "وظیفهٔ «{$task->title}» با موفقیت ایجاد شد.",
        ];
    }

    /**
     * Exact match first (after trimming), then falls back to a partial
     * match - so a model-supplied name that's merely a substring of the
     * real one (e.g. "فروش" for "دپارتمان فروش") still resolves, while an
     * exact real name is never shadowed by a coincidental partial match.
     */
    private function findDepartmentByName(string $name): ?Department
    {
        $name = trim($name);

        return Department::query()->where('name', $name)->first()
            ?? Department::query()->where('name', 'like', "%{$name}%")->first();
    }

    /**
     * Exact match first, then partial - same lookup shape as
     * findDepartmentByName(), used for approver_user_name.
     */
    private function findUserByName(string $name): ?User
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return User::query()->where('name', $name)->first()
            ?? User::query()->where('name', 'like', "%{$name}%")->first();
    }

    /**
     * Exact match first, then partial - same lookup shape as
     * findDepartmentByName(), used for approver_role_name.
     */
    private function findRoleByName(string $name): ?Role
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Role::query()->where('name', $name)->first()
            ?? Role::query()->where('name', 'like', "%{$name}%")->first();
    }

    /**
     * Creates a new approval workflow (name + ordered steps) as an
     * INACTIVE draft - see the "never auto-activate" and "never guess a
     * missing department/user/role" principles explained in
     * getToolDefinitions()'s design_workflow_draft description. Every
     * approver is resolved and validated BEFORE anything is written to the
     * database: either the whole workflow is created correctly, or nothing
     * is created at all - never a half-built workflow with a broken step.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function designWorkflowDraftTool(array $arguments): array
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        $stepsInput = $arguments['steps'] ?? [];

        if ($name === '') {
            return ['error' => 'نام گردش‌کار الزامی است. هیچ گردش‌کاری ساخته نشد.'];
        }

        if (! is_array($stepsInput) || count($stepsInput) === 0) {
            return ['error' => 'حداقل یک مرحلهٔ تأیید لازم است. هیچ گردش‌کاری ساخته نشد.'];
        }

        $resolvedSteps = [];

        foreach (array_values($stepsInput) as $index => $step) {
            $stepNumber = $index + 1;
            $stepName = trim((string) ($step['name'] ?? ''));
            $approverType = ApproverType::tryFrom((string) ($step['approver_type'] ?? ''));

            if ($stepName === '' || ! $approverType) {
                return ['error' => "مرحلهٔ شمارهٔ {$stepNumber} نام یا نوع تأییدکنندهٔ معتبر ندارد. هیچ گردش‌کاری ساخته نشد."];
            }

            $approverUserId = null;
            $approverRoleId = null;

            if ($approverType === ApproverType::SpecificUser) {
                $approverName = (string) ($step['approver_user_name'] ?? '');
                $approverUser = $this->findUserByName($approverName);

                if (! $approverUser) {
                    return [
                        'error' => "کاربری با نام «{$approverName}» برای مرحلهٔ «{$stepName}» پیدا نشد. هیچ گردش‌کاری ساخته نشد.",
                        'available_users' => User::query()->orderBy('name')->pluck('name')->all(),
                    ];
                }

                $approverUserId = $approverUser->id;
            } elseif ($approverType === ApproverType::Role) {
                $approverName = (string) ($step['approver_role_name'] ?? '');
                $approverRole = $this->findRoleByName($approverName);

                if (! $approverRole) {
                    return [
                        'error' => "نقشی با نام «{$approverName}» برای مرحلهٔ «{$stepName}» پیدا نشد. هیچ گردش‌کاری ساخته نشد.",
                        'available_roles' => Role::query()->orderBy('name')->pluck('name')->all(),
                    ];
                }

                $approverRoleId = $approverRole->id;
            }
            // ApproverType::DepartmentManager needs no lookup here - it's
            // resolved dynamically per submission from the submitter's own
            // department (see WorkflowStep::resolveApprovers()).

            $resolvedSteps[] = [
                'name' => $stepName,
                'approver_type' => $approverType,
                'approver_user_id' => $approverUserId,
                'approver_role_id' => $approverRoleId,
                'sla_hours' => filled($step['sla_hours'] ?? null) ? (int) $step['sla_hours'] : null,
            ];
        }

        $workflow = DB::transaction(function () use ($name, $resolvedSteps) {
            $workflow = WorkflowDefinition::create([
                'name' => $name,
                'subject_type' => FormSubmission::class,
                // Always a draft - a human must review and activate this
                // manually before any form can use it. Never exposed as a
                // tool parameter the model could set to true.
                'is_active' => false,
            ]);

            foreach ($resolvedSteps as $order => $step) {
                $workflow->steps()->create([
                    'step_order' => $order + 1,
                    'name' => $step['name'],
                    'approver_type' => $step['approver_type'],
                    'approver_user_id' => $step['approver_user_id'],
                    'approver_role_id' => $step['approver_role_id'],
                    'sla_hours' => $step['sla_hours'],
                ]);
            }

            return $workflow;
        });

        return [
            'success' => true,
            'workflow_definition_id' => $workflow->id,
            'name' => $workflow->name,
            'steps' => $workflow->steps()->orderBy('step_order')->pluck('name')->all(),
            'message' => "گردش‌کار «{$workflow->name}» با ".count($resolvedSteps).' مرحله به‌صورت پیش‌نویس (غیرفعال) ساخته شد. تا وقتی یک مدیر آن را از بخش «فرم‌ساز و گردش‌کار ← گردش‌کارهای تأیید» بررسی و فعال نکند، در هیچ فرمی قابل استفاده نیست.',
        ];
    }

    /**
     * Creates a new form template (+ its fields) as an INACTIVE draft -
     * mirrors designWorkflowDraftTool()'s "resolve everything first, write
     * nothing until it's all valid" shape. See getToolDefinitions()'s
     * design_form_template_draft description for the safety principles.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function designFormTemplateDraftTool(array $arguments): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));
        $fieldsInput = $arguments['fields'] ?? [];

        if ($title === '') {
            return ['error' => 'عنوان قالب فرم الزامی است. هیچ فرمی ساخته نشد.'];
        }

        if (! is_array($fieldsInput) || count($fieldsInput) === 0) {
            return ['error' => 'حداقل یک فیلد برای فرم لازم است. هیچ فرمی ساخته نشد.'];
        }

        $workflow = null;

        if (filled($arguments['workflow_definition_id'] ?? null)) {
            $workflow = WorkflowDefinition::find($arguments['workflow_definition_id']);

            if (! $workflow) {
                return ['error' => "گردش‌کاری با شناسهٔ «{$arguments['workflow_definition_id']}» پیدا نشد. هیچ فرمی ساخته نشد."];
            }
        }

        $department = null;

        if (filled($arguments['department_name'] ?? null)) {
            $department = $this->findDepartmentByName($arguments['department_name']);

            if (! $department) {
                return [
                    'error' => "دپارتمانی با نام «{$arguments['department_name']}» پیدا نشد. هیچ فرمی ساخته نشد.",
                    'available_departments' => Department::query()->orderBy('name')->pluck('name')->all(),
                ];
            }
        }

        // Step names -> ids, scoped to the linked workflow only - a field
        // can never be scoped to some other workflow's step.
        $stepIdsByName = $workflow ? $workflow->steps()->pluck('id', 'name') : collect();

        $resolvedFields = [];
        $seenKeys = [];

        foreach (array_values($fieldsInput) as $index => $field) {
            $fieldNumber = $index + 1;
            $fieldKey = trim((string) ($field['field_key'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));
            $fieldType = FormFieldType::tryFrom((string) ($field['field_type'] ?? ''));

            if ($fieldKey === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
                return ['error' => "کلید فیلد شمارهٔ {$fieldNumber} («{$fieldKey}») نامعتبر است - باید فقط شامل حروف انگلیسی کوچک، عدد و زیرخط باشد و با یک حرف شروع شود. هیچ فرمی ساخته نشد."];
            }

            if (isset($seenKeys[$fieldKey])) {
                return ['error' => "کلید فیلد «{$fieldKey}» بیش از یک‌بار استفاده شده است. هیچ فرمی ساخته نشد."];
            }

            $seenKeys[$fieldKey] = true;

            if ($label === '' || ! $fieldType) {
                return ['error' => "فیلد شمارهٔ {$fieldNumber} برچسب یا نوع معتبر ندارد. هیچ فرمی ساخته نشد."];
            }

            $workflowStepId = null;

            if (filled($field['workflow_step_name'] ?? null)) {
                $stepName = (string) $field['workflow_step_name'];

                if (! $stepIdsByName->has($stepName)) {
                    return [
                        'error' => "مرحلهٔ «{$stepName}» در گردش‌کار انتخابی پیدا نشد. هیچ فرمی ساخته نشد.",
                        'available_steps' => $stepIdsByName->keys()->all(),
                    ];
                }

                $workflowStepId = $stepIdsByName[$stepName];
            }

            $options = null;

            if (in_array($fieldType, [FormFieldType::Select, FormFieldType::Radio], true)) {
                $options = is_array($field['options'] ?? null) && count($field['options']) > 0
                    ? $field['options']
                    : null;

                if (! $options) {
                    return ['error' => "فیلد «{$label}» از نوع لیست کشویی/انتخاب تکی است ولی هیچ گزینه‌ای برایش مشخص نشده. هیچ فرمی ساخته نشد."];
                }
            }

            $resolvedFields[] = [
                'field_key' => $fieldKey,
                'label' => $label,
                'field_type' => $fieldType,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'options' => $options,
                'workflow_step_id' => $workflowStepId,
                'sort_order' => $index,
            ];
        }

        $template = DB::transaction(function () use ($title, $arguments, $workflow, $department, $resolvedFields) {
            $template = FormTemplate::create([
                'title' => $title,
                'description' => filled($arguments['description'] ?? null) ? $arguments['description'] : null,
                'department_id' => $department?->id,
                'workflow_definition_id' => $workflow?->id,
                // Always a draft - see designWorkflowDraftTool()'s note.
                'is_active' => false,
                'created_by' => Auth::id(),
            ]);

            foreach ($resolvedFields as $field) {
                $template->fields()->create($field);
            }

            return $template;
        });

        return [
            'success' => true,
            'form_template_id' => $template->id,
            'title' => $template->title,
            'fields_count' => count($resolvedFields),
            'department' => $department?->name,
            'workflow' => $workflow?->name,
            'message' => "قالب فرم «{$template->title}» با ".count($resolvedFields).' فیلد به‌صورت پیش‌نویس (غیرفعال) ساخته شد. تا وقتی یک مدیر آن را از بخش «فرم‌ساز و گردش‌کار ← قالب‌های فرم» بررسی، در صورت نیاز ویرایش و فعال نکند، هیچ کارمندی نمی‌تواند آن را ثبت کند.',
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
