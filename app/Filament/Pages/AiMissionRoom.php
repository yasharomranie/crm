<?php

namespace App\Filament\Pages;

use App\Domain\Ai\Missions\MissionActionExecutor;
use App\Domain\Ai\Missions\MissionBrief;
use App\Domain\Ai\Missions\MissionDashboard;
use App\Domain\Ai\Missions\MissionRunner;
use App\Domain\Ai\Missions\MissionSchedule;
use App\Domain\Ai\Missions\MissionTemplates;
use App\Enums\AiMissionActionStatus;
use App\Enums\AiMissionBriefStatus;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionCardStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Jobs\BuildMissionBriefJob;
use App\Jobs\ProcessMissionSourceJob;
use App\Jobs\RunMissionStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use App\Models\AiMissionSource;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * The operations room: hand a goal to a small team of the user's assistants
 * (coordinator, specialists, critic), run it one round at a time and read the
 * board and the draft report. The team only reads and reports; the manager
 * decides what to do with the result.
 */
class AiMissionRoom extends Page
{
    use HasAccessControlledPage, WithFileUploads;

    protected string $view = 'filament.pages.ai-mission-room';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'اتاق عملیات';

    protected static ?string $title = 'اتاق عملیات دستیاران';

    public static function accessControlPermission(): string
    {
        return 'ai-mission-room.access';
    }

    #[Url(as: 'mission')]
    public ?int $missionId = null;

    public bool $showCreate = false;

    public string $missionTitle = '';

    public string $missionGoal = '';

    public string $missionEnd = '';

    public int $maxRounds = 2;

    public int $tokenBudget = 200000;

    public ?int $coordinatorId = null;

    public string $coordinatorDirective = '';

    /**
     * @var list<array{profile_id: ?int, directive: string}>
     */
    public array $specialists = [];

    public bool $withCritic = false;

    public ?string $templateKey = null;

    public bool $applyTemplateSchedule = false;

    public ?int $criticId = null;

    public string $criticDirective = '';

    /** Which team slot the assistant picker is open for: coordinator, critic or specialist:{index}. */
    public ?string $pickingFor = null;

    /**
     * @return Collection<int, AiMission>
     */
    public function getMissions(): Collection
    {
        return AiMission::query()->ownedBy(Auth::user())->with('roles.profile')->latest('id')->limit(30)->get();
    }

    /**
     * @return array{tiles: array<string, int>, attention: list<array<string, mixed>>, running: list<array<string, mixed>>, recurring: list<array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function getDashboard(): array
    {
        return app(MissionDashboard::class)->build(Auth::user());
    }

    public function getSelectedMission(): ?AiMission
    {
        return $this->missionId
            ? AiMission::query()->ownedBy(Auth::user())->with(['roles.profile', 'roles.sources', 'cards.profile', 'cards.sourceChunk.source', 'runs', 'actions.profile', 'sources'])->find($this->missionId)
            : null;
    }

    /**
     * @return Collection<int, AiAssistantProfile>
     */
    public function getProfiles(): Collection
    {
        return AiAssistantProfile::query()->visibleTo(Auth::user())->orderBy('name')->get();
    }

    public function openMission(int $id): void
    {
        $mission = AiMission::query()->ownedBy(Auth::user())->find($id);
        $this->missionId = $mission?->id;
        $this->showCreate = false;

        $this->fillScheduleFields($mission);
        $this->fillBriefFields($mission);
    }

    public function mount(): void
    {
        if ($this->missionId) {
            $mission = AiMission::query()->ownedBy(Auth::user())->find($this->missionId);
            $this->fillScheduleFields($mission);
            $this->fillBriefFields($mission);
        }
    }

    private function fillScheduleFields(?AiMission $mission): void
    {
        if (! $mission) {
            return;
        }

        $this->scheduleFrequency = $mission->schedule ?? '';
        $this->scheduleTime = $mission->schedule_time ?: '08:00';
        $this->scheduleWeekday = $mission->schedule_weekday ?? 6;
        $this->scheduleDay = $mission->schedule_day ?? 1;
    }

