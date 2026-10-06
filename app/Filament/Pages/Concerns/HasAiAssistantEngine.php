<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\AiMessageRole;
use App\Enums\ApproverType;
use App\Enums\AttendanceStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Enums\LoyaltyTier;
use App\Enums\ProjectStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowInstanceStatus;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Department;
use App\Models\Document;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormTemplate;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\GuestTicket;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectPhase;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SalesChannel;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;

/**
 * The AI assistant's entire conversation/tool engine - every property and
 * method here is shared verbatim between the full-page AiAssistant
 * (app/Filament/Pages/AiAssistant.php) and the panel-wide floating
 * AiAssistantWidget (app/Livewire/AiAssistantWidget.php), so the two
 * surfaces can never drift: a tool added once is available, identically
 * permission-gated, from both. Only Filament-Page-specific concerns
 * (navigation, $view, canAccess()) stay outside this trait.
 */
trait HasAiAssistantEngine
{
    /**
     * Common Persian filler words with no search signal of their own -
     * stripped from a search_everything query before word-count capping,
     * so a conversational sentence's actual name/keyword (which in Persian
     * often lands near the END of the sentence, e.g. "... چی در مورد سعید
     * ترابیان") never gets silently discarded just for coming after a run
     * of filler words. See globalSearchTool().
     *
     * @var array<int, string>
     */
    private const SEARCH_STOPWORDS = [
        'و', 'یا', 'به', 'از', 'در', 'با', 'را', 'که', 'این', 'آن', 'تو', 'چی', 'چه',
        'مورد', 'برای', 'است', 'هست', 'رو', 'هم', 'یک', 'می', 'شود', 'بود', 'کن', 'کنید',
        'داری', 'دارم', 'دارد', 'چقدر', 'چند', 'کدام', 'چطور', 'آیا', 'بگو', 'لطفا', 'لطفاً',
        'بده', 'بگی', 'بگید', 'کجاست', 'چیه', 'چیست',
    ];

    public ?int $conversationId = null;

    public string $body = '';

    public bool $isThinking = false;

    /**
     * Transient within a single send() call only - never a Livewire-synced
     * property, since it only needs to survive from a tool's execution
     * earlier in runAgentLoop() to the final assistant message created
     * later in the very same request. See buildChartForTool().
     *
     * @var array<string, mixed>|null
     */
    private ?array $pendingChart = null;

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
        // Reset per send() - a chart from an earlier, unrelated question
        // must never leak onto a later turn that calls no chartable tool
        // at all (e.g. a plain "ممنون" reply).
        $this->pendingChart = null;

