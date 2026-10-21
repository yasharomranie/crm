<?php

namespace App\Filament\Pages;

use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
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

    /**
     * @var list<array{profile_id: ?int, role: string, directive: string}>
     */
    public array $roleRows = [];

    /**
     * @return Collection<int, AiMission>
     */
    public function getMissions(): Collection
    {
        return AiMission::query()->ownedBy(Auth::user())->latest('id')->limit(30)->get();
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
        $this->reset(['missionTitle', 'missionGoal', 'missionEnd']);
        $this->maxRounds = 2;
        $this->roleRows = [
            ['profile_id' => null, 'role' => AiMissionRoleType::Coordinator->value, 'directive' => ''],
            ['profile_id' => null, 'role' => AiMissionRoleType::Specialist->value, 'directive' => ''],
        ];
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
        $this->roleRows = [
            ['profile_id' => null, 'role' => AiMissionRoleType::Coordinator->value, 'directive' => 'یافته‌ها را جمع کن و فقط تصمیم‌هایی پیشنهاد بده که شاهد دارند.'],
            ['profile_id' => null, 'role' => AiMissionRoleType::Specialist->value, 'directive' => 'با ابزار وضعیت اتاق‌های خالی هفتهٔ آینده را بررسی کن.'],
            ['profile_id' => null, 'role' => AiMissionRoleType::Specialist->value, 'directive' => 'با ابزار وضعیت تیکت‌های پشتیبانی و مهمانان مرتبط را بررسی کن.'],
            ['profile_id' => null, 'role' => AiMissionRoleType::Critic->value, 'directive' => 'هر ادعای بی‌منبع یا عددی که با ابزار تأیید نشده را به چالش بکش.'],
        ];
    }

    public function addRoleRow(): void
    {
        if (count($this->roleRows) < 6) {
            $this->roleRows[] = ['profile_id' => null, 'role' => AiMissionRoleType::Specialist->value, 'directive' => ''];
        }
    }

    public function removeRoleRow(int $index): void
    {
        unset($this->roleRows[$index]);
        $this->roleRows = array_values($this->roleRows);
    }

    public function createMission(): void
    {
        $this->validate([
            'missionTitle' => ['required', 'string', 'max:150'],
            'missionGoal' => ['required', 'string', 'max:1500'],
            'missionEnd' => ['nullable', 'string', 'max:500'],
            'maxRounds' => ['required', 'integer', 'min:1', 'max:5'],
            'roleRows' => ['required', 'array', 'min:2', 'max:6'],
            'roleRows.*.profile_id' => ['required', 'integer'],
            'roleRows.*.role' => ['required', 'in:'.implode(',', array_column(AiMissionRoleType::cases(), 'value'))],
            'roleRows.*.directive' => ['nullable', 'string', 'max:500'],
        ], [], ['missionTitle' => 'عنوان', 'missionGoal' => 'هدف', 'roleRows.*.profile_id' => 'دستیار']);

        $rows = collect($this->roleRows);
        $allowed = $this->getProfiles()->pluck('id');

        $problem = match (true) {
            $rows->where('role', AiMissionRoleType::Coordinator->value)->count() !== 1 => 'دقیقاً یک هماهنگ‌کننده لازم است.',
            $rows->where('role', AiMissionRoleType::Specialist->value)->isEmpty() => 'دست‌کم یک متخصص لازم است.',
            $rows->where('role', AiMissionRoleType::Critic->value)->count() > 2 => 'حداکثر دو منتقد مجاز است.',
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
            ]);

            foreach ($rows->values() as $sort => $row) {
                $mission->roles()->create(['ai_assistant_profile_id' => $row['profile_id'], 'role' => $row['role'], 'directive' => $row['directive'] ?: null, 'sort' => $sort]);
            }

            return $mission;
        });

        $this->missionId = $mission->id;
        $this->showCreate = false;
        Notification::make()->title('مأموریت ساخته شد؛ «یک دور اجرا» را بزنید.')->success()->send();
    }

    public function runRound(): void
    {
        $mission = $this->getSelectedMission();

        if (! $mission) {
            return;
        }

        $result = app(MissionRunner::class)->runRound($mission, Auth::user());

        $notification = Notification::make()->title($result['ok'] ? 'دور اجرا شد؛ تخته و گزارش را ببینید.' : ($result['error'] ?? 'اجرا ناموفق بود.'));
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();
    }

    public function deleteMission(int $id): void
    {
        AiMission::query()->ownedBy(Auth::user())->whereKey($id)->where('status', '!=', AiMissionStatus::Running->value)->delete();

        if ($this->missionId === $id) {
            $this->missionId = null;
        }
    }
}