    public function startCreate(): void
    {
        $this->discardPendingSources();
        $this->reset(['missionTitle', 'missionGoal', 'missionEnd', 'coordinatorId', 'coordinatorDirective', 'criticId', 'criticDirective', 'withCritic', 'pickingFor', 'templateKey', 'applyTemplateSchedule']);
        $this->maxRounds = 2;
        $this->tokenBudget = 200000;
        $this->specialists = [['profile_id' => null, 'directive' => '', 'needs' => []]];
        $this->showCreate = true;
        $this->missionId = null;
    }

    /**
     * Fills the form from one of the ready-made missions (MissionTemplates):
     * goal, directives and the lookups each specialist will need. Assistants the
     * user already picked stay where they are.
     */
    public function applyTemplate(string $key): void
    {
        $template = app(MissionTemplates::class)->find($key);

        if (! $template) {
            return;
        }

        $this->templateKey = $key;
        $this->applyTemplateSchedule = false;
        $this->missionTitle = $template['title'];
        $this->missionGoal = $template['goal'];
        $this->missionEnd = $template['end'];
        $this->coordinatorDirective = $template['coordinator'];
        $this->specialists = collect($template['specialists'])->values()->map(fn (array $specialist, int $index) => [
            'profile_id' => $this->specialists[$index]['profile_id'] ?? null,
            'directive' => $specialist['directive'],
            'needs' => $specialist['needs'],
        ])->all();
        $this->withCritic = $template['critic'] !== null;
        $this->criticDirective = $template['critic'] ?? '';

        if (! $this->withCritic) {
            $this->criticId = null;
        }
    }

    public function openPicker(string $slot): void
    {
        $this->pickingFor = $slot;
    }

    public function closePicker(): void
    {
        $this->pickingFor = null;
    }

    public function pickProfile(int $profileId): void
    {
        if (! $this->getProfiles()->contains('id', $profileId) || $this->pickingFor === null) {
            return;
        }

        if ($this->pickingFor === 'coordinator') {
            $this->coordinatorId = $profileId;
        } elseif ($this->pickingFor === 'critic') {
            $this->criticId = $profileId;
        } elseif (preg_match('/^specialist:(\d+)$/', $this->pickingFor, $match) && isset($this->specialists[(int) $match[1]])) {
            $this->specialists[(int) $match[1]]['profile_id'] = $profileId;
        }

        $this->pickingFor = null;
    }

    public function addSpecialist(): void
    {
        if (count($this->specialists) < 4) {
            $this->specialists[] = ['profile_id' => null, 'directive' => '', 'needs' => []];
            $this->pickingFor = 'specialist:'.(count($this->specialists) - 1);
        }
    }

    public function removeSpecialist(int $index): void
    {
        unset($this->specialists[$index]);
        $this->specialists = array_values($this->specialists);
        $this->pickingFor = null;
    }

    public function toggleCritic(): void
    {
        $this->withCritic = ! $this->withCritic;

        if (! $this->withCritic) {
            $this->criticId = null;
        }
    }

