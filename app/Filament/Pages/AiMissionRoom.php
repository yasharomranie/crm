<?php

namespace App\Filament\Pages;

use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionCardStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Jobs\RunMissionStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
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

    public function getSelectedMission(): ?AiMission
    {
        return $this->missionId
            ? AiMission::query()->ownedBy(Auth::user())->with(['roles.profile', 'cards.profile'])->find($this->missionId)
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
        $this->missionId = AiMission::query()->ownedBy(Auth::user())->whereKey($id)->value('id');
        $this->showCreate = false;
    }

    public function startCreate(): void
    {
        $this->reset(['missionTitle', 'missionGoal', 'missionEnd', 'coordinatorId', 'coordinatorDirective', 'criticId', 'criticDirective', 'withCritic', 'pickingFor']);
        $this->maxRounds = 2;
        $this->tokenBudget = 60000;
        $this->specialists = [['profile_id' => null, 'directive' => '']];
        $this->showCreate = true;
        $this->missionId = null;
    }

    /**
     * A ready-made mission built on the lookups every custom assistant can be
     * granted (room availability, guests, support tickets); the user only picks
     * which assistant plays each part.
     */
    public function applyTemplate(): void
    {
        $this->missionTitle = 'وضعیت رزرو و پشتیبانی هفتهٔ آینده';
        $this->missionGoal = 'برای هفتهٔ آینده بگو کدام روزها اتاق خالی کم است، کدام مهمان یا رزرو نیاز به پیگیری دارد و کدام تیکت پشتیبانی باز مانده است.';
        $this->missionEnd = 'وقتی برای هر سه بخش (اتاق، مهمان، تیکت) یافتهٔ منبع‌دار داریم.';
        $this->coordinatorDirective = 'یافته‌ها را جمع کن و فقط تصمیم‌هایی پیشنهاد بده که شاهد دارند.';
        $this->specialists = [
            ['profile_id' => $this->specialists[0]['profile_id'] ?? null, 'directive' => 'با ابزار وضعیت اتاق‌های خالی هفتهٔ آینده را بررسی کن.'],
            ['profile_id' => $this->specialists[1]['profile_id'] ?? null, 'directive' => 'با ابزار وضعیت تیکت‌های پشتیبانی و مهمانان مرتبط را بررسی کن.'],
        ];
        $this->withCritic = true;
        $this->criticDirective = 'هر ادعای بی‌منبع یا عددی که با ابزار تأیید نشده را به چالش بکش.';
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
            $this->specialists[] = ['profile_id' => null, 'directive' => ''];
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

    public string $noteText = '';

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

    public function deleteMission(int $id): void
    {
        AiMission::query()->ownedBy(Auth::user())->whereKey($id)->where('status', '!=', AiMissionStatus::Running->value)->delete();

        if ($this->missionId === $id) {
            $this->missionId = null;
        }
    }
}
