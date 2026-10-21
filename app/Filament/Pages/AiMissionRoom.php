<?php

namespace App\Filament\Pages;

use App\Domain\Ai\Missions\MissionActionExecutor;
use App\Domain\Ai\Missions\MissionDashboard;
use App\Domain\Ai\Missions\MissionRunner;
use App\Domain\Ai\Missions\MissionSchedule;
use App\Domain\Ai\Missions\MissionTemplates;
use App\Enums\AiMissionActionStatus;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionCardStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Jobs\RunMissionStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * The operations room: hand a goal to a small team of the user's assistants
 * (coordinator, specialists, critic), run it one round at a time and read the
 * board and the draft report. The team only reads and reports; the manager
 * decides what to do with the result.
 */
class AiMissionRoom extends Page
{
    use HasAccessControlledPage;

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

    public int $tokenBudget = 60000;

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
            ? AiMission::query()->ownedBy(Auth::user())->with(['roles.profile', 'cards.profile', 'runs', 'actions.profile'])->find($this->missionId)
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
    }

    public function mount(): void
    {
        if ($this->missionId) {
            $this->fillScheduleFields(AiMission::query()->ownedBy(Auth::user())->find($this->missionId));
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
        $this->reset(['missionTitle', 'missionGoal', 'missionEnd', 'coordinatorId', 'coordinatorDirective', 'criticId', 'criticDirective', 'withCritic', 'pickingFor', 'templateKey', 'applyTemplateSchedule']);
        $this->maxRounds = 2;
        $this->tokenBudget = 60000;
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

        $this->missionId = $mission->id;
        $this->showCreate = false;
        Notification::make()->title('مأموریت ساخته شد؛ «شروع کار تیم» را بزنید.')->success()->send();
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

    public function deleteMission(int $id): void
    {
        AiMission::query()->ownedBy(Auth::user())->whereKey($id)->where('status', '!=', AiMissionStatus::Running->value)->delete();

        if ($this->missionId === $id) {
            $this->missionId = null;
        }
    }
}