    public function createMission(): void
    {
        $this->validate([
            'missionTitle' => ['required', 'string', 'max:150'],
            'missionGoal' => ['required', 'string', 'max:1500'],
            'missionEnd' => ['nullable', 'string', 'max:500'],
            'maxRounds' => ['required', 'integer', 'min:1', 'max:5'],
            'tokenBudget' => ['required', 'integer', 'min:1000', 'max:2000000'],
            'coordinatorDirective' => ['nullable', 'string', 'max:500'],
            'criticDirective' => ['nullable', 'string', 'max:500'],
            'specialists.*.directive' => ['nullable', 'string', 'max:500'],
        ], [], ['missionTitle' => 'عنوان', 'missionGoal' => 'هدف']);

        $rows = collect([
            ['profile_id' => $this->coordinatorId, 'role' => AiMissionRoleType::Coordinator->value, 'directive' => $this->coordinatorDirective],
            ...collect($this->specialists)->map(fn (array $row) => ['profile_id' => $row['profile_id'], 'role' => AiMissionRoleType::Specialist->value, 'directive' => $row['directive']])->all(),
            ...($this->withCritic ? [['profile_id' => $this->criticId, 'role' => AiMissionRoleType::Critic->value, 'directive' => $this->criticDirective]] : []),
        ]);
        $allowed = $this->getProfiles()->pluck('id');

        $problem = match (true) {
            ! $this->coordinatorId => 'یک هماهنگ‌کننده انتخاب کنید.',
            $rows->where('role', AiMissionRoleType::Specialist->value)->whereNotNull('profile_id')->isEmpty() => 'دست‌کم یک متخصص انتخاب کنید.',
            $rows->contains(fn (array $row) => $row['profile_id'] === null) => 'برای هر جایگاه یک دستیار انتخاب کنید (یا جایگاه خالی را حذف کنید).',
            $rows->pluck('profile_id')->unique()->count() !== $rows->count() => 'هر دستیار فقط یک نقش می‌گیرد.',
            $rows->pluck('profile_id')->diff($allowed)->isNotEmpty() => 'یکی از دستیارهای انتخاب‌شده در دسترس شما نیست.',
            default => null,
        };

        if ($problem) {
            Notification::make()->title($problem)->danger()->send();

            return;
        }

        $mission = DB::transaction(function () use ($rows) {
            $mission = AiMission::create([
                'created_by' => Auth::id(),
                'title' => $this->missionTitle,
                'goal' => $this->missionGoal,
                'end_condition' => filled($this->missionEnd) ? $this->missionEnd : null,
                'max_rounds' => $this->maxRounds,
                'token_budget' => $this->tokenBudget,
            ]);

            foreach ($rows->values() as $sort => $row) {
                $mission->roles()->create(['ai_assistant_profile_id' => $row['profile_id'], 'role' => $row['role'], 'directive' => $row['directive'] ?: null, 'sort' => $sort]);
            }

            $this->getPendingSources()->each->update(['ai_mission_id' => $mission->id]);

            return $mission;
        });

        $template = $this->applyTemplateSchedule && $this->templateKey ? app(MissionTemplates::class)->find($this->templateKey) : null;

        if ($template) {
            $schedule = $template['schedule'];
            $mission->update([
                'schedule' => $schedule['frequency'],
                'schedule_time' => $schedule['time'],
                'schedule_weekday' => $schedule['weekday'],
                'schedule_day' => $schedule['day'],
                'next_run_at' => app(MissionSchedule::class)->nextRun($schedule['frequency'], $schedule['time'], $schedule['weekday'], $schedule['day']),
            ]);
        }

        $this->newSourceIds = [];
        $this->missionId = $mission->id;
        $this->showCreate = false;
        Notification::make()
            ->title($mission->sources()->exists() ? 'مأموریت ساخته شد؛ فایل‌ها در کتابخانهٔ آن هستند و وقتی «آماده» شدند تیم از آن‌ها استفاده می‌کند.' : 'مأموریت ساخته شد؛ «شروع کار تیم» را بزنید.')
            ->success()
            ->send();
    }