        for ($round = 0; $round < 5; $round++) {
            $assistantMessage = $provider->chatCompletion($messages, $tools);
            $toolCalls = $assistantMessage['tool_calls'] ?? null;

            $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $toolCalls,
                // Only a final answer (no further tool calls queued) ever
                // carries a chart - and only once every tool call it
                // depended on has actually run, which by this point in the
                // loop (tool execution happens strictly before the NEXT
                // round reaches here) is guaranteed.
                'chart' => $toolCalls ? null : $this->pendingChart,
            ]);

            $messages[] = $assistantMessage;

            if (! $toolCalls) {
                return;
            }

            foreach ($toolCalls as $toolCall) {
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                $result = $this->executeTool($toolCall['function']['name'], $arguments);
                $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE);

                // Last chartable tool this turn wins - keeps whatever was
                // already pending when this particular call's result isn't
                // itself chart-worthy (a detail lookup after a summary,
                // say), instead of blanking it back out.
                $chart = $this->buildChartForTool($toolCall['function']['name'], $result);

                if ($chart !== null) {
                    $this->pendingChart = $chart;
                }

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

            دربارهٔ جست‌وجوی سراسری (search_everything):
            - وقتی کاربر چیزی را با یک نام/عبارت کلی می‌خواهد (مثل نام یک مشتری، پروژه، قرارداد، یا هر کلیدواژه‌ای) بدون این‌که بگوید دقیقاً مال کدام بخش سیستم است، اول search_everything را با همان عبارت صدا بزن - هیچ‌وقت مستقیم حدس نزن کدوم ابزار تخصصی مناسبه و هیچ‌وقت فقط به این دلیل که یک ابزار تخصصی‌تر چیزی پیدا نکرد، بگو «پیدا نشد»؛ همیشه اول با این ابزار در کل سیستم بگرد.
            - این ابزار هم‌زمان وظایف، پروژه‌ها، معاملات، قراردادها، اسناد، تیکت‌های پشتیبانی، شرکت‌ها، و مقادیر واقعی ثبت‌شده داخل همهٔ فرم‌ها (نه فقط اسم فرم‌ها) را می‌گردد و نتایج را بر اساس شباهت به عبارت جست‌وجو مرتب می‌کند - نتیجهٔ اول لزوماً تطابق کامل نیست، بهترین تطابق نسبیه.
            - بعد از پیدا کردن نتیجهٔ مرتبط، اگه کاربر جزئیات بیشتری خواست، با ابزار تخصصی‌تر همون بخش (مثلاً summarize_submission_for_decision برای یک پاسخ فرم) عمیق‌تر برو - search_everything فقط برای پیدا کردن «کجاست» است، نه جایگزین ابزارهای تخصصی.

            دربارهٔ طراحی گردش‌کار و قالب فرم (design_workflow_draft و design_form_template_draft):
            - اگر فرم درخواستی نیاز به چرخهٔ تأیید چندمرحله‌ای دارد، اول با design_workflow_draft گردش‌کار را بساز و بعد workflow_definition_id برگشتی را به design_form_template_draft بده. اگر فرم ساده است و نیاز به تأیید ندارد، فقط design_form_template_draft کافی است.
            - هیچ‌وقت نام دپارتمان، کاربر یا نقش سازمانی را حدس نزن یا با نام مشابه/نزدیک جایگزین نکن؛ همیشه از لیستی که در همین پیام سیستم به تو داده شده استفاده کن. اگر چیزی که کاربر گفته دقیقاً در لیست نبود، اسم ندادنش را به کاربر بگو و از او بخواه دقیق‌تر مشخص کند یا نام‌های نزدیک موجود را پیشنهاد بده - هرگز آن را حدسی نساز.
            - هر گردش‌کار و فرمی که با این ابزارها ساخته می‌شود، همیشه و بدون استثنا به‌صورت «غیرفعال» (پیش‌نویس) ذخیره می‌شود و تا وقتی یک انسان آن را از پنل بررسی و فعال نکند، در هیچ ثبت واقعی‌ای استفاده نخواهد شد - این محدودیت را همیشه صریح به کاربر یادآوری کن تا غافلگیر نشود.
            - اگر design_form_template_draft بگوید فرم مشابهی از قبل وجود دارد (needs_confirmation)، فرم جدیدی نساز؛ اول به کاربر بگو فرم مشابه چیست و بپرس آیا با وجود این فرم جدید می‌خواهد، و فقط با تأیید صریح او دوباره با confirm_similar_duplicate=true تماس بگیر.
            - برای افزودن فیلد به فرم موجود از add_field_to_form_template استفاده کن (هرگز فیلد قبلی را حذف یا نوعش را عوض نمی‌کند)، و برای تغییر نام/مهلت پاسخ‌گویی یک مرحلهٔ موجود از update_workflow_step - این ابزار هرگز تأییدکنندهٔ یک مرحله را عوض نمی‌کند چون ممکن است تأییدهای در جریان را به فرد اشتباهی منتقل کند.
            - get_rejection_reasons فقط متن خام نظرات رد را برمی‌گرداند؛ دسته‌بندی و خلاصه‌سازی دلایل رد کار خودت است، نه ابزار.

            دربارهٔ آنالیز محتوای فرم‌های پرشده (مثلاً جدول‌های کارها/پیشرفت داخل یک صورتجلسه):
            - اگر کلید فنی فیلدی که کاربر با نامش (مثل «دپارتمان» یا «پیشرفت») به آن اشاره کرده را نمی‌دانی، اول get_form_template_fields را برای آن فرم صدا بزن تا کلیدهای فنی و - برای فیلدهای جدولی - ستون‌های واقعی آن را ببینی؛ هیچ‌وقت کلید فنی را حدس نزن.
            - برای آنالیز چند سابمیشن با هم (مثلاً «همهٔ صورتجلسه‌های واحد دیجیتال مارکتینگ رو آنالیز کن»)، از search_form_submissions با include_answers=true استفاده کن تا مقادیر همهٔ فیلدها - از جمله کل ردیف‌های فیلدهای جدولی - یک‌جا برایت بیاید؛ برای یک سابمیشن مشخص با شناسه از summarize_submission_for_decision استفاده کن.
            - دسته‌بندی و تحلیل کیفی ردیف‌های جدول (مثلاً «کدام کار تمام شده، کدام نیمه‌کاره، کدام شروع نشده») کار خودت است، نه ابزار - این تحلیل را بر اساس عنوان واقعی ستون‌هایی که get_form_template_fields/include_answers برگرداندند انجام بده، نه بر اساس فرض یا حدس دربارهٔ معنای یک ستون.

            دربارهٔ پیگیری فرم‌های عقب‌افتاده و گزارش عملکرد (مهم - این دقیقاً جایی است که مدیر می‌خواهد بداند با چه کسی باید پیگیری کند، نه فقط یک عدد):
            - وقتی مدیر می‌پرسد «کدام کارمند/مسئول فرمی را که باید تایید می‌کرد هنوز تایید نکرده»، «چه چیزی از مهلتش گذشته»، یا «با چه کسی باید پیگیری کنم»، هیچ‌وقت فقط به get_form_submissions_summary بسنده نکن - آن فقط یک عدد کلی (sla_breached_count) می‌دهد، بدون اسم. حتماً get_breached_sla_submissions را صدا بزن تا اسم دقیق مسئول (یا مسئولان) هر درخواست، فرم، و میزان تأخیرش را بگیری و در پاسخ بیاوری.
            - اگر سؤال دربارهٔ یک فرم تکرارشونده/روزانه است که خودِ کارمند باید آن را پر می‌کرد (نه اینکه منتظر تاییدِ کسی دیگر باشد؛ مثل چک‌لیست روزانه)، به‌جای get_breached_sla_submissions از get_daily_checklist_compliance استفاده کن - آن ابزار برای «پرنشدن» است، این یکی برای «تاییدنشدن».
            - برای یک گزارش عملکرد کامل و دقیق، می‌توانی get_breached_sla_submissions و get_daily_checklist_compliance را با get_approver_performance (نرخ رد/تایید و سرعت هر فرد در طول زمان) ترکیب کنی تا هم وضعیت فعلی (چه کسی الان عقب افتاده) و هم روند کلی (چه کسی به‌طور معمول کند/سریع عمل می‌کند) را یک‌جا بدهی.

            دربارهٔ گزارش‌های هتل‌داری و رزرو:
            - get_accommodation_summary فقط عدد همین امروز را می‌دهد. برای برنامه‌ریزی چند روز آینده («چند روز دیگه چقدر اتاق خالی داریم») از get_occupancy_forecast استفاده کن.
            - برای لیست دقیق ورودی/خروجی‌های چند روز آینده (اسم مهمان، اتاق، تعداد شب - نه فقط یک عدد) از get_upcoming_arrivals و get_upcoming_departures استفاده کن.
            - get_overdue_checkouts مهمانانی را نشان می‌دهد که باید خارج می‌شدند ولی هنوز چک‌اوت نشده‌اند؛ برای پیگیری فرانت‌دسک/نظافت استفاده کن.
            - وقتی کاربر اسم یک مهمان یا شمارهٔ یک رزرو را می‌گوید، از search_bookings استفاده کن، نه search_everything.
            - get_revenue_by_room_type، get_booking_source_breakdown و get_cancellation_summary همگی روی ماه جاری یا ماه قبل (شمسی) کار می‌کنند؛ پارامتر period را بر اساس سؤال کاربر («این ماه»/«ماه قبل») تنظیم کن.

            دربارهٔ گزارش‌های وظایف و پروژه‌ها:
            - وقتی مدیر می‌پرسد «کدام وظیفه/کارمند عقب افتاده» یا «با کی پیگیری کنم»، هیچ‌وقت فقط به get_task_summary بسنده نکن - آن فقط یک عدد کلی (overdue_count) می‌دهد. حتماً get_overdue_tasks را صدا بزن تا اسم دقیق هر وظیفه، مسئولش، و میزان تأخیرش را بگیری.
            - برای جست‌وجوی یک وظیفهٔ خاص یا فیلتر کردن (مثلاً «کارهای باز رضا» یا «وظایف فوری این هفته») از search_tasks استفاده کن.
            - برای مقایسهٔ بار کاری افراد («بار کاری کی سنگین‌تره») از get_tasks_by_assignee استفاده کن؛ برای نگاه از زاویهٔ ماتریس آیزنهاور (فوری/مهم) از get_task_priority_breakdown.
            - get_project_health_summary فقط درصد EVM همهٔ پروژه‌های فعال را یک‌جا می‌دهد. برای جزئیات کامل یک پروژهٔ مشخص (بودجه، هزینهٔ واقعی، مسیر بحرانی) از get_project_detail با نام همان پروژه استفاده کن.
            - وقتی سؤال دربارهٔ «چرا پروژه عقب افتاده» است، نه فقط «چقدر عقب افتاده»، از get_overdue_project_phases استفاده کن تا دقیقاً مشخص شود کدام فاز/بخش کار عقب است.
            - برای مقایسهٔ بودجه و هزینهٔ واقعی (نه درصد EVM انتزاعی) از get_project_budget_summary، و برای دیدن ریز هزینه‌های یک پروژهٔ مشخص از get_project_expenses استفاده کن.

            دربارهٔ گزارش‌های CRM و مشتریان:
            - وقتی کاربر اسم یک مشتری یا شمارهٔ موبایلش را می‌گوید، از get_guest_detail استفاده کن تا یک نمای کامل (سطح وفاداری، RFM، کیف پول، معاملات و تیکت‌های باز، آخرین بازخورد) بگیری؛ برای فیلتر/فهرست‌کردن چند مشتری (مثلاً «مشتریان VIP») از search_guests استفاده کن.
            - get_crm_summary فقط یک عدد کلی (sla_breached_tickets) برای تیکت‌های عقب‌افتاده می‌دهد. وقتی کاربر می‌پرسد «کدام تیکت از مهلتش گذشته» یا «با کدام مشتری باید پیگیری کنم»، حتماً get_breached_support_tickets را صدا بزن تا موضوع تیکت، اسم مشتری و میزان تأخیر را بگیری.
            - برای عملکرد فروش هر کارشناس (پایپ‌لاین باز، نرخ موفقیت) از get_deals_by_assignee، و برای اینکه مشتری‌ها از کجا می‌آیند (حضوری/آنلاین/معرفی و...) از get_deal_source_breakdown استفاده کن.
            - برای فهرست مشتریان برتر/پرارزش (برای تماس قدردانی یا پیشنهاد ویژه) از get_top_guests_by_value استفاده کن.
            - برای رضایت مشتری (NPS) از get_guest_satisfaction_summary، و برای وضعیت کیف پول/کش‌بک مشتریان از get_wallet_activity_summary استفاده کن.
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
        $actionTools = [
            'create_task', 'design_workflow_draft', 'design_form_template_draft',
            'add_field_to_form_template', 'update_workflow_step',
        ];

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
            'add_field_to_form_template' => 'form-templates.update',
            'update_workflow_step' => 'workflow-definitions.update',
            default => null,
        };

        if ($requiredResourcePermission && ! Auth::user()?->can($requiredResourcePermission)) {
            return ['error' => 'شما اجازهٔ ایجاد این نوع رکورد را در سیستم ندارید.'];
        }

        return match ($name) {
            'search_everything' => $this->globalSearchTool($arguments),
            'get_accommodation_summary' => $this->accommodationSummary(),
            'get_occupancy_forecast' => $this->occupancyForecastTool($arguments),
            'search_bookings' => $this->searchBookingsTool($arguments),
            'get_upcoming_arrivals' => $this->upcomingArrivalsTool($arguments),
            'get_upcoming_departures' => $this->upcomingDeparturesTool($arguments),
            'get_overdue_checkouts' => $this->overdueCheckoutsTool(),
            'get_revenue_by_room_type' => $this->revenueByRoomTypeTool($arguments),
            'get_booking_source_breakdown' => $this->bookingSourceBreakdownTool($arguments),
            'get_cancellation_summary' => $this->cancellationSummaryTool($arguments),
            'get_crm_summary' => $this->crmSummary(),
            'get_guest_detail' => $this->guestDetailTool($arguments),
            'search_guests' => $this->searchGuestsTool($arguments),
            'get_breached_support_tickets' => $this->breachedSupportTicketsTool($arguments),
            'get_deals_by_assignee' => $this->dealsByAssigneeTool($arguments),
            'get_deal_source_breakdown' => $this->dealSourceBreakdownTool($arguments),
            'get_top_guests_by_value' => $this->topGuestsByValueTool($arguments),
            'get_guest_satisfaction_summary' => $this->guestSatisfactionSummaryTool($arguments),
            'get_wallet_activity_summary' => $this->walletActivitySummaryTool($arguments),
            'get_project_health_summary' => ['projects' => $this->projectHealthSummary()],
            'get_project_detail' => $this->projectDetailTool($arguments),
            'get_overdue_project_phases' => $this->overdueProjectPhasesTool($arguments),
            'get_project_budget_summary' => ['projects' => $this->projectBudgetSummaryTool()],
            'get_project_expenses' => $this->projectExpensesTool($arguments),
            'get_attendance_summary' => $this->attendanceSummary(),
            'get_task_summary' => $this->taskSummary(),
            'search_tasks' => $this->searchTasksTool($arguments),
            'get_overdue_tasks' => $this->overdueTasksTool($arguments),
            'get_tasks_by_assignee' => $this->tasksByAssigneeTool($arguments),
            'get_task_priority_breakdown' => $this->taskPriorityBreakdownTool(),
            'get_channel_manager_summary' => ['channels' => $this->channelManagerSummary()],
            'list_alerts' => ['alerts' => static::computeAlerts()],
            'get_form_submissions_summary' => $this->formSubmissionsSummary(),
            'get_daily_checklist_compliance' => $this->dailyChecklistCompliance($arguments),
            'create_task' => $this->createTaskTool($arguments),
            'design_workflow_draft' => $this->designWorkflowDraftTool($arguments),
            'design_form_template_draft' => $this->designFormTemplateDraftTool($arguments),
            'get_workflow_bottleneck_analysis' => $this->workflowBottleneckAnalysisTool($arguments),
            'get_approver_performance' => $this->approverPerformanceTool($arguments),
            'get_rejection_reasons' => $this->rejectionReasonsTool($arguments),
            'compare_form_submissions_between_periods' => $this->comparePeriodsTool($arguments),
            'get_form_template_fields' => $this->formTemplateFieldsTool($arguments),
            'search_form_submissions' => $this->searchFormSubmissionsTool($arguments),
            'summarize_submission_for_decision' => $this->summarizeSubmissionTool($arguments),
            'get_at_risk_sla_submissions' => $this->atRiskSlaSubmissionsTool(),
            'get_breached_sla_submissions' => $this->breachedSlaSubmissionsTool($arguments),
            'get_workflow_health_check' => $this->workflowHealthCheckTool(),
            'add_field_to_form_template' => $this->addFieldToFormTemplateTool($arguments),
            'update_workflow_step' => $this->updateWorkflowStepTool($arguments),
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
            $tools[] = $this->toolSchema('search_everything', 'Free-text search across the ENTIRE system at once - tasks, projects, CRM deals, contracts, documents, support tickets, companies, AND every answered value inside every submitted form (not just form titles). Use this FIRST whenever the user names something loosely (a client/company name, a project/task title, a keyword) without saying which module it is in, or after a more specific tool already came back empty - never just say "not found" without trying this first. Results are ranked by how many of the query\'s words they match, most relevant first. Each result names which module it came from and its id, so you can fetch full detail with a more specific tool afterwards if needed (e.g. summarize_submission_for_decision for a form-submission result).', [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Free-text search phrase - a name, title, keyword, or short phrase. Not a technical id or field key.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results to return, across all modules combined - defaults to 15, max 30'],
                ],
                'required' => ['query'],
            ]);
            $tools[] = $this->toolSchema('get_accommodation_summary', 'Get current room occupancy, today\'s check-ins/outs, and this month\'s accommodation revenue.', $noParams);
            $tools[] = $this->toolSchema('get_occupancy_forecast', 'Get a day-by-day room occupancy forecast for the upcoming days: occupied/available room counts, occupancy rate percent, and arrival/departure counts per day - use for planning questions like "چند روز آینده چقدر اتاق خالی داریم".', [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'description' => 'Number of upcoming days to forecast, starting today - defaults to 7, max 30'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('search_bookings', 'Search/list bookings by guest name, reference code, status, or check-in date range - use to find a specific booking or list recent ones, e.g. "رزرو آقای/خانم فلانی رو پیدا کن" or "لیست آخرین رزروها رو بده".', [
                'type' => 'object',
                'properties' => [
                    'guest_name' => ['type' => 'string', 'description' => 'Partial or full guest name'],
                    'reference_code' => ['type' => 'string', 'description' => 'Partial or full booking reference code, e.g. GNJ-BKG-000123'],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled']],
                    'check_in_from' => ['type' => 'string', 'description' => 'Earliest check-in date, format YYYY-MM-DD (Gregorian)'],
                    'check_in_to' => ['type' => 'string', 'description' => 'Latest check-in date, format YYYY-MM-DD (Gregorian)'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results to return - defaults to 20, max 50'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_upcoming_arrivals', 'Get the detailed list of guests due to check in over the next N days - guest name, room, nights, party size - not just a count. Use for "امروز/این چند روز کیا میان".', [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'description' => 'How many days ahead to include, starting today - defaults to 1 (today only), max 14'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_upcoming_departures', 'Get the detailed list of currently in-house guests due to check out over the next N days - guest name, room, folio total - not just a count. Use for "امروز/این چند روز کیا میرن".', [
                'type' => 'object',
                'properties' => [
                    'days' => ['type' => 'integer', 'description' => 'How many days ahead to include, starting today - defaults to 1 (today only), max 14'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_overdue_checkouts', 'Get in-house guests whose scheduled check-out date has already passed but who have not actually been checked out yet - use for "کدام مهمان از تاریخ خروجش گذشته" / front-desk follow-up.', $noParams);
            $tools[] = $this->toolSchema('get_revenue_by_room_type', 'Get revenue, nights sold, ADR (average daily rate), and occupancy rate broken down by room type, for this or last Jalali month - use for "کدام نوع اتاق بیشترین درآمد را داشته".', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_booking_source_breakdown', 'Get checked-out booking count, revenue, and commission owed broken down by booking source (walk-in, phone, website, OTA), for this or last Jalali month - use for "چند درصد رزروها از سایت واسط میاد" یا مقایسهٔ حضوری و آنلاین.', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_cancellation_summary', 'Get how many bookings made this or last Jalali month ended up cancelled, and the cancellation rate - use for "نرخ کنسلی رزروها چقدره".', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_crm_summary', 'Get the sales pipeline (deals grouped by stage with counts and values) and support ticket / SLA status.', $noParams);
            $tools[] = $this->toolSchema('get_guest_detail', 'Get a full 360 view of ONE specific customer by name or mobile number: contact info, loyalty tier, RFM (recency/frequency/lifetime value), wallet balance, open deals, open support tickets, and latest feedback - use when the user names a specific customer, e.g. "مشتری رضا کریمی چه وضعیتی داره".', [
                'type' => 'object',
                'properties' => [
                    'guest_name' => ['type' => 'string', 'description' => 'Partial or full customer name, or their mobile number'],
                ],
                'required' => ['guest_name'],
            ]);
            $tools[] = $this->toolSchema('search_guests', 'Search/list customers by name, mobile, loyalty tier, or minimum completed-stay count - use to find a specific customer or list a segment, e.g. "مشتریان VIP رو نشون بده".', [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Partial or full customer name'],
                    'mobile' => ['type' => 'string', 'description' => 'Partial or full mobile number'],
                    'loyalty_tier' => ['type' => 'string', 'enum' => ['bronze', 'silver', 'gold', 'vip']],
                    'min_completed_stays' => ['type' => 'integer', 'description' => 'Only customers with at least this many completed stays'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results - defaults to 20, max 50'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_breached_support_tickets', 'Get support tickets that have ALREADY breached their SLA deadline - subject, customer name, priority, hours overdue - not just the count get_crm_summary gives. Optionally scope to one customer.', [
                'type' => 'object',
                'properties' => [
                    'guest_name' => ['type' => 'string', 'description' => 'Optional - partial or full customer name to scope to; omit to list every breached ticket'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_deals_by_assignee', 'Get per-sales-rep pipeline stats: open deal count, open pipeline value, won count, and win rate - grouped by the assigned user. Optionally scope to one person. Use for "عملکرد فروش کی بهتره".', [
                'type' => 'object',
                'properties' => [
                    'user_name' => ['type' => 'string', 'description' => 'Optional - partial or full name of one sales rep to scope to; omit to list every assignee'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_deal_source_breakdown', 'Get deal count, total value, and win rate broken down by lead source (walk-in, phone, website, referral, social media, other), for this or last Jalali month - use for "بیشتر مشتری‌ها از کجا میان".', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_top_guests_by_value', 'Get the top customers ranked by lifetime value (completed-stay spend), with their loyalty tier and stay count - use for "مشتریان برتر/VIP رو نشون بده" or targeted outreach lists.', [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'How many top customers to return - defaults to 10, max 50'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_guest_satisfaction_summary', 'Get the NPS (Net Promoter Score) breakdown from submitted guest feedback - promoter/passive/detractor counts, average score, and the NPS score itself - for this or last Jalali month. Use for "رضایت مشتری‌ها چطوره".', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_wallet_activity_summary', 'Get total wallet credit/debit activity (cashback and spend) across all customers for this or last Jalali month, plus the customers with the highest current wallet balances.', [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month'], 'description' => 'Defaults to this_month'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_project_health_summary', 'Get earned-value (EVM) health for every active project: earned/planned value percent, cost variance, schedule variance.', $noParams);
            $tools[] = $this->toolSchema('get_project_detail', 'Get full detail on ONE specific project by name: status, budget vs actual cost, EV/PV/CV/SV, and its critical-path phase titles in order - use when the user names a specific project, e.g. "پروژه وب‌سایت جدید چطوره".', [
                'type' => 'object',
                'properties' => [
                    'project_name' => ['type' => 'string', 'description' => 'Partial or full project title or code'],
                ],
                'required' => ['project_name'],
            ]);
            $tools[] = $this->toolSchema('get_overdue_project_phases', 'Get the specific WBS phases that are overdue (past their planned end date with progress under 100%) across active projects - project name, phase title, planned end date, days overdue - not just a project-level percentage. Optionally scope to one project by name.', [
                'type' => 'object',
                'properties' => [
                    'project_name' => ['type' => 'string', 'description' => 'Optional - partial or full project title to scope to; omit to check every active project'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_project_budget_summary', 'Get plain budget vs actual-spend numbers (not EVM percentages) for every active project: budget, amount spent, amount remaining, percent spent - use for "کدام پروژه داره از بودجه رد میشه".', $noParams);
            $tools[] = $this->toolSchema('get_project_expenses', 'Get the recent expense line items (title, amount, date, phase) for ONE specific project - use for "پروژه فلان رو چی خرج کرده" / drilling into what a project\'s spend actually went to.', [
                'type' => 'object',
                'properties' => [
                    'project_name' => ['type' => 'string', 'description' => 'Partial or full project title or code'],
                    'limit' => ['type' => 'integer', 'description' => 'Max expenses to return, most recent first - defaults to 20, max 50'],
                ],
                'required' => ['project_name'],
            ]);
            $tools[] = $this->toolSchema('get_attendance_summary', 'Get this calendar month\'s staff attendance: on-time/late/absent counts and the users with the most lateness.', $noParams);
            $tools[] = $this->toolSchema('get_task_summary', 'Get the overdue task count and a breakdown of all tasks by status.', $noParams);
            $tools[] = $this->toolSchema('search_tasks', 'Search/list tasks by assignee name, status, priority, department, or due date range - use to find specific tasks or list a filtered set, e.g. "کارهای باز رضا رو نشون بده".', [
                'type' => 'object',
                'properties' => [
                    'assignee_name' => ['type' => 'string', 'description' => 'Partial or full name of the assigned user'],
                    'status' => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'done', 'overdue', 'cancelled']],
                    'priority' => ['type' => 'string', 'enum' => ['urgent_important', 'not_urgent_important', 'urgent_not_important', 'neither'], 'description' => 'Eisenhower-matrix quadrant'],
                    'department_name' => ['type' => 'string', 'description' => 'Partial or full department name'],
                    'due_before' => ['type' => 'string', 'description' => 'Only tasks due on/before this date, format YYYY-MM-DD (Gregorian)'],
                    'due_after' => ['type' => 'string', 'description' => 'Only tasks due on/after this date, format YYYY-MM-DD (Gregorian)'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results - defaults to 20, max 50'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_overdue_tasks', 'Get the detailed list of overdue tasks - title, assignee name, due date, days overdue - not just a count. Optionally scope to one assignee. Use for "کدام کارمند وظیفه‌اش عقب افتاده" / "با کی باید پیگیری کنم".', [
                'type' => 'object',
                'properties' => [
                    'assignee_name' => ['type' => 'string', 'description' => 'Optional - partial or full name of one assignee to scope to; omit to list every overdue task'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results - defaults to 30, max 100'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_tasks_by_assignee', 'Get per-person task workload: open (todo/in_progress) count, overdue count, and tasks completed this Jalali month - grouped by assignee. Optionally scope to one person. Use for "بار کاری کی سنگین‌تره" / workload balancing.', [
                'type' => 'object',
                'properties' => [
                    'user_name' => ['type' => 'string', 'description' => 'Optional - partial or full name of one user to scope to; omit to list every assignee'],
                ],
                'required' => [],
            ]);
            $tools[] = $this->toolSchema('get_task_priority_breakdown', 'Get task counts and overdue counts grouped by Eisenhower-matrix priority quadrant (urgent+important, not urgent+important, urgent+not important, neither) - use for "چقدر از کارهای فوری و مهم عقب افتاده".', $noParams);
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

            $tools[] = $this->toolSchema('get_workflow_bottleneck_analysis', 'Analyze which approval steps across form workflows take the longest to get acted on (approved/rejected/etc), on average, ranked worst first - use this for questions like "which step is the biggest bottleneck in our approval process".', [
                'type' => 'object',
                'properties' => [
                    'workflow_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one workflow to scope the analysis to; omit to analyze every workflow'],
                    'limit' => ['type' => 'integer', 'description' => 'Max number of steps to return, worst first - defaults to 5'],
                ],
                'required' => [],
            ]);

            $tools[] = $this->toolSchema('get_approver_performance', 'Per-approver stats across form workflow actions: how many they approved/rejected, their rejection rate compared to the system average, and their average response time - use this for questions about who is fast/slow, or who rejects unusually often or rarely (rubber-stamping).', [
                'type' => 'object',
                'properties' => [
                    'user_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one approver to scope to; omit to list every approver'],
                ],
                'required' => [],
            ]);

            $tools[] = $this->toolSchema('get_rejection_reasons', 'Get the raw list of recent rejection comments across form submissions (optionally scoped to one form), so you can read and summarize/categorize the actual reasons yourself in your answer - e.g. "what are the most common reasons contracts get rejected".', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one form to scope to'],
                    'limit' => ['type' => 'integer', 'description' => 'Max number of rejection comments to return - defaults to 30, max 100'],
                ],
                'required' => [],
            ]);

            $tools[] = $this->toolSchema('compare_form_submissions_between_periods', 'Compare submitted/approved/rejected/pending counts for this Jalali month vs last Jalali month, optionally scoped to one form - use for questions like "how does this month compare to last month".', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one form to scope to; omit to compare across every form'],
                ],
                'required' => [],
            ]);

            $tools[] = $this->toolSchema('get_form_template_fields', 'Get a form template\'s field list - each field\'s label, technical key, type, and (for table-type fields, i.e. a repeating multi-column row like a task/progress table) its column definitions. Call this BEFORE search_form_submissions when you don\'t already know a field\'s exact technical key - e.g. to find which field on a specific form holds "department", "progress", or any other value the user refers to by its Persian label rather than its key.', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Exact or partial name of the form template'],
                ],
                'required' => ['form_template_name'],
            ]);

            $tools[] = $this->toolSchema('search_form_submissions', 'Search past submissions of one form by a specific field\'s value - e.g. "show purchase requests over 500 million toman" or "this department\'s weekly meeting minutes". Use get_form_template_fields first if you don\'t already know the field\'s technical key. Returns matching submissions (id, submitter, date, status) by default; set include_answers to also get every answered field for each one (table-type fields included, as their full array of rows) - use that for analyzing/comparing several submissions at once instead of calling summarize_submission_for_decision on each id separately.', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Exact or partial name of the form to search within'],
                    'field_key' => ['type' => 'string', 'description' => 'Optional - technical key of the field to filter on (omit to just filter by status/date)'],
                    'operator' => ['type' => 'string', 'enum' => ['==', '!=', '>', '>=', '<', '<=', 'contains'], 'description' => 'Comparison operator for field_key\'s value - defaults to =='],
                    'value' => ['type' => 'string', 'description' => 'Value to compare field_key against - a plain number for numeric fields, or Y-m-d Gregorian for date fields'],
                    'status' => ['type' => 'string', 'enum' => ['pending_approval', 'approved', 'rejected'], 'description' => 'Optional status filter'],
                    'include_answers' => ['type' => 'boolean', 'description' => 'When true, includes every answered field (including table-type fields\' full row arrays) for each matched submission, for analyzing several submissions together. Defaults to false. Lowers the max limit to 15 (large payload per submission).'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results - defaults to 20, max 100 (max 15 when include_answers is true)'],
                ],
                'required' => ['form_template_name'],
            ]);

            $tools[] = $this->toolSchema('summarize_submission_for_decision', 'Get full detail on one specific form submission (every answered field) plus, for its numeric fields, how it compares to the average/min/max of past decided submissions of the same form - use this to help the manager decide on a specific pending request by its id.', [
                'type' => 'object',
                'properties' => [
                    'submission_id' => ['type' => 'integer', 'description' => 'The form submission\'s id'],
                ],
                'required' => ['submission_id'],
            ]);

            $tools[] = $this->toolSchema('get_at_risk_sla_submissions', 'Get in-progress approvals that have NOT yet breached their current step\'s SLA but are close to it (70%+ of the allotted time elapsed) - a preventive warning before list_alerts/get_form_submissions_summary would flag them as already breached.', $noParams);

            $tools[] = $this->toolSchema('get_breached_sla_submissions', 'Get in-progress approvals that have ALREADY breached their current step\'s SLA deadline, each with WHO is responsible for acting on it right now (the current approver(s) by name), the form, the submitter, the department, and how many hours overdue. Use this - never just get_form_submissions_summary\'s plain sla_breached_count - for questions like "which employee/manager hasn\'t approved something they were supposed to" or "who do I need to follow up with". For a recurring/daily self-submission form (not an approval bottleneck), use get_daily_checklist_compliance instead.', [
                'type' => 'object',
                'properties' => [
                    'form_template_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one form to scope to; omit to check every form'],
                    'approver_name' => ['type' => 'string', 'description' => 'Optional - name (or partial name) of one responsible approver to scope to; omit to check everyone'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results, most overdue first - defaults to 20, max 50'],
                ],
                'required' => [],
            ]);

            $tools[] = $this->toolSchema('get_workflow_health_check', 'Find structural misconfigurations in active form approval workflows: a step whose specific approver user no longer exists, a role-based step whose role has no members, an active workflow with zero steps, or a form needing "department manager" approval whose department has no manager set.', $noParams);
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
                        'confirm_similar_duplicate' => ['type' => 'boolean', 'description' => 'If a form with a very similar title already exists, the tool refuses and lists it instead of creating a duplicate. Only pass true here, after telling the user about the similar form and getting their explicit confirmation that they still want a new one.'],
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

            if ($user->can('form-templates.update')) {
                $tools[] = $this->toolSchema('add_field_to_form_template', 'Add ONE new field to an EXISTING form template (active or draft) - use when the manager asks to add a field to a form that already exists. Never deletes or changes any existing field; only appends a new one, so it never loses past submissions\' data.', [
                    'type' => 'object',
                    'properties' => [
                        'form_template_name' => ['type' => 'string', 'description' => 'Exact or partial name of the existing form to add the field to'],
                        'field' => [
                            'type' => 'object',
                            'properties' => [
                                'field_key' => ['type' => 'string', 'description' => 'English snake_case technical key, unique within the form'],
                                'label' => ['type' => 'string', 'description' => 'Persian display label'],
                                'field_type' => ['type' => 'string', 'enum' => ['text', 'textarea', 'number', 'date_jalali', 'select', 'radio', 'checkbox', 'file', 'signature', 'gps']],
                                'is_required' => ['type' => 'boolean'],
                                'options' => ['type' => 'object', 'description' => 'Required only for select/radio fields - a map of stored value to Persian label'],
                                'workflow_step_name' => ['type' => 'string', 'description' => 'Optional - exact name of a step in this form\'s own linked workflow, to scope this field to that step instead of initial submission'],
                            ],
                            'required' => ['field_key', 'label', 'field_type'],
                        ],
                    ],
                    'required' => ['form_template_name', 'field'],
                ]);
            }

            if ($user->can('workflow-definitions.update')) {
                $tools[] = $this->toolSchema('update_workflow_step', 'Update an EXISTING workflow step\'s name and/or SLA deadline only - use when the manager asks to rename a step or change its response deadline. Never changes who approves at that step (approver_type/identity), since that could silently redirect approvals already pending there.', [
                    'type' => 'object',
                    'properties' => [
                        'workflow_name' => ['type' => 'string', 'description' => 'Exact or partial name of the existing workflow'],
                        'step_name' => ['type' => 'string', 'description' => 'Exact or partial current name of the step to update'],
                        'new_name' => ['type' => 'string', 'description' => 'Optional new name for the step'],
                        'sla_hours' => ['type' => 'integer', 'description' => 'Optional new SLA deadline in hours - pass 0 or omit entirely to leave it unchanged, there is no way to clear an existing SLA through this tool'],
                    ],
                    'required' => ['workflow_name', 'step_name'],
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

    // --- Charts (rendered under the final assistant message - see
    // resources/views/filament/pages/ai-assistant.blade.php) -------------------

    /**
     * Not every reporting tool's result is chart-worthy - forcing one onto
     * a detail-oriented result (one submission, raw search hits, a list of
     * missed calendar days) would be noise, not insight. This only returns
     * a spec for the tools whose result is genuinely aggregate/breakdown-
     * shaped, and - per the actual request that prompted this ("با توجه به
     * کاربرد هرکدام") - picks the chart type that fits THAT tool's own data
     * shape rather than reusing one default everywhere: doughnut for a
     * small proportion-of-a-whole (attendance, room status), bar for a
     * ranked/categorical comparison (everything else here).
     *
     * @param  array<string, mixed>  $result
     * @return array{type: string, title: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float|int>}>}|null
     */
    private function buildChartForTool(string $name, array $result): ?array
    {
        return match ($name) {
            'get_attendance_summary' => $this->chartFromCounts('doughnut', 'وضعیت حضور این ماه', [
                'به‌موقع' => $result['on_time_count'] ?? 0,
                'تأخیر' => $result['late_count'] ?? 0,
                'غیبت' => $result['absent_count'] ?? 0,
            ]),
            'get_accommodation_summary' => $this->chartFromCounts('doughnut', 'وضعیت اتاق‌ها', [
                'اشغال' => $result['occupied'] ?? 0,
                'خالی' => $result['available'] ?? 0,
                'نیاز به نظافت' => $result['needs_cleaning'] ?? 0,
            ]),
            'get_occupancy_forecast' => $this->chartFromRows('bar', 'نرخ اشغال پیش‌بینی‌شده به تفکیک روز (٪)', $result['forecast'] ?? [], 'jalali_date', 'occupancy_rate_percent'),
            'get_revenue_by_room_type' => $this->chartFromRows('bar', 'درآمد به تفکیک نوع اتاق (تومان)', $result['room_types'] ?? [], 'room_type', 'revenue_toman'),
            'get_booking_source_breakdown' => $this->chartFromRows('bar', 'درآمد به تفکیک منبع رزرو (تومان)', $result['sources'] ?? [], 'source', 'revenue_toman'),
            'get_task_summary' => $this->chartFromCounts('bar', 'وظایف بر اساس وضعیت', $result['by_status'] ?? []),
            'get_tasks_by_assignee' => $this->chartFromRows('bar', 'وظایف باز به تفکیک مسئول', $result['assignees'] ?? [], 'user', 'open_count'),
            'get_task_priority_breakdown' => $this->chartFromRows('bar', 'وظایف بر اساس اولویت (ماتریس آیزنهاور)', $result['priorities'] ?? [], 'priority', 'count'),
            'get_project_budget_summary' => $this->chartFromRows('bar', 'درصد بودجهٔ مصرف‌شده به تفکیک پروژه (٪)', $result['projects'] ?? [], 'title', 'percent_spent'),
            'get_form_submissions_summary' => $this->chartFromCounts('bar', 'درخواست‌های در انتظار تایید بر اساس فرم', $result['pending_by_form'] ?? []),
            'get_crm_summary' => $this->chartFromRows('bar', 'ارزش معاملات بر اساس مرحله (تومان)', $result['deals_by_stage'] ?? [], 'stage', 'total_value_toman'),
            'get_breached_support_tickets' => $this->chartFromRows('bar', 'میزان تاخیر تیکت‌ها از مهلت SLA (ساعت)', $result['tickets'] ?? [], 'subject', 'overdue_hours'),
            'get_deals_by_assignee' => $this->chartFromRows('bar', 'ارزش پایپ‌لاین باز به تفکیک کارشناس فروش (تومان)', $result['assignees'] ?? [], 'user', 'open_pipeline_value_toman'),
            'get_deal_source_breakdown' => $this->chartFromRows('bar', 'ارزش معاملات به تفکیک منبع (تومان)', $result['sources'] ?? [], 'source', 'value_toman'),
            'get_top_guests_by_value' => $this->chartFromRows('bar', 'ارزش طول عمر مشتری (تومان)', $result['guests'] ?? [], 'name', 'lifetime_value_toman'),
            'get_guest_satisfaction_summary' => $this->chartFromCounts('doughnut', 'بازخورد مشتریان (NPS)', [
                'حامی' => $result['promoters'] ?? 0,
                'خنثی' => $result['passives'] ?? 0,
                'منتقد' => $result['detractors'] ?? 0,
            ]),
            'get_wallet_activity_summary' => $this->chartFromCounts('bar', 'واریز و برداشت کیف پول (تومان)', [
                'واریز' => $result['credited_toman'] ?? 0,
                'برداشت' => $result['debited_toman'] ?? 0,
            ]),
            'get_channel_manager_summary' => $this->chartFromRows('bar', 'کمیسیون بدهکار بر اساس کانال فروش (تومان)', $result['channels'] ?? [], 'name', 'commission_owed_toman'),
            'get_approver_performance' => $this->chartFromRows('bar', 'نرخ رد درخواست به تفکیک تأییدکننده (٪)', $result['approvers'] ?? [], 'user', 'rejection_rate_percent'),
            'get_breached_sla_submissions' => $this->chartFromRows('bar', 'میزان تاخیر از مهلت تایید (ساعت)', $result['items'] ?? [], 'form', 'overdue_hours'),
            'get_project_health_summary' => $this->chartFromRows('bar', 'درصد پیشرفت واقعی پروژه‌ها', $result['projects'] ?? [], 'title', 'earned_value_percent'),
            'get_workflow_bottleneck_analysis' => $this->chartFromRows('bar', 'میانگین زمان پاسخ به تفکیک مرحله (ساعت)', $result['bottlenecks'] ?? [], 'workflow_and_step', 'average_hours'),
            default => null,
        };
    }

    /**
     * A doughnut/bar spec straight from a flat label => value map (an
     * already-grouped breakdown like by_status or pending_by_form) - every
     * non-numeric entry (e.g. a 'message' key on an otherwise-empty
     * result) is dropped, and an all-zero or empty map returns null rather
     * than a technically-valid but meaningless blank chart.
     *
     * @param  array<string, mixed>  $counts
     */
    private function chartFromCounts(string $type, string $title, array $counts): ?array
    {
        $numeric = array_filter($counts, 'is_numeric');

        if ($numeric === [] || array_sum($numeric) <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'title' => $title,
            'labels' => array_keys($numeric),
            'datasets' => [['label' => $title, 'data' => array_values($numeric)]],
        ];
    }

    /**
     * The same spec shape, but built from a list of row arrays (one bar
     * per row) instead of a flat map - e.g. get_breached_sla_submissions'
     * "items", each needing its $labelKey ('form') and $valueKey
     * ('overdue_hours') picked out. Capped at 10 bars: beyond that a chart
     * stops being readable, and the model's own text/table answer already
     * covers the long tail.
     *
     * @param  array<int, mixed>  $rows
     */
    private function chartFromRows(string $type, string $title, array $rows, string $labelKey, string $valueKey): ?array
    {
        $filtered = collect($rows)
            ->filter(fn ($row) => is_array($row) && isset($row[$labelKey]) && is_numeric($row[$valueKey] ?? null))
            ->take(10);

        if ($filtered->isEmpty()) {
            return null;
        }

        return [
            'type' => $type,
            'title' => $title,
            'labels' => $filtered->pluck($labelKey)->map(fn ($value) => (string) $value)->all(),
            'datasets' => [['label' => $title, 'data' => $filtered->pluck($valueKey)->map(fn ($value) => (float) $value)->all()]],
        ];
    }

    // --- Tool implementations -------------------------------------------------

    /**
     * A free-text, cross-module search - the one tool that doesn't require
     * already knowing which module/table something lives in. Every other
     * tool in this class is scoped to one module (accommodation, CRM,
     * forms, ...); a user naming a client, project, or keyword loosely
     * (e.g. "قالب سایت رابیان") has no way to know which of those to reach
     * for, and used to just get "not found" from whichever single tool the
     * model guessed. This searches tasks, projects, deals, contracts,
     * documents, support tickets, companies, and - critically - every
     * answered value inside every submitted form (not just form/field
     * names) all at once, and ranks results by how many of the query's
     * words they contain so the closest match surfaces first even when the
     * user's wording isn't an exact substring of any single record.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function globalSearchTool(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['error' => 'عبارت جست‌وجو خالی است.'];
        }

        // Common filler words carry no search signal but, in a natural
        // Persian sentence, often come BEFORE the actual name/keyword
        // ("تو کمیسیون‌های هفتگی چی در مورد سعید ترابیان") - stripping them
        // first (then capping) is what keeps a plain word-position cap from
        // silently discarding the one distinctive word at the end of a
        // longer, conversational query. Falls back to the raw word list if
        // stripping happens to remove everything (e.g. the query WAS just
        // filler), since searching something beats erroring out.
        $allWords = collect(preg_split('/\s+/u', $query))->filter()->unique()->values();
        $meaningfulWords = $allWords->reject(fn (string $word) => in_array(mb_strtolower($word), self::SEARCH_STOPWORDS, true))->values();
        $words = ($meaningfulWords->isEmpty() ? $allWords : $meaningfulWords)->take(8);

        if ($words->isEmpty()) {
            return ['error' => 'عبارت جست‌وجو خالی است.'];
        }

        // How many of the query's words appear inside a candidate's own
        // searchable text - the ranking signal, since a real match rarely
        // contains the user's exact phrase verbatim but does share most of
        // its distinct words.
        $score = fn (string $haystack): int => $words
            ->filter(fn (string $word) => mb_stripos($haystack, $word) !== false)
            ->count();

        $likeAnyWord = function (Builder $query, array $columns) use ($words) {
            $query->where(function (Builder $q) use ($columns, $words) {
                foreach ($columns as $column) {
                    foreach ($words as $word) {
                        $q->orWhere($column, 'like', "%{$word}%");
                    }
                }
            });
        };

        $results = collect();

        Task::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['title', 'description']))
            ->limit(30)->get()
            ->each(function (Task $task) use (&$results, $score) {
                $results->push([
                    'type' => 'وظیفه',
                    'id' => $task->id,
                    'title' => $task->title,
                    'snippet' => Str::limit((string) $task->description, 150),
                    'status' => $task->status?->getLabel(),
                    'score' => $score($task->title.' '.$task->description),
                ]);
            });

        Project::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['title', 'description']))
            ->limit(30)->get()
            ->each(function (Project $project) use (&$results, $score) {
                $results->push([
                    'type' => 'پروژه',
                    'id' => $project->id,
                    'title' => $project->title,
                    'snippet' => Str::limit((string) $project->description, 150),
                    'status' => $project->status?->getLabel(),
                    'score' => $score($project->title.' '.$project->description),
                ]);
            });

        Deal::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['title', 'notes']))
            ->limit(30)->get()
            ->each(function (Deal $deal) use (&$results, $score) {
                $results->push([
                    'type' => 'معامله (CRM)',
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'snippet' => Str::limit((string) $deal->notes, 150),
                    'status' => $deal->stage?->getLabel(),
                    'score' => $score($deal->title.' '.$deal->notes),
                ]);
            });

        Contract::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['title', 'counterparty_name', 'brief']))
            ->limit(30)->get()
            ->each(function (Contract $contract) use (&$results, $score) {
                $results->push([
                    'type' => 'قرارداد',
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'snippet' => Str::limit($contract->counterparty_name.' - '.$contract->brief, 150),
                    'score' => $score($contract->title.' '.$contract->counterparty_name.' '.$contract->brief),
                ]);
            });

        Document::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['title']))
            ->limit(30)->get()
            ->each(function (Document $document) use (&$results, $score) {
                $results->push([
                    'type' => 'سند',
                    'id' => $document->id,
                    'title' => $document->title,
                    'score' => $score($document->title),
                ]);
            });

        GuestTicket::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['subject', 'description']))
            ->limit(30)->get()
            ->each(function (GuestTicket $ticket) use (&$results, $score) {
                $results->push([
                    'type' => 'تیکت پشتیبانی',
                    'id' => $ticket->id,
                    'title' => $ticket->subject,
                    'snippet' => Str::limit((string) $ticket->description, 150),
                    'status' => $ticket->status?->getLabel(),
                    'score' => $score($ticket->subject.' '.$ticket->description),
                ]);
            });

        Company::query()
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['name']))
            ->limit(30)->get()
            ->each(function (Company $company) use (&$results, $score) {
                $results->push([
                    'type' => 'شرکت',
                    'id' => $company->id,
                    'title' => $company->name,
                    'score' => $score($company->name),
                ]);
            });

        // The one place a plain "form title" search would miss the point:
        // the user usually means something someone TYPED INTO a form, not
        // the form's own name - e.g. "قالب سایت رابیان" is almost certainly
        // a value inside some field, not a form template called that.
        FormSubmissionValue::query()
            ->whereNotNull('value_text')
            ->tap(fn (Builder $q) => $likeAnyWord($q, ['value_text']))
            ->with(['formField' => fn ($q) => $q->withTrashed(), 'formSubmission.formTemplate'])
            ->limit(30)->get()
            ->each(function (FormSubmissionValue $value) use (&$results, $score) {
                $results->push([
                    'type' => 'پاسخ ثبت‌شده در فرم',
                    'form' => $value->formSubmission?->formTemplate?->title,
                    'submission_id' => $value->form_submission_id,
                    'field' => $value->formField?->label,
                    'snippet' => Str::limit((string) $value->value_text, 150),
                    'score' => $score((string) $value->value_text),
                ]);
            });

        // A Table field's whole row array lives in value_json, not
        // value_text - a name that only ever appears inside a table cell
        // (e.g. a client's name in a weekly-commission table's own
        // "توضیحات" column) would otherwise never be found at all, which
        // was the exact real-world case that surfaced this gap.
        //
        // This CANNOT be pre-filtered with a SQL LIKE the way value_text
        // is above: Eloquent's array cast json_encode()s without
        // JSON_UNESCAPED_UNICODE, so Persian (or any non-ASCII) text is
        // stored as escaped sequences like "سعید" in
        // the raw column - a LIKE '%سعید%' against that literal column
        // text can never match, even though the value is really "سعید"
        // once decoded. Loading Table-field values directly and filtering
        // in PHP (where the array cast has already decoded them back to
        // real text) sidesteps that entirely - fine at this app's scale
        // (a form-builder's table rows are a small slice of all answers).
        FormSubmissionValue::query()
            ->whereNotNull('value_json')
            ->whereHas('formField', fn (Builder $q) => $q->withTrashed()->where('field_type', FormFieldType::Table))
            ->with(['formField' => fn ($q) => $q->withTrashed(), 'formSubmission.formTemplate'])
            ->latest('id')
            ->limit(300)
            ->get()
            ->each(function (FormSubmissionValue $value) use (&$results, $score) {
                if (! is_array($value->value_json)) {
                    return;
                }

                $flattened = collect($value->value_json)
                    ->map(fn ($row) => is_array($row) ? implode(' ', $row) : (string) $row)
                    ->implode(' | ');

                $rowScore = $score($flattened);

                if ($flattened === '' || $rowScore === 0) {
                    return;
                }

                $results->push([
                    'type' => 'ردیف جدول در یک فرم ثبت‌شده',
                    'form' => $value->formSubmission?->formTemplate?->title,
                    'submission_id' => $value->form_submission_id,
                    'field' => $value->formField?->label,
                    'snippet' => Str::limit($flattened, 150),
                    'score' => $rowScore,
                ]);
            });

        $limit = max(1, min(30, (int) ($arguments['limit'] ?? 15)));

        $matched = $results
            ->filter(fn (array $result) => $result['score'] > 0)
            ->sortByDesc('score')
            ->values();

        return [
            'query' => $query,
            'matched_count' => $matched->count(),
            'results' => $matched->take($limit)->all(),
        ];
    }

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

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function occupancyForecastTool(array $arguments): array
    {
        $days = max(1, min(30, (int) ($arguments['days'] ?? 7)));
        $totalRooms = Room::where('is_active', true)->count();

        $forecast = [];

        for ($i = 0; $i < $days; $i++) {
            $date = today()->addDays($i);

            // A room can't have two overlapping active bookings
            // (Booking::assertRoomAvailable enforces this), so counting
            // distinct room_id here is just defensive, not load-bearing.
            $occupied = Booking::query()
                ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
                ->where('check_in_date', '<=', $date)
                ->where('check_out_date', '>', $date)
                ->pluck('room_id')
                ->unique()
                ->count();

            $arrivals = Booking::query()
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                ->whereDate('check_in_date', $date)
                ->count();

            $departures = Booking::query()
                ->where('status', BookingStatus::CheckedIn)
                ->whereDate('check_out_date', $date)
                ->count();

            $forecast[] = [
                'date' => $date->toDateString(),
                'jalali_date' => Jalalian::fromCarbon($date)->format('%d %B'),
                'occupied' => $occupied,
                'available' => max(0, $totalRooms - $occupied),
                'occupancy_rate_percent' => $totalRooms > 0 ? round($occupied / $totalRooms * 100, 1) : 0,
                'arrivals' => $arrivals,
                'departures' => $departures,
            ];
        }

        return ['total_active_rooms' => $totalRooms, 'forecast' => $forecast];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function searchBookingsTool(array $arguments): array
    {
        $guestName = trim((string) ($arguments['guest_name'] ?? ''));
        $referenceCode = trim((string) ($arguments['reference_code'] ?? ''));
        $status = BookingStatus::tryFrom((string) ($arguments['status'] ?? ''));
        $checkInFrom = $arguments['check_in_from'] ?? null;
        $checkInTo = $arguments['check_in_to'] ?? null;
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));

        $query = Booking::query()->with(['guest', 'room.roomType', 'channel']);

        if ($guestName !== '') {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$guestName}%"));
        }

        if ($referenceCode !== '') {
            $query->where('reference_code', 'like', "%{$referenceCode}%");
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($checkInFrom) {
            $query->whereDate('check_in_date', '>=', $checkInFrom);
        }

        if ($checkInTo) {
            $query->whereDate('check_in_date', '<=', $checkInTo);
        }

        $bookings = $query->orderByDesc('check_in_date')->limit($limit)->get();

        if ($bookings->isEmpty()) {
            return ['message' => 'هیچ رزروی با این مشخصات پیدا نشد.'];
        }

        return [
            'count' => $bookings->count(),
            'bookings' => $bookings->map(fn (Booking $booking) => [
                'reference_code' => $booking->reference_code,
                'guest_name' => $booking->guest?->name,
                'room' => $booking->room?->name,
                'room_type' => $booking->room?->roomType?->name,
                'check_in_date' => $booking->check_in_date->toDateString(),
                'check_out_date' => $booking->check_out_date->toDateString(),
                'nights' => $booking->nights(),
                'status' => $booking->status->getLabel(),
                'source' => $booking->source->getLabel(),
                'channel' => $booking->channel?->name,
                'total_amount_toman' => $booking->totalAmount(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function upcomingArrivalsTool(array $arguments): array
    {
        $days = max(1, min(14, (int) ($arguments['days'] ?? 1)));
        $from = today();
        $to = today()->addDays($days - 1);

        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereBetween('check_in_date', [$from, $to])
            ->with(['guest', 'room.roomType'])
            ->orderBy('check_in_date')
            ->get();

        if ($bookings->isEmpty()) {
            return ['message' => 'در این بازه هیچ ورود (چک-این) برنامه‌ریزی‌شده‌ای وجود ندارد.'];
        }

        return [
            'arrivals_count' => $bookings->count(),
            'arrivals' => $bookings->map(fn (Booking $booking) => [
                'reference_code' => $booking->reference_code,
                'guest_name' => $booking->guest?->name,
                'guest_mobile' => $booking->guest?->mobile,
                'room' => $booking->room?->name,
                'room_type' => $booking->room?->roomType?->name,
                'check_in_date' => $booking->check_in_date->toDateString(),
                'jalali_check_in_date' => Jalalian::fromCarbon($booking->check_in_date)->format('%d %B'),
                'nights' => $booking->nights(),
                'adults' => $booking->adults_count,
                'children' => $booking->children_count,
                'status' => $booking->status->getLabel(),
                'source' => $booking->source->getLabel(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function upcomingDeparturesTool(array $arguments): array
    {
        $days = max(1, min(14, (int) ($arguments['days'] ?? 1)));
        $from = today();
        $to = today()->addDays($days - 1);

        $bookings = Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->whereBetween('check_out_date', [$from, $to])
            ->with(['guest', 'room.roomType', 'charges'])
            ->orderBy('check_out_date')
            ->get();

        if ($bookings->isEmpty()) {
            return ['message' => 'در این بازه هیچ خروج (چک-اوت) برنامه‌ریزی‌شده‌ای وجود ندارد.'];
        }

        return [
            'departures_count' => $bookings->count(),
            'departures' => $bookings->map(fn (Booking $booking) => [
                'reference_code' => $booking->reference_code,
                'guest_name' => $booking->guest?->name,
                'guest_mobile' => $booking->guest?->mobile,
                'room' => $booking->room?->name,
                'room_type' => $booking->room?->roomType?->name,
                'check_out_date' => $booking->check_out_date->toDateString(),
                'jalali_check_out_date' => Jalalian::fromCarbon($booking->check_out_date)->format('%d %B'),
                'nights' => $booking->nights(),
                'total_amount_toman' => $booking->totalAmount(),
            ])->all(),
        ];
    }

    private function overdueCheckoutsTool(): array
    {
        $bookings = Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->where('check_out_date', '<', today())
            ->with(['guest', 'room'])
            ->orderBy('check_out_date')
            ->get();

        if ($bookings->isEmpty()) {
            return ['message' => 'هیچ مهمان مقیمی از تاریخ خروج برنامه‌ریزی‌شده‌اش عقب نیفتاده است.'];
        }

        return [
            'overdue_count' => $bookings->count(),
            'overdue_checkouts' => $bookings->map(fn (Booking $booking) => [
                'reference_code' => $booking->reference_code,
                'guest_name' => $booking->guest?->name,
                'guest_mobile' => $booking->guest?->mobile,
                'room' => $booking->room?->name,
                'scheduled_check_out_date' => $booking->check_out_date->toDateString(),
                // Carbon 3's diffInDays() defaults to a signed result (today
                // is later than a past check-out date), unlike Carbon 2 -
                // abs() keeps this a plain "how many days late" count.
                'days_overdue' => (int) abs(today()->diffInDays($booking->check_out_date)),
            ])->all(),
        ];
    }

    /**
     * Resolves a "this_month"/"last_month" period keyword to a [start, end]
     * Carbon range anchored to the Jalali calendar - the business's actual
     * accounting month, not the Gregorian one - shared by the hospitality
     * reporting tools below.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveJalaliPeriod(string $period): array
    {
        $thisMonth = Jalalian::now();

        if ($period === 'last_month') {
            $lastMonth = $thisMonth->subMonths(1);

            return [$lastMonth->getFirstDayOfMonth()->toCarbon()->startOfDay(), $lastMonth->getEndDayOfMonth()->toCarbon()->endOfDay()];
        }

        return [$thisMonth->getFirstDayOfMonth()->toCarbon()->startOfDay(), $thisMonth->getEndDayOfMonth()->toCarbon()->endOfDay()->min(now())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function revenueByRoomTypeTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);
        $periodDays = max(1, $start->diffInDays($end) + 1);

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->get()
            ->map(function (RoomType $roomType) use ($start, $end, $periodDays) {
                $roomCount = $roomType->rooms()->where('is_active', true)->count();

                $bookings = Booking::query()
                    ->whereHas('room', fn ($q) => $q->where('room_type_id', $roomType->id))
                    ->where('status', BookingStatus::CheckedOut)
                    ->whereBetween('actual_check_out_at', [$start, $end])
                    ->with('charges')
                    ->get();

                $nightsSold = $bookings->sum(fn (Booking $booking) => $booking->nights());
                $revenue = (int) $bookings->sum(fn (Booking $booking) => $booking->totalAmount());
                $availableRoomNights = $roomCount * $periodDays;

                return [
                    'room_type' => $roomType->name,
                    'bookings_checked_out' => $bookings->count(),
                    'nights_sold' => $nightsSold,
                    'revenue_toman' => $revenue,
                    'adr_toman' => $nightsSold > 0 ? (int) round($revenue / $nightsSold) : 0,
                    'occupancy_rate_percent' => $availableRoomNights > 0 ? round($nightsSold / $availableRoomNights * 100, 1) : 0,
                ];
            })
            ->sortByDesc('revenue_toman')
            ->values();

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'room_types' => $roomTypes->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function bookingSourceBreakdownTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);

        $sources = collect(BookingSource::cases())
            ->map(function (BookingSource $source) use ($start, $end) {
                $bookings = Booking::query()
                    ->where('source', $source)
                    ->where('status', BookingStatus::CheckedOut)
                    ->whereBetween('actual_check_out_at', [$start, $end])
                    ->with('charges')
                    ->get();

                return [
                    'source' => $source->getLabel(),
                    'bookings' => $bookings->count(),
                    'revenue_toman' => (int) $bookings->sum(fn (Booking $booking) => $booking->totalAmount()),
                    'commission_owed_toman' => (int) $bookings->sum('commission_amount'),
                ];
            })
            ->filter(fn (array $row) => $row['bookings'] > 0)
            ->values();

        if ($sources->isEmpty()) {
            return ['message' => 'در این بازه هیچ رزرو تسویه‌شده‌ای ثبت نشده است.'];
        }

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'sources' => $sources->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function cancellationSummaryTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);

        // Defined as one cohort - of the bookings MADE in this period, how
        // many ended up cancelled (as of now) - rather than mixing
        // "created in period" against "cancelled in period" counts from two
        // different cohorts, which would make the rate meaningless.
        $createdInPeriod = Booking::query()->whereBetween('created_at', [$start, $end])->get();
        $cancelledOfThose = $createdInPeriod->where('status', BookingStatus::Cancelled)->count();

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'bookings_created' => $createdInPeriod->count(),
            'cancelled_count' => $cancelledOfThose,
            'cancellation_rate_percent' => $createdInPeriod->count() > 0
                ? round($cancelledOfThose / $createdInPeriod->count() * 100, 1)
                : 0,
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

    private function findGuestByName(string $name): ?Guest
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Guest::query()->where('name', $name)->first()
            ?? Guest::query()->where('mobile', $name)->first()
            ?? Guest::query()->where('name', 'like', "%{$name}%")->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function guestDetailTool(array $arguments): array
    {
        $name = trim((string) ($arguments['guest_name'] ?? ''));
        $guest = $this->findGuestByName($name);

        if (! $guest) {
            return ['error' => "مشتری‌ای با نام یا شمارهٔ «{$name}» پیدا نشد."];
        }

        $openDeals = $guest->deals()->whereNotIn('stage', [DealStage::Won, DealStage::Lost])->count();
        $openTickets = $guest->tickets()->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->count();
        $latestFeedback = $guest->feedbacks()->whereNotNull('submitted_at')->latest('submitted_at')->first();

        return [
            'name' => $guest->name,
            'mobile' => $guest->mobile,
            'email' => $guest->email,
            'loyalty_tier' => $guest->loyaltyTier()->getLabel(),
            'wallet_balance_toman' => (int) $guest->wallet_balance,
            'completed_stays' => $guest->frequency(),
            'lifetime_value_toman' => $guest->monetaryTotal(),
            'days_since_last_stay' => $guest->recencyDays(),
            'open_deals' => $openDeals,
            'open_support_tickets' => $openTickets,
            'latest_feedback_score' => $latestFeedback?->nps_score,
            'latest_feedback_comment' => $latestFeedback?->comment,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function searchGuestsTool(array $arguments): array
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        $mobile = trim((string) ($arguments['mobile'] ?? ''));
        $loyaltyTier = LoyaltyTier::tryFrom((string) ($arguments['loyalty_tier'] ?? ''));
        $minStays = max(0, (int) ($arguments['min_completed_stays'] ?? 0));
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));

        $query = Guest::query();

        if ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($mobile !== '') {
            $query->where('mobile', 'like', "%{$mobile}%");
        }

        $guests = $query->get()
            ->filter(fn (Guest $guest) => ! $loyaltyTier || $guest->loyaltyTier() === $loyaltyTier)
            ->filter(fn (Guest $guest) => $minStays === 0 || $guest->frequency() >= $minStays)
            ->take($limit);

        if ($guests->isEmpty()) {
            return ['message' => 'هیچ مشتری‌ای با این مشخصات پیدا نشد.'];
        }

        return [
            'count' => $guests->count(),
            'guests' => $guests->map(fn (Guest $guest) => [
                'name' => $guest->name,
                'mobile' => $guest->mobile,
                'loyalty_tier' => $guest->loyaltyTier()->getLabel(),
                'completed_stays' => $guest->frequency(),
                'wallet_balance_toman' => (int) $guest->wallet_balance,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function breachedSupportTicketsTool(array $arguments): array
    {
        $guestName = trim((string) ($arguments['guest_name'] ?? ''));

        $query = GuestTicket::query()
            ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->with('guest');

        if ($guestName !== '') {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$guestName}%"));
        }

        $tickets = $query->orderBy('sla_due_at')->get();

        if ($tickets->isEmpty()) {
            return ['message' => 'هیچ تیکت پشتیبانی‌ای از مهلت SLA خودش نگذشته است.'];
        }

        return [
            'breached_count' => $tickets->count(),
            'tickets' => $tickets->map(fn (GuestTicket $ticket) => [
                'subject' => $ticket->subject,
                'guest' => $ticket->guest?->name,
                'priority' => $ticket->priority->getLabel(),
                'status' => $ticket->status->getLabel(),
                'overdue_hours' => (int) round($ticket->sla_due_at->diffInHours(now())),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dealsByAssigneeTool(array $arguments): array
    {
        $userName = trim((string) ($arguments['user_name'] ?? ''));

        $query = Deal::query()->whereNotNull('assigned_to')->with('assignee');

        if ($userName !== '') {
            $query->whereHas('assignee', fn ($q) => $q->where('name', 'like', "%{$userName}%"));
        }

        $deals = $query->get();

        if ($deals->isEmpty()) {
            return ['message' => 'هیچ معامله‌ای با مسئول مشخص پیدا نشد.'];
        }

        $assignees = $deals->groupBy(fn (Deal $deal) => $deal->assignee?->name)
            ->map(function (Collection $group, string $name) {
                $open = $group->whereNotIn('stage', [DealStage::Won, DealStage::Lost]);
                $won = $group->where('stage', DealStage::Won);
                $decided = $group->whereIn('stage', [DealStage::Won, DealStage::Lost]);

                return [
                    'user' => $name,
                    'open_deals' => $open->count(),
                    'open_pipeline_value_toman' => (int) $open->sum('value'),
                    'won_count' => $won->count(),
                    'win_rate_percent' => $decided->count() > 0 ? round($won->count() / $decided->count() * 100, 1) : 0,
                ];
            })
            ->sortByDesc('open_pipeline_value_toman')
            ->values();

        return ['assignees' => $assignees->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dealSourceBreakdownTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);

        $sources = collect(DealSource::cases())
            ->map(function (DealSource $source) use ($start, $end) {
                $deals = Deal::query()
                    ->where('source', $source)
                    ->whereBetween('created_at', [$start, $end])
                    ->get();

                $decided = $deals->whereIn('stage', [DealStage::Won, DealStage::Lost]);
                $won = $deals->where('stage', DealStage::Won);

                return [
                    'source' => $source->getLabel(),
                    'deals' => $deals->count(),
                    'value_toman' => (int) $deals->sum('value'),
                    'win_rate_percent' => $decided->count() > 0 ? round($won->count() / $decided->count() * 100, 1) : 0,
                ];
            })
            ->filter(fn (array $row) => $row['deals'] > 0)
            ->values();

        if ($sources->isEmpty()) {
            return ['message' => 'در این بازه هیچ معامله‌ای ثبت نشده است.'];
        }

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'sources' => $sources->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function topGuestsByValueTool(array $arguments): array
    {
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 10)));

        $guests = Guest::query()->get()
            ->map(fn (Guest $guest) => [
                'name' => $guest->name,
                'mobile' => $guest->mobile,
                'loyalty_tier' => $guest->loyaltyTier()->getLabel(),
                'completed_stays' => $guest->frequency(),
                'lifetime_value_toman' => $guest->monetaryTotal(),
            ])
            ->filter(fn (array $row) => $row['lifetime_value_toman'] > 0)
            ->sortByDesc('lifetime_value_toman')
            ->take($limit)
            ->values();

        if ($guests->isEmpty()) {
            return ['message' => 'هنوز هیچ مشتری‌ای اقامت تسویه‌شده‌ای ندارد.'];
        }

        return ['guests' => $guests->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function guestSatisfactionSummaryTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);

        $feedback = GuestFeedback::query()
            ->whereNotNull('submitted_at')
            ->whereNotNull('nps_score')
            ->whereBetween('submitted_at', [$start, $end])
            ->get();

        if ($feedback->isEmpty()) {
            return ['message' => 'در این بازه هیچ بازخورد امتیازدهی‌شده‌ای ثبت نشده است.'];
        }

        $promoters = $feedback->filter(fn (GuestFeedback $item) => $item->npsCategory() === 'promoter')->count();
        $passives = $feedback->filter(fn (GuestFeedback $item) => $item->npsCategory() === 'passive')->count();
        $detractors = $feedback->filter(fn (GuestFeedback $item) => $item->npsCategory() === 'detractor')->count();

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'responses' => $feedback->count(),
            'average_score' => round((float) $feedback->avg('nps_score'), 1),
            'promoters' => $promoters,
            'passives' => $passives,
            'detractors' => $detractors,
            // Standard NPS formula: %promoters - %detractors, on a -100..100 scale.
            'nps_score' => round(($promoters - $detractors) / $feedback->count() * 100, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function walletActivitySummaryTool(array $arguments): array
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        [$start, $end] = $this->resolveJalaliPeriod($period);

        $transactions = WalletTransaction::query()->whereBetween('created_at', [$start, $end])->get();

        if ($transactions->isEmpty()) {
            return ['message' => 'در این بازه هیچ تراکنش کیف پولی ثبت نشده است.'];
        }

        $credited = (int) $transactions->where('type', WalletTransactionType::Credit)->sum('amount');
        $debited = (int) $transactions->where('type', WalletTransactionType::Debit)->sum('amount');

        $topBalances = Guest::query()
            ->where('wallet_balance', '>', 0)
            ->orderByDesc('wallet_balance')
            ->limit(5)
            ->get()
            ->map(fn (Guest $guest) => ['guest' => $guest->name, 'balance_toman' => (int) $guest->wallet_balance]);

        return [
            'period' => $period === 'last_month' ? 'ماه قبل' : 'این ماه',
            'credited_toman' => $credited,
            'debited_toman' => $debited,
            'net_toman' => $credited - $debited,
            'top_balances' => $topBalances->all(),
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

    private function findProjectByName(string $name): ?Project
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return Project::query()->where('title', $name)->first()
            ?? Project::query()->where('code', $name)->first()
            ?? Project::query()->where('title', 'like', "%{$name}%")->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function projectDetailTool(array $arguments): array
    {
        $name = trim((string) ($arguments['project_name'] ?? ''));
        $project = $this->findProjectByName($name);

        if (! $project) {
            return [
                'error' => "پروژه‌ای با نام «{$name}» پیدا نشد.",
                'available_projects' => Project::query()->orderBy('title')->pluck('title')->all(),
            ];
        }

        $criticalPathTitles = ProjectPhase::query()->whereIn('id', $project->criticalPathPhaseIds())->pluck('title', 'id');

        return [
            'title' => $project->title,
            'code' => $project->code,
            'status' => $project->status->getLabel(),
            'owner' => $project->owner?->name,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'budget_toman' => (int) $project->budget,
            'actual_cost_toman' => (int) $project->actualCost(),
            'earned_value_percent' => round($project->earnedValuePercent(), 1),
            'planned_value_percent' => round($project->plannedValuePercent(), 1),
            'cost_variance_toman' => (int) $project->costVariance(),
            'schedule_variance_toman' => (int) $project->scheduleVariance(),
            // In critical-path order (earliest to latest), not DB order.
            'critical_path_phases' => collect($project->criticalPathPhaseIds())
                ->map(fn (int $id) => $criticalPathTitles[$id] ?? null)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function overdueProjectPhasesTool(array $arguments): array
    {
        $projectName = trim((string) ($arguments['project_name'] ?? ''));
        $projectsQuery = Project::query()->whereIn('status', [ProjectStatus::Planning, ProjectStatus::InProgress]);

        if ($projectName !== '') {
            $project = $this->findProjectByName($projectName);

            if (! $project) {
                return [
                    'error' => "پروژه‌ای با نام «{$projectName}» پیدا نشد.",
                    'available_projects' => Project::query()->orderBy('title')->pluck('title')->all(),
                ];
            }

            $projectsQuery->whereKey($project->id);
        }

        $overduePhases = $projectsQuery->get()
            ->flatMap(fn (Project $project) => $project->leafPhases()->get()
                ->filter(fn (ProjectPhase $phase) => $phase->isOverdue())
                ->map(fn (ProjectPhase $phase) => [
                    'project' => $project->title,
                    'phase' => $phase->title,
                    'planned_end_date' => $phase->end_date->toDateString(),
                    'progress_percent' => (float) $phase->progress_percent,
                    'days_overdue' => (int) abs(today()->diffInDays($phase->end_date)),
                ]))
            ->sortByDesc('days_overdue')
            ->values();

        if ($overduePhases->isEmpty()) {
            return ['message' => 'هیچ فاز عقب‌افتاده‌ای در پروژه‌های فعال پیدا نشد.'];
        }

        return ['overdue_count' => $overduePhases->count(), 'phases' => $overduePhases->all()];
    }

    private function projectBudgetSummaryTool(): array
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Planning, ProjectStatus::InProgress])
            ->get()
            ->map(function (Project $project) {
                $budget = (int) $project->budget;
                $spent = (int) $project->actualCost();

                return [
                    'title' => $project->title,
                    'budget_toman' => $budget,
                    'spent_toman' => $spent,
                    'remaining_toman' => $budget - $spent,
                    'percent_spent' => $budget > 0 ? round($spent / $budget * 100, 1) : 0,
                ];
            })
            ->sortByDesc('percent_spent')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function projectExpensesTool(array $arguments): array
    {
        $name = trim((string) ($arguments['project_name'] ?? ''));
        $project = $this->findProjectByName($name);

        if (! $project) {
            return [
                'error' => "پروژه‌ای با نام «{$name}» پیدا نشد.",
                'available_projects' => Project::query()->orderBy('title')->pluck('title')->all(),
            ];
        }

        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));

        $expenses = $project->expenses()
            ->with('phase')
            ->orderByDesc('spent_at')
            ->limit($limit)
            ->get();

        if ($expenses->isEmpty()) {
            return ['message' => "هیچ هزینه‌ای برای پروژهٔ «{$project->title}» ثبت نشده است."];
        }

        return [
            'project' => $project->title,
            'total_spent_toman' => (int) $project->actualCost(),
            'expenses' => $expenses->map(fn (ProjectExpense $expense) => [
                'title' => $expense->title,
                'amount_toman' => (int) $expense->amount,
                'spent_at' => $expense->spent_at?->toDateString(),
                'phase' => $expense->phase?->title,
            ])->all(),
        ];
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

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function searchTasksTool(array $arguments): array
    {
        $assigneeName = trim((string) ($arguments['assignee_name'] ?? ''));
        $status = TaskStatus::tryFrom((string) ($arguments['status'] ?? ''));
        $priority = TaskPriority::tryFrom((string) ($arguments['priority'] ?? ''));
        $departmentName = trim((string) ($arguments['department_name'] ?? ''));
        $dueBefore = $arguments['due_before'] ?? null;
        $dueAfter = $arguments['due_after'] ?? null;
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));

        $query = Task::query()->with(['assignee', 'department']);

        if ($assigneeName !== '') {
            $query->whereHas('assignee', fn ($q) => $q->where('name', 'like', "%{$assigneeName}%"));
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($departmentName !== '') {
            $query->whereHas('department', fn ($q) => $q->where('name', 'like', "%{$departmentName}%"));
        }

        if ($dueBefore) {
            $query->whereDate('due_at', '<=', $dueBefore);
        }

        if ($dueAfter) {
            $query->whereDate('due_at', '>=', $dueAfter);
        }

        $tasks = $query->orderByDesc('due_at')->limit($limit)->get();

        if ($tasks->isEmpty()) {
            return ['message' => 'هیچ وظیفه‌ای با این مشخصات پیدا نشد.'];
        }

        return [
            'count' => $tasks->count(),
            'tasks' => $tasks->map(fn (Task $task) => [
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'department' => $task->department?->name,
                'priority' => $task->priority->getLabel(),
                'status' => $task->status->getLabel(),
                'due_at' => $task->due_at?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function overdueTasksTool(array $arguments): array
    {
        $assigneeName = trim((string) ($arguments['assignee_name'] ?? ''));
        $limit = max(1, min(100, (int) ($arguments['limit'] ?? 30)));

        $query = Task::query()
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with(['assignee', 'department']);

        if ($assigneeName !== '') {
            $query->whereHas('assignee', fn ($q) => $q->where('name', 'like', "%{$assigneeName}%"));
        }

        $tasks = $query->orderBy('due_at')->limit($limit)->get();

        if ($tasks->isEmpty()) {
            return ['message' => 'هیچ وظیفهٔ عقب‌افتاده‌ای پیدا نشد.'];
        }

        return [
            'overdue_count' => $tasks->count(),
            'tasks' => $tasks->map(fn (Task $task) => [
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'department' => $task->department?->name,
                'priority' => $task->priority->getLabel(),
                'due_at' => $task->due_at->toDateString(),
                // due_at is a datetime (unlike a booking's date-only
                // check_out_date), so comparing it raw against today()
                // under-counts whenever due_at's time-of-day is later than
                // midnight - startOfDay() keeps this a whole-calendar-days count.
                'days_overdue' => (int) abs(today()->diffInDays($task->due_at->copy()->startOfDay())),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function tasksByAssigneeTool(array $arguments): array
    {
        $userName = trim((string) ($arguments['user_name'] ?? ''));

        $query = Task::query()->whereNotNull('assigned_to')->with('assignee');

        if ($userName !== '') {
            $query->whereHas('assignee', fn ($q) => $q->where('name', 'like', "%{$userName}%"));
        }

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            return ['message' => 'هیچ وظیفه‌ای با مسئول مشخص پیدا نشد.'];
        }

        $thisMonthStart = now()->startOfMonth();

        $assignees = $tasks->groupBy(fn (Task $task) => $task->assignee?->name)
            ->map(fn (Collection $group, string $name) => [
                'user' => $name,
                'open_count' => $group->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])->count(),
                'overdue_count' => $group->filter(fn (Task $task) => $task->due_at
                    && $task->due_at->isPast()
                    && ! in_array($task->status, [TaskStatus::Done, TaskStatus::Cancelled], true))->count(),
                'completed_this_month' => $group->where('status', TaskStatus::Done)
                    ->filter(fn (Task $task) => $task->completed_at && $task->completed_at->gte($thisMonthStart))
                    ->count(),
            ])
            ->sortByDesc('open_count')
            ->values();

        return ['assignees' => $assignees->all()];
    }

    private function taskPriorityBreakdownTool(): array
    {
        $byPriority = Task::query()
            ->selectRaw('priority, count(*) as task_count')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->priority->value => (int) $row->task_count]);

        $overdueByPriority = Task::query()
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->selectRaw('priority, count(*) as overdue_count')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->priority->value => (int) $row->overdue_count]);

        $priorities = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority) => [
                'priority' => $priority->getLabel(),
                'count' => (int) ($byPriority[$priority->value] ?? 0),
                'overdue_count' => (int) ($overdueByPriority[$priority->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        return ['priorities' => $priorities->all()];
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

        // Duplicate-form guard: refuse (rather than silently create a
        // near-duplicate) unless the caller explicitly confirms it wants a
        // new form anyway - the same "never guess, ask for confirmation"
        // principle as the department/user/role lookups below, just for
        // "does this already exist" instead of "does this exist at all".
        if (! ($arguments['confirm_similar_duplicate'] ?? false)) {
            $similarTitles = FormTemplate::query()
                ->get(['title'])
                ->filter(function (FormTemplate $existing) use ($title) {
                    similar_text(mb_strtolower($existing->title), mb_strtolower($title), $percent);

                    return $percent >= 70;
                })
                ->pluck('title');

            if ($similarTitles->isNotEmpty()) {
                return [
                    'needs_confirmation' => true,
                    'message' => 'فرم(های) بسیار مشابهی از قبل وجود دارد. اگر کاربر مطمئن است که با وجود این، فرم جدیدی می‌خواهد، همین ابزار را دوباره با confirm_similar_duplicate=true صدا بزن؛ در غیر این صورت پیشنهاد بده از فرم موجود استفاده شود.',
                    'similar_existing_forms' => $similarTitles->values()->all(),
                ];
            }
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

    // --- Form/workflow analytics tools -----------------------------------

    /**
     * Walks every past instance of (optionally, one) workflow and, for
     * each recorded action, computes how long the subject sat at that step
     * before it was acted on - the time since the instance started (for a
     * step's first action) or since the previous action (for any later
     * one). There is no dedicated "entered this step at" column, so this
     * is the same approximation get_form_submissions_summary's SLA-breach
     * check already relies on, just applied to every historical step
     * instead of only the current one.
     *
     * @return Collection<int, array{workflow_name: ?string, step_name: ?string, actor_name: ?string, action: WorkflowActionType, hours: float}>
     */
    private function workflowActionDurations(?string $workflowName = null): Collection
    {
        $instances = WorkflowInstance::query()
            ->where('subject_type', FormSubmission::class)
            ->when($workflowName, fn ($query) => $query->whereHas(
                'workflowDefinition',
                fn ($q) => $q->where('name', 'like', "%{$workflowName}%"),
            ))
            ->with(['workflowDefinition', 'actions.workflowStep', 'actions.actor'])
            ->get();

        $rows = new Collection;

        foreach ($instances as $instance) {
            $previousAt = $instance->started_at;

            foreach ($instance->actions as $action) {
                if ($previousAt) {
                    $rows->push([
                        'workflow_name' => $instance->workflowDefinition?->name,
                        'step_name' => $action->workflowStep?->name,
                        'actor_name' => $action->actor?->name,
                        'action' => $action->action,
                        'hours' => (float) $previousAt->diffInHours($action->acted_at),
                    ]);
                }

                $previousAt = $action->acted_at;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function workflowBottleneckAnalysisTool(array $arguments): array
    {
        $durations = $this->workflowActionDurations($arguments['workflow_name'] ?? null);

        if ($durations->isEmpty()) {
            return ['message' => 'داده‌ای برای تحلیل گلوگاه یافت نشد - هنوز هیچ اقدامی روی گردش‌کارها ثبت نشده است.'];
        }

        $limit = max(1, (int) ($arguments['limit'] ?? 5));

        $bySteps = $durations
            ->groupBy(fn (array $row) => ($row['workflow_name'] ?? '؟').' ← '.($row['step_name'] ?? '؟'))
            ->map(fn (Collection $rows, string $label) => [
                'workflow_and_step' => $label,
                'average_hours' => round($rows->avg('hours'), 1),
                'sample_count' => $rows->count(),
            ])
            ->sortByDesc('average_hours')
            ->take($limit)
            ->values();

        return ['bottlenecks' => $bySteps->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function approverPerformanceTool(array $arguments): array
    {
        $userNameFilter = trim((string) ($arguments['user_name'] ?? ''));
        $durations = $this->workflowActionDurations();

        $decisions = $durations->whereIn('action', [WorkflowActionType::Approve, WorkflowActionType::Reject]);
        $systemRejectionRate = $decisions->isNotEmpty()
            ? $decisions->where('action', WorkflowActionType::Reject)->count() / $decisions->count()
            : 0.0;

        $byActor = $decisions
            ->filter(fn (array $row) => filled($row['actor_name']))
            ->groupBy('actor_name')
            ->when($userNameFilter !== '', fn (Collection $groups) => $groups->filter(
                fn (Collection $rows, string $actorName) => str_contains($actorName, $userNameFilter),
            ))
            ->map(function (Collection $rows, string $actorName) {
                $approved = $rows->where('action', WorkflowActionType::Approve)->count();
                $rejected = $rows->where('action', WorkflowActionType::Reject)->count();

                return [
                    'user' => $actorName,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'rejection_rate_percent' => round($rejected / max(1, $approved + $rejected) * 100, 1),
                    'average_response_hours' => round($rows->avg('hours'), 1),
                ];
            })
            ->values();

        if ($byActor->isEmpty()) {
            return ['message' => $userNameFilter !== ''
                ? "تأییدکننده‌ای با نام مشابه «{$userNameFilter}» در تاریخچهٔ اقدامات پیدا نشد."
                : 'هنوز هیچ تأیید یا ردی در گردش‌کارها ثبت نشده است.'];
        }

        return [
            'system_average_rejection_rate_percent' => round($systemRejectionRate * 100, 1),
            'approvers' => $byActor->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function rejectionReasonsTool(array $arguments): array
    {
        $formName = trim((string) ($arguments['form_template_name'] ?? ''));
        $limit = max(1, min(100, (int) ($arguments['limit'] ?? 30)));

        $actions = WorkflowAction::query()
            ->where('action', WorkflowActionType::Reject)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->with(['actor', 'workflowStep', 'workflowInstance.subject'])
            ->latest('acted_at')
            // A generous ceiling to filter within, well above any
            // realistic $limit - keeps this a single query regardless of
            // the optional form-name filter below, which can't be
            // expressed through a plain whereHas() across a morphTo chain.
            ->limit(300)
            ->get()
            ->filter(fn (WorkflowAction $action) => $action->workflowInstance?->subject instanceof FormSubmission)
            ->when($formName !== '', fn (Collection $rows) => $rows->filter(
                fn (WorkflowAction $action) => str_contains(
                    $action->workflowInstance->subject->formTemplate?->title ?? '',
                    $formName,
                ),
            ))
            ->take($limit);

        if ($actions->isEmpty()) {
            return ['message' => 'هیچ ردی با نظر ثبت‌شده یافت نشد.'];
        }

        return [
            'count' => $actions->count(),
            'rejections' => $actions->map(fn (WorkflowAction $action) => [
                'form' => $action->workflowInstance->subject->formTemplate?->title,
                'step' => $action->workflowStep?->name,
                'rejected_by' => $action->actor?->name,
                'comment' => $action->comment,
                'date' => $action->acted_at?->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function comparePeriodsTool(array $arguments): array
    {
        $formName = trim((string) ($arguments['form_template_name'] ?? ''));

        $thisMonth = Jalalian::now();
        $lastMonth = $thisMonth->subMonths(1);

        $ranges = [
            'این ماه' => [$thisMonth->getFirstDayOfMonth()->toCarbon()->startOfDay(), $thisMonth->getEndDayOfMonth()->toCarbon()->endOfDay()->min(now())],
            'ماه قبل' => [$lastMonth->getFirstDayOfMonth()->toCarbon()->startOfDay(), $lastMonth->getEndDayOfMonth()->toCarbon()->endOfDay()],
        ];

        $comparison = [];

        foreach ($ranges as $label => [$start, $end]) {
            $base = FormSubmission::query()->whereBetween('submitted_at', [$start, $end]);

            if ($formName !== '') {
                $base->whereHas('formTemplate', fn ($q) => $q->where('title', 'like', "%{$formName}%"));
            }

            $comparison[$label] = [
                'submitted' => (clone $base)->count(),
                'approved' => (clone $base)->where('status', FormSubmissionStatus::Approved)->count(),
                'rejected' => (clone $base)->where('status', FormSubmissionStatus::Rejected)->count(),
                'still_pending' => (clone $base)->where('status', FormSubmissionStatus::PendingApproval)->count(),
            ];
        }

        return [
            'form' => $formName !== '' ? $formName : 'همهٔ فرم‌ها',
            'comparison' => $comparison,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function searchFormSubmissionsTool(array $arguments): array
    {
        $formName = trim((string) ($arguments['form_template_name'] ?? ''));
        $template = $formName !== '' ? $this->findFormTemplateByName($formName) : null;

        if (! $template) {
            return [
                'error' => "قالب فرمی با نام «{$formName}» پیدا نشد.",
                'available_forms' => FormTemplate::query()->orderBy('title')->pluck('title')->all(),
            ];
        }

        $query = FormSubmission::query()->where('form_template_id', $template->id)->with('submitter');

        $status = FormSubmissionStatus::tryFrom((string) ($arguments['status'] ?? ''));

        if ($status) {
            $query->where('status', $status);
        }

        $fieldKey = trim((string) ($arguments['field_key'] ?? ''));

        if ($fieldKey !== '') {
            $field = $template->fields()->withTrashed()->where('field_key', $fieldKey)->first();

            if (! $field) {
                return [
                    'error' => "فیلدی با کلید «{$fieldKey}» در فرم «{$template->title}» پیدا نشد.",
                    'available_fields' => $template->fields()->pluck('field_key')->all(),
                ];
            }

            $value = $arguments['value'] ?? null;
            $column = match ($field->field_type) {
                FormFieldType::Number => 'value_number',
                FormFieldType::DateJalali => 'value_date',
                default => 'value_text',
            };

            if ($field->field_type === FormFieldType::DateJalali && filled($value)) {
                try {
                    $value = Carbon::parse($value)->toDateString();
                } catch (\Throwable) {
                    return ['error' => "تاریخ «{$value}» قابل تشخیص نبود."];
                }
            }

            $operator = (string) ($arguments['operator'] ?? '==');
            $sqlOperator = match ($operator) {
                '!=', '>', '>=', '<', '<=' => $operator,
                'contains' => 'like',
                default => '=',
            };

            $query->whereHas('values', function ($q) use ($field, $column, $sqlOperator, $value) {
                $q->where('form_field_id', $field->id)
                    ->where($column, $sqlOperator, $sqlOperator === 'like' ? "%{$value}%" : $value);
            });
        }

        // include_answers's own payload (every field, with table-type
        // fields' full row arrays) is much bigger per submission than the
        // plain id/date/status listing, so the limit is capped tighter to
        // keep a multi-submission analysis from blowing up the model's
        // context - 15 full submissions is already a lot to reason over.
        $includeAnswers = (bool) ($arguments['include_answers'] ?? false);
        $limit = $includeAnswers
            ? max(1, min(15, (int) ($arguments['limit'] ?? 10)))
            : max(1, min(100, (int) ($arguments['limit'] ?? 20)));

        if ($includeAnswers) {
            $query->with(['values.formField' => fn ($q) => $q->withTrashed()]);
        }

        $submissions = $query->latest('submitted_at')->limit($limit)->get();

        return [
            'form' => $template->title,
            'matched_count' => $submissions->count(),
            'submissions' => $submissions->map(fn (FormSubmission $submission) => [
                'id' => $submission->id,
                'submitted_by' => $submission->submitter?->name,
                'submitted_at' => $submission->submitted_at?->format('Y-m-d'),
                'status' => $submission->status?->getLabel(),
                ...($includeAnswers ? ['answers' => $this->submissionAnswers($submission)] : []),
            ])->all(),
        ];
    }

    /**
     * Get a form template's own field list - label, technical key, type,
     * and (for a Table field) its column definitions - so the model can
     * work out which field a user's plain-language reference (e.g.
     * "department", "progress") actually maps to before calling
     * search_form_submissions, instead of guessing a field_key.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function formTemplateFieldsTool(array $arguments): array
    {
        $formName = trim((string) ($arguments['form_template_name'] ?? ''));
        $template = $formName !== '' ? $this->findFormTemplateByName($formName) : null;

        if (! $template) {
            return [
                'error' => "قالب فرمی با نام «{$formName}» پیدا نشد.",
                'available_forms' => FormTemplate::query()->orderBy('title')->pluck('title')->all(),
            ];
        }

        return [
            'form' => $template->title,
            'fields' => $template->fields()->orderBy('sort_order')->get()->map(fn (FormField $field) => [
                'label' => $field->label,
                'field_key' => $field->field_key,
                'field_type' => $field->field_type->value,
                'is_required' => (bool) $field->is_required,
                'table_columns' => $field->field_type === FormFieldType::Table
                    ? collect($field->table_columns ?? [])->map(fn (array $column) => [
                        'label' => $column['label'],
                        'key' => $column['key'],
                        'type' => $column['type'],
                    ])->all()
                    : null,
            ])->all(),
        ];
    }

    /**
     * Every answered field on a submission, label + value - shared between
     * summarize_submission_for_decision (one submission, full detail) and
     * search_form_submissions's include_answers option (several at once).
     * Signature/File values are masked to a short marker rather than their
     * raw payload (a signature pad's base64 image, an uploaded file's
     * storage path) - long, useless-to-a-language-model content that would
     * otherwise bloat every multi-submission analysis for no benefit, since
     * the model can't do anything with either besides note they exist.
     *
     * @return array<int, array{field: ?string, value: mixed}>
     */
    private function submissionAnswers(FormSubmission $submission): array
    {
        return $submission->values
            ->map(fn (FormSubmissionValue $value) => [
                'field' => $value->formField?->label,
                'value' => match ($value->formField?->field_type) {
                    FormFieldType::Signature => $value->value ? '[امضا ثبت شده]' : null,
                    FormFieldType::File => $value->value ? '[فایل پیوست شده]' : null,
                    default => $value->value,
                },
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function summarizeSubmissionTool(array $arguments): array
    {
        $id = $arguments['submission_id'] ?? null;

        $submission = $id
            ? FormSubmission::query()
                ->with(['formTemplate', 'submitter', 'values.formField' => fn ($q) => $q->withTrashed()])
                ->find($id)
            : null;

        if (! $submission) {
            return ['error' => "درخواستی با شناسهٔ «{$id}» پیدا نشد."];
        }

        $answers = $this->submissionAnswers($submission);

        $comparisons = $submission->values
            ->filter(fn (FormSubmissionValue $value) => $value->formField?->field_type === FormFieldType::Number)
            ->map(function (FormSubmissionValue $value) use ($submission) {
                $stats = FormSubmissionValue::query()
                    ->where('form_field_id', $value->form_field_id)
                    ->whereHas('formSubmission', fn ($q) => $q
                        ->where('form_template_id', $submission->form_template_id)
                        ->where('id', '!=', $submission->id)
                        ->whereIn('status', [FormSubmissionStatus::Approved, FormSubmissionStatus::Rejected]))
                    ->selectRaw('avg(value_number) as avg_value, min(value_number) as min_value, max(value_number) as max_value, count(*) as sample_count')
                    ->first();

                return [
                    'field' => $value->formField?->label,
                    'this_value' => (float) $value->value_number,
                    'past_average' => $stats?->avg_value !== null ? round((float) $stats->avg_value, 2) : null,
                    'past_min' => $stats?->min_value !== null ? (float) $stats->min_value : null,
                    'past_max' => $stats?->max_value !== null ? (float) $stats->max_value : null,
                    'past_sample_count' => (int) ($stats->sample_count ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'submission_id' => $submission->id,
            'form' => $submission->formTemplate?->title,
            'submitted_by' => $submission->submitter?->name,
            'status' => $submission->status?->getLabel(),
            'answers' => $answers,
            'numeric_field_comparisons' => $comparisons,
        ];
    }

    private function atRiskSlaSubmissionsTool(): array
    {
        $items = WorkflowInstance::query()
            ->where('status', WorkflowInstanceStatus::InProgress)
            ->where('subject_type', FormSubmission::class)
            ->with(['currentStep', 'subject.formTemplate'])
            ->get()
            ->map(function (WorkflowInstance $instance) {
                $slaHours = $instance->currentStep?->sla_hours;

                if (! $slaHours) {
                    return null;
                }

                $elapsedHours = $instance->updated_at->diffInHours(now());
                $percentElapsed = $elapsedHours / $slaHours;

                // Already-breached instances are list_alerts/
                // get_form_submissions_summary's job - this tool is only
                // the preventive warning before that point.
                if ($percentElapsed < 0.7 || $percentElapsed >= 1.0) {
                    return null;
                }

                return [
                    'form' => $instance->subject?->formTemplate?->title,
                    'step' => $instance->currentStep?->name,
                    'sla_hours' => $slaHours,
                    'elapsed_hours' => $elapsedHours,
                    'remaining_hours' => max(0, $slaHours - $elapsedHours),
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return ['message' => 'در حال حاضر هیچ درخواستی نزدیک به نقض SLA نیست.'];
        }

        return ['at_risk_count' => $items->count(), 'items' => $items->all()];
    }

    /**
     * The actionable counterpart to get_form_submissions_summary's plain
     * sla_breached_count - that count alone was the exact gap a manager
     * hit asking "which employee/manager hasn't approved something they
     * should have, so I can follow up with them": a number with nobody's
     * name attached. This resolves each breached step's real responsible
     * approver(s) (currentApprovers() already handles specific_user/role/
     * department_manager resolution) so the model can actually name who
     * to go talk to, not just how many are stuck.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function breachedSlaSubmissionsTool(array $arguments): array
    {
        $formNameFilter = trim((string) ($arguments['form_template_name'] ?? ''));
        $approverNameFilter = trim((string) ($arguments['approver_name'] ?? ''));
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));

        $items = WorkflowInstance::query()
            ->where('status', WorkflowInstanceStatus::InProgress)
            ->where('subject_type', FormSubmission::class)
            ->with(['currentStep', 'subject.formTemplate', 'subject.submitter', 'subject.department'])
            ->get()
            ->map(function (WorkflowInstance $instance) {
                $slaHours = $instance->currentStep?->sla_hours;

                if (! $slaHours) {
                    return null;
                }

                // Rounded to a whole hour: diffInHours() returns a precise
                // float in this Carbon version, and a manager asking "how
                // many hours overdue" has no use for sub-hour fractions.
                $elapsedHours = (int) round($instance->updated_at->diffInHours(now()));

                if ($elapsedHours <= $slaHours) {
                    return null;
                }

                $submission = $instance->subject;

                return [
                    'submission_id' => $submission?->id,
                    'reference_code' => $submission?->reference_code,
                    'form' => $submission?->formTemplate?->title,
                    'step' => $instance->currentStep?->name,
                    'submitted_by' => $submission?->submitter?->name,
                    'department' => $submission?->department?->name,
                    'responsible_approvers' => $instance->currentApprovers()->pluck('name')->all(),
                    'sla_hours' => $slaHours,
                    'overdue_hours' => $elapsedHours - $slaHours,
                ];
            })
            ->filter()
            // ->map() above turns this into a plain Support Collection (the
            // mapped items are arrays, not models), not the Eloquent
            // Collection the query started as - no type hint here, since
            // declaring either one would reject the other at runtime.
            ->when($formNameFilter !== '', fn ($collection) => $collection->filter(
                fn (array $item) => mb_stripos((string) $item['form'], $formNameFilter) !== false
            ))
            ->when($approverNameFilter !== '', fn ($collection) => $collection->filter(
                fn (array $item) => collect($item['responsible_approvers'])->contains(fn (string $name) => mb_stripos($name, $approverNameFilter) !== false)
            ))
            ->sortByDesc('overdue_hours')
            ->values();

        if ($items->isEmpty()) {
            return ['message' => 'در حال حاضر هیچ درخواست در حال تاییدی وجود ندارد که از مهلت (SLA) مرحلهٔ فعلی‌اش گذشته باشد.'];
        }

        return ['breached_count' => $items->count(), 'items' => $items->take($limit)->all()];
    }

    private function workflowHealthCheckTool(): array
    {
        $issues = [];

        $workflows = WorkflowDefinition::query()
            ->where('is_active', true)
            ->where('subject_type', FormSubmission::class)
            ->with(['steps.approverUser', 'steps.approverRole.users'])
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->steps->isEmpty()) {
                $issues[] = "گردش‌کار «{$workflow->name}» فعال است ولی هیچ مرحله‌ای ندارد.";

                continue;
            }

            foreach ($workflow->steps as $step) {
                if ($step->approver_type === ApproverType::SpecificUser && ! $step->approverUser) {
                    $issues[] = "مرحلهٔ «{$step->name}» در گردش‌کار «{$workflow->name}» به کاربر تأییدکننده‌ای اشاره می‌کند که دیگر وجود ندارد.";
                }

                if ($step->approver_type === ApproverType::Role && (! $step->approverRole || $step->approverRole->users->isEmpty())) {
                    $roleLabel = $step->approverRole?->name ?? 'حذف‌شده';
                    $issues[] = "مرحلهٔ «{$step->name}» در گردش‌کار «{$workflow->name}» به نقش «{$roleLabel}» اشاره می‌کند که هیچ کاربری ندارد.";
                }
            }
        }

        $formsNeedingDepartmentManager = FormTemplate::query()
            ->where('is_active', true)
            ->whereHas(
                'workflowDefinition.steps',
                fn ($q) => $q->where('approver_type', ApproverType::DepartmentManager),
            )
            ->with('department')
            ->get();

        foreach ($formsNeedingDepartmentManager as $template) {
            if (! $template->department) {
                $issues[] = "فرم «{$template->title}» به مرحلهٔ «مدیر دپارتمان» نیاز دارد ولی به هیچ دپارتمانی وصل نیست.";
            } elseif (! $template->department->manager_id) {
                $issues[] = "دپارتمان «{$template->department->name}» مدیر ندارد، در حالی که فرم «{$template->title}» به تأیید مدیر همین دپارتمان نیاز دارد.";
            }
        }

        if ($issues === []) {
            return ['message' => 'هیچ مشکل ساختاری در گردش‌کارهای فعال پیدا نشد.'];
        }

        return ['issues_count' => count($issues), 'issues' => $issues];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function addFieldToFormTemplateTool(array $arguments): array
    {
        $formName = trim((string) ($arguments['form_template_name'] ?? ''));
        $template = $this->findFormTemplateByName($formName);

        if (! $template) {
            return [
                'error' => "قالب فرمی با نام «{$formName}» پیدا نشد. فیلدی اضافه نشد.",
                'available_forms' => FormTemplate::query()->orderBy('title')->pluck('title')->all(),
            ];
        }

        $field = $arguments['field'] ?? null;

        if (! is_array($field)) {
            return ['error' => 'مشخصات فیلد جدید ارسال نشده است. فیلدی اضافه نشد.'];
        }

        $fieldKey = trim((string) ($field['field_key'] ?? ''));
        $label = trim((string) ($field['label'] ?? ''));
        $fieldType = FormFieldType::tryFrom((string) ($field['field_type'] ?? ''));

        if ($fieldKey === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
            return ['error' => "کلید فیلد «{$fieldKey}» نامعتبر است - باید فقط شامل حروف انگلیسی کوچک، عدد و زیرخط باشد و با یک حرف شروع شود. فیلدی اضافه نشد."];
        }

        // Scoped to this template's own (non-trashed) fields, same idea as
        // FieldsRelationManager's scopedUnique(): reusing a key that only
        // belonged to a since-removed field is fine.
        if ($template->fields()->where('field_key', $fieldKey)->exists()) {
            return ['error' => "فیلدی با کلید «{$fieldKey}» از قبل در فرم «{$template->title}» وجود دارد. فیلدی اضافه نشد."];
        }

        if ($label === '' || ! $fieldType) {
            return ['error' => 'برچسب یا نوع فیلد معتبر نیست. فیلدی اضافه نشد.'];
        }

        $workflowStepId = null;

        if (filled($field['workflow_step_name'] ?? null)) {
            $step = $template->workflowDefinition?->steps->firstWhere('name', $field['workflow_step_name']);

            if (! $step) {
                return [
                    'error' => "مرحلهٔ «{$field['workflow_step_name']}» در گردش‌کار این فرم پیدا نشد. فیلدی اضافه نشد.",
                    'available_steps' => $template->workflowDefinition?->steps->pluck('name')->all() ?? [],
                ];
            }

            $workflowStepId = $step->id;
        }

        $options = null;

        if (in_array($fieldType, [FormFieldType::Select, FormFieldType::Radio], true)) {
            $options = is_array($field['options'] ?? null) && count($field['options']) > 0 ? $field['options'] : null;

            if (! $options) {
                return ['error' => "فیلد «{$label}» از نوع لیست کشویی/انتخاب تکی است ولی هیچ گزینه‌ای برایش مشخص نشده. فیلدی اضافه نشد."];
            }
        }

        $newField = $template->fields()->create([
            'field_key' => $fieldKey,
            'label' => $label,
            'field_type' => $fieldType,
            'is_required' => (bool) ($field['is_required'] ?? false),
            'options' => $options,
            'workflow_step_id' => $workflowStepId,
            'sort_order' => ($template->fields()->max('sort_order') ?? 0) + 1,
        ]);

        return [
            'success' => true,
            'form' => $template->title,
            'field_key' => $newField->field_key,
            'message' => "فیلد «{$newField->label}» با موفقیت به فرم «{$template->title}» اضافه شد.".($template->is_active ? ' چون این فرم فعال است، این فیلد از همین حالا در ثبت‌های جدید نمایش داده می‌شود.' : ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function updateWorkflowStepTool(array $arguments): array
    {
        $workflowName = trim((string) ($arguments['workflow_name'] ?? ''));
        $workflow = $this->findWorkflowByName($workflowName);

        if (! $workflow) {
            return [
                'error' => "گردش‌کاری با نام «{$workflowName}» پیدا نشد. تغییری اعمال نشد.",
                'available_workflows' => WorkflowDefinition::query()->orderBy('name')->pluck('name')->all(),
            ];
        }

        $stepName = trim((string) ($arguments['step_name'] ?? ''));
        $step = $workflow->steps->first(fn (WorkflowStep $step) => $step->name === $stepName)
            ?? $workflow->steps->first(fn (WorkflowStep $step) => str_contains($step->name, $stepName));

        if (! $step) {
            return [
                'error' => "مرحله‌ای با نام «{$stepName}» در گردش‌کار «{$workflow->name}» پیدا نشد. تغییری اعمال نشد.",
                'available_steps' => $workflow->steps->pluck('name')->all(),
            ];
        }

        $updates = [];

        if (filled($arguments['new_name'] ?? null)) {
            $updates['name'] = trim($arguments['new_name']);
        }

        if (filled($arguments['sla_hours'] ?? null)) {
            $updates['sla_hours'] = (int) $arguments['sla_hours'];
        }

        if ($updates === []) {
            return ['error' => 'هیچ تغییری برای اعمال مشخص نشده است.'];
        }

        $step->update($updates);

        return [
            'success' => true,
            'workflow' => $workflow->name,
            'step' => $step->name,
            'message' => "مرحلهٔ «{$step->name}» در گردش‌کار «{$workflow->name}» با موفقیت به‌روزرسانی شد.",
        ];
    }

    private function findFormTemplateByName(string $name): ?FormTemplate
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return FormTemplate::query()->where('title', $name)->first()
            ?? FormTemplate::query()->where('title', 'like', "%{$name}%")->first();
    }

    private function findWorkflowByName(string $name): ?WorkflowDefinition
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return WorkflowDefinition::query()->where('name', $name)->first()
            ?? WorkflowDefinition::query()->where('name', 'like', "%{$name}%")->first();
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
            $alerts[] = ['severity' => 'warning', 'message' => Number::toPersian("{$overdueTasks} وظیفه از مهلت انجام خود گذشته است.")];
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
            $alerts[] = ['severity' => 'danger', 'message' => Number::toPersian("{$breachedTickets} تیکت پشتیبانی از مهلت SLA عبور کرده است.")];
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
            $alerts[] = ['severity' => 'warning', 'message' => Number::toPersian("{$chronicallyLateCount} نفر بیش از ۳ بار در این ماه با تأخیر حضور یافته‌اند.")];
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
}