    public function runRound(): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission) {
            return;
        }

        $started = app(MissionRunner::class)->start($mission, Auth::user());

        if (! $started['ok']) {
            Notification::make()->title($started['error'] ?? 'اجرا ممکن نشد.')->danger()->send();

            return;
        }

        RunMissionStepJob::dispatch($mission->id, Auth::id(), $started['round'], 0);

        $mission->refresh();

        match ($mission->status) {
            AiMissionStatus::Running => Notification::make()->title('تیم شروع به کار کرد؛ می‌توانید صفحه را ببندید، نتیجه در همین‌جا می‌ماند.')->success()->send(),
            AiMissionStatus::Failed, AiMissionStatus::Stopped => Notification::make()->title($mission->last_error ?? 'اجرا ناموفق بود.')->danger()->send(),
            default => Notification::make()->title('دور اجرا شد؛ تخته و گزارش را ببینید.')->success()->send(),
        };
    }

    public function stopMission(): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission || $mission->status !== AiMissionStatus::Running) {
            return;
        }

        // A run whose worker died (nothing touched it for a few minutes) is released at once.
        if ($mission->updated_at->lt(now()->subMinutes(3))) {
            app(MissionRunner::class)->abandonRound($mission, $mission->rounds_run + 1, 'متوقف شد.', AiMissionStatus::Stopped);
            Notification::make()->title('مأموریت متوقف شد.')->success()->send();

            return;
        }

        $mission->update(['cancel_requested' => true, 'current_step' => 'در حال توقف… (بعد از پایان کار فعلی)']);
        Notification::make()->title('درخواست توقف ثبت شد؛ بعد از پایان مرحلهٔ فعلی می‌ایستد.')->success()->send();
    }

    public function saveBudget(int $tokens): void
    {
        $mission = $this->getSelectedMission();

        if ($mission && $tokens >= 1000 && $tokens <= 2000000) {
            $mission->update(['token_budget' => $tokens]);
            Notification::make()->title('سقف توکن به‌روز شد.')->success()->send();
        }
    }

    public string $scheduleFrequency = '';

    public string $scheduleTime = '08:00';

    public int $scheduleWeekday = 6;

    public int $scheduleDay = 1;

    public string $noteText = '';

    /**
     * Makes the mission recur (or stops it): at the chosen time a fresh round
     * starts by itself and the owner is told when it needs a look. An empty
     * frequency switches it off.
     */
    public function saveSchedule(): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission) {
            return;
        }

        $this->validate([
            'scheduleFrequency' => ['nullable', 'in:daily,weekly,monthly'],
            'scheduleTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'scheduleWeekday' => ['integer', 'between:0,6'],
            'scheduleDay' => ['integer', 'between:1,28'],
        ], [], ['scheduleTime' => 'ساعت']);

        if ($this->scheduleFrequency === '') {
            $mission->update(['schedule' => null, 'next_run_at' => null]);
            Notification::make()->title('تکرار خودکار خاموش شد.')->success()->send();

            return;
        }

        $mission->update([
            'schedule' => $this->scheduleFrequency,
            'schedule_time' => $this->scheduleTime,
            'schedule_weekday' => $this->scheduleFrequency === 'weekly' ? $this->scheduleWeekday : null,
            'schedule_day' => $this->scheduleFrequency === 'monthly' ? $this->scheduleDay : null,
            'next_run_at' => app(MissionSchedule::class)->nextRun($this->scheduleFrequency, $this->scheduleTime, $this->scheduleWeekday, $this->scheduleDay),
        ]);

        Notification::make()->title('تکرار خودکار ذخیره شد.')->success()->send();
    }

    /**
     * A message to the team ("focus on the north villas"): every step that has
     * not started yet reads it, so it also steers a run that is already going.
     */
    public function addNote(): void
    {
        $mission = $this->getSelectedMission();
        $text = trim($this->noteText);

        if (! $mission || $text === '' || mb_strlen($text) > 500) {
            Notification::make()->title('پیام را بنویسید (حداکثر ۵۰۰ نویسه).')->danger()->send();

            return;
        }

        $notes = $mission->notes ?? [];

        if (count($notes) >= 10) {
            Notification::make()->title('حداکثر ۱۰ پیام برای هر مأموریت؛ یکی از پیام‌های قبلی را پاک کنید.')->danger()->send();

            return;
        }

        $notes[] = ['text' => $text, 'at' => now()->toIso8601String()];
        $mission->update(['notes' => $notes]);
        $this->noteText = '';

        Notification::make()->title($mission->status === AiMissionStatus::Running ? 'پیام ثبت شد؛ از مرحلهٔ بعدی اعمال می‌شود.' : 'پیام ثبت شد؛ از دور بعدی اعمال می‌شود.')->success()->send();
    }

    public function removeNote(int $index): void
    {
        $mission = $this->getSelectedMission();
        $notes = $mission?->notes ?? [];

        if ($mission && isset($notes[$index])) {
            unset($notes[$index]);
            $mission->update(['notes' => array_values($notes)]);
        }
    }

    /**
     * Rejecting a finding keeps it out of what the team reads next and out of
     * the report; locking confirms it as trusted; reopening undoes either.
     */
    public function setCardStatus(int $cardId, string $status): void
    {
        $mission = $this->getSelectedMission();
        $newStatus = AiMissionCardStatus::tryFrom($status);

        if (! $mission || ! $newStatus || $mission->status === AiMissionStatus::Running) {
            return;
        }

        $mission->cards()->whereKey($cardId)->where('kind', AiMissionCardKind::Finding->value)->update(['status' => $newStatus->value]);
    }

    /**
     * Every action still waiting for the user's decision, across all their
     * missions - the central approval queue.
     *
     * @return Collection<int, AiMissionAction>
     */
    public function getPendingActions(): Collection
    {
        return AiMissionAction::query()
            ->whereHas('mission', fn ($query) => $query->ownedBy(Auth::user()))
            ->where('status', AiMissionActionStatus::Pending->value)
            ->with(['mission', 'profile'])
            ->oldest('id')
            ->get();
    }

    public function approveAction(int $actionId): void
    {
        $action = $this->ownedPendingAction($actionId);

        if (! $action) {
            return;
        }

        $action = app(MissionActionExecutor::class)->approve($action, Auth::user());
        $failed = $action->status === AiMissionActionStatus::Failed;
        $notification = Notification::make()
            ->title($failed ? 'اقدام انجام نشد' : 'اقدام انجام شد')
            ->body($failed ? (string) $action->error : $action->summary);
        $failed ? $notification->danger()->send() : $notification->success()->send();
    }

    public function rejectAction(int $actionId): void
    {
        $action = $this->ownedPendingAction($actionId);

        if ($action) {
            app(MissionActionExecutor::class)->reject($action, Auth::user());
        }
    }

    /** Only the mission's owner can decide on its actions. */
    private function ownedPendingAction(int $actionId): ?AiMissionAction
    {
        return AiMissionAction::query()
            ->whereKey($actionId)
            ->where('status', AiMissionActionStatus::Pending->value)
            ->whereHas('mission', fn ($query) => $query->ownedBy(Auth::user()))
            ->first();
    }

    public const SOURCE_EXTENSIONS = ['txt', 'md', 'csv', 'docx', 'pdf', 'xlsx'];

    public const MAX_SOURCES_PER_MISSION = 20;

    /** @var array<int, mixed> */
    public array $sourceUploads = [];

    public string $sourceKind = 'policy';

    /**
     * Files uploaded while a new mission is being set up; they get their
     * mission when it is created.
     *
     * @var array<int, int|string>
     */
    public array $newSourceIds = [];

    public string $briefSummary = '';

    /**
     * One textarea per brief list, a line per item.
     *
     * @var array<string, string>
     */
    public array $briefLists = [];

    /**
     * Files uploaded in the new-mission form that have no mission yet.
     *
     * @return Collection<int, AiMissionSource>
     */
    public function getPendingSources(): Collection
    {
        return AiMissionSource::query()->ownedBy(Auth::user())->whereNull('ai_mission_id')->whereIn('id', array_map('intval', $this->newSourceIds))->orderBy('id')->get();
    }

    public function uploadSources(): void
    {
        $this->validate([
            'sourceUploads' => ['required', 'array', 'max:10'],
            'sourceUploads.*' => ['file', 'max:20480', 'extensions:'.implode(',', self::SOURCE_EXTENSIONS)],
            'sourceKind' => ['required', 'in:'.implode(',', array_column(AiMissionSourceKind::cases(), 'value'))],
        ], [], ['sourceUploads.*' => 'فایل']);

        $mission = $this->showCreate || ! $this->missionId ? null : AiMission::query()->ownedBy(Auth::user())->find($this->missionId);

        if (! $this->showCreate && (! $mission || $mission->status === AiMissionStatus::Running)) {
            Notification::make()->title($mission ? 'تا پایان اجرا نمی‌توان فایل اضافه کرد.' : 'اول یک مأموریت را باز کنید یا بسازید.')->warning()->send();

            return;
        }

        $room = self::MAX_SOURCES_PER_MISSION - ($mission ? $mission->sources()->count() : count($this->getPendingSources()));

        foreach (array_slice($this->sourceUploads, 0, max(0, $room)) as $file) {
            $name = $file->getClientOriginalName();
            $source = AiMissionSource::create([
                'user_id' => Auth::id(),
                'ai_mission_id' => $mission?->id,
                'title' => Str::limit(pathinfo($name, PATHINFO_FILENAME), 120, ''),
                'original_filename' => Str::limit($name, 200, ''),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'kind' => $this->sourceKind,
                'path' => $file->store('mission-sources'),
            ]);

            if (! $mission) {
                $this->newSourceIds[] = (string) $source->id;
            }

            ProcessMissionSourceJob::dispatch($source->id);
        }

        $skipped = max(0, count($this->sourceUploads) - max(0, $room));
        $this->reset('sourceUploads');

        Notification::make()
            ->title($skipped > 0 ? 'سقف '.self::MAX_SOURCES_PER_MISSION.' فایل برای هر مأموریت پر شد؛ '.$skipped.' فایل بارگذاری نشد.' : 'فایل‌ها بارگذاری شدند و در پس‌زمینه خوانده می‌شوند.')
            ->{$skipped > 0 ? 'warning' : 'success'}()
            ->send();
    }

    /** Takes back a file uploaded in the new-mission form before the mission exists. */
    public function removePendingSource(int $sourceId): void
    {
        $this->getPendingSources()->firstWhere('id', $sourceId)?->delete();
        $this->newSourceIds = array_values(array_diff($this->newSourceIds, [(string) $sourceId, $sourceId]));
    }

    public function cancelCreate(): void
    {
        $this->discardPendingSources();
        $this->showCreate = false;
    }

    private function discardPendingSources(): void
    {
        $this->getPendingSources()->each->delete();
        $this->newSourceIds = [];
    }

    /**
     * Limits what one member may read: by default every file of the mission,
     * after the first change only the ones ticked. Ticking all again goes back
     * to the default; a member always keeps at least one file.
     */
    public function toggleRoleSource(int $roleId, int $sourceId): void
    {
        $mission = $this->getSelectedMission();
        $role = $mission?->roles->firstWhere('id', $roleId);
        $attached = $mission?->sources->pluck('id')->all() ?? [];

        if (! $mission || ! $role || ! in_array($sourceId, $attached, true) || $mission->status === AiMissionStatus::Running) {
            return;
        }

        $current = $role->sources->pluck('id')->all() ?: $attached;
        $next = in_array($sourceId, $current, true) ? array_values(array_diff($current, [$sourceId])) : [...$current, $sourceId];

        if ($next === []) {
            Notification::make()->title('هر عضو باید دست‌کم یک فایل ببیند؛ اگر نباید فایلی ببیند، همین عضو را از مأموریت جدا کنید.')->warning()->send();

            return;
        }

        $role->sources()->sync(count(array_intersect($attached, $next)) === count($attached) ? [] : $next);
    }

    public function setSourceKind(int $sourceId, string $kind): void
    {
        $newKind = AiMissionSourceKind::tryFrom($kind);

        if ($newKind) {
            AiMissionSource::query()->ownedBy(Auth::user())->whereKey($sourceId)->update(['kind' => $newKind->value]);
        }
    }

    public function retrySource(int $sourceId): void
    {
        $source = AiMissionSource::query()->ownedBy(Auth::user())->where('status', AiMissionSourceStatus::Failed->value)->find($sourceId);

        if ($source) {
            $source->update(['status' => AiMissionSourceStatus::Pending->value, 'error' => null]);
            ProcessMissionSourceJob::dispatch($source->id);
        }
    }

    public function deleteSource(int $sourceId): void
    {
        $source = AiMissionSource::query()->ownedBy(Auth::user())->whereNotNull('ai_mission_id')->whereDoesntHave('mission', fn ($query) => $query->where('status', AiMissionStatus::Running->value))->find($sourceId);

        $source?->delete();
    }

    public function buildBrief(): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission || $mission->status === AiMissionStatus::Running || $mission->brief_status === AiMissionBriefStatus::Building) {
            return;
        }

        $sources = $mission->sources;

        if ($sources->isEmpty() || $sources->contains(fn (AiMissionSource $source) => $source->status !== AiMissionSourceStatus::Ready)) {
            Notification::make()->title('اول فایل‌ها باید خوانده شوند؛ فایل‌های «ناموفق» را دوباره امتحان یا حذف کنید.')->warning()->send();

            return;
        }

        $mission->update(['brief_status' => AiMissionBriefStatus::Building, 'brief_error' => null]);
        BuildMissionBriefJob::dispatch($mission->id);
        $this->refreshBrief();
    }

    /** Called while a file is being read or a brief built: picks up the result when it lands. */
    public function refreshBrief(): void
    {
        $mission = $this->getSelectedMission();

        if ($mission && $mission->brief_status !== AiMissionBriefStatus::Building) {
            $this->fillBriefFields($mission);
        }
    }

    private function fillBriefFields(?AiMission $mission): void
    {
        $brief = MissionBrief::normalize($mission?->brief);
        $this->briefSummary = $brief['summary'];
        $this->briefLists = collect(MissionBrief::LISTS)->mapWithKeys(fn (string $label, string $key) => [$key => implode("\n", $brief[$key])])->all();
    }

    /** Stores the text the manager edited. Any edit asks for a new approval. */
    public function saveBrief(): void
    {
        $this->storeBrief(approve: false);
    }

    public function approveBrief(): void
    {
        $this->storeBrief(approve: true);
    }

    private function storeBrief(bool $approve): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission || $mission->status === AiMissionStatus::Running || in_array($mission->brief_status, [AiMissionBriefStatus::None, AiMissionBriefStatus::Building], true)) {
            return;
        }

        $brief = MissionBrief::normalize([
            'summary' => $this->briefSummary,
            ...collect($this->briefLists)->map(fn ($text) => preg_split('/\R/u', (string) $text) ?: [])->all(),
        ]);

        $mission->update([
            'brief' => $brief,
            'brief_status' => $approve ? AiMissionBriefStatus::Approved : AiMissionBriefStatus::Review,
            'brief_sources_hash' => MissionBrief::sourcesHash($mission),
            'brief_approved_at' => $approve ? now() : null,
            'brief_error' => null,
        ]);

        $this->fillBriefFields($mission->refresh());

        Notification::make()->title($approve ? 'خلاصهٔ استراتژی تأیید شد؛ تیم از این به بعد با آن کار می‌کند.' : 'ویرایش ذخیره شد؛ برای استفادهٔ تیم باید تأیید شود.')->success()->send();
    }

    public function deleteMission(int $id): void
    {
        AiMission::query()->ownedBy(Auth::user())->whereKey($id)->where('status', '!=', AiMissionStatus::Running->value)->get()->each->delete();

        if ($this->missionId === $id) {
            $this->missionId = null;
        }
    }
}
