<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Pages\Concerns\HasCustomAssistantChatEngine;
use App\Models\AiAssistantProfile;
use App\Support\AiAssistantAvatar;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A single hub listing every conversational AI assistant in the app - the
 * two built-in, tool-using assistants (AiAssistant, ContractAssistant) as
 * link-out cards, plus any custom AiAssistantProfile the current user
 * owns or that's been shared - each opening into its own chat pane
 * in-place via HasCustomAssistantChatEngine. The four purely backend/
 * batch AI features (keyword classifier/discovery, SEO analyst, trip-
 * planner suggestions) are deliberately out of scope here: they have no
 * conversation to hold.
 */
class AiAssistantsHub extends Page
{
    use HasAccessControlledPage, HasCustomAssistantChatEngine;

    protected string $view = 'filament.pages.ai-assistants-hub';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'مرکز دستیاران هوشمند';

    protected static ?string $title = 'مرکز دستیاران هوشمند';

    public static function accessControlPermission(): string
    {
        return 'ai-assistants-hub.access';
    }

    public bool $showCreateForm = false;

    public string $newName = '';

    public string $newDescription = '';

    public string $newShape = 'circle';

    public string $newColor = 'Amber';

    public bool $newIsShared = false;

    public string $newSpecialties = '';

    public ?int $editingProfileId = null;

    /**
     * @return array<int, array{label: string, description: string, url: string, shape: string, color: string}>
     */
    public function builtInAssistants(): array
    {
        return [
            [
                'label' => 'دستیار هوشمند مدیریتی',
                'description' => 'گزارش و تحلیل زنده از اقامتی، پروژه‌ها، فروش و حضور و غیاب - با دسترسی کامل به ابزارهای داده‌ای.',
                'url' => AiAssistant::getUrl(),
                'shape' => 'circle',
                'color' => 'Blue',
            ],
            [
                'label' => 'دستیار حقوقی و قرارداد',
                'description' => 'پیش‌نویس و ممیزی قراردادهای روتین مجموعه.',
                'url' => ContractAssistant::getUrl(),
                'shape' => 'rounded-square',
                'color' => 'White',
            ],
        ];
    }

    /**
     * @return Collection<int, AiAssistantProfile>
     */
    public function getMyProfiles(): Collection
    {
        return AiAssistantProfile::query()
            ->visibleTo(Auth::user())
            ->latest('updated_at')
            ->get();
    }

    /**
     * Other visible profiles a conversation can be forwarded to - every
     * profile except whichever one is currently open.
     *
     * @return Collection<int, AiAssistantProfile>
     */
    public function getForwardTargets(): Collection
    {
        return $this->getMyProfiles()->reject(fn (AiAssistantProfile $profile) => $profile->id === $this->activeProfileId);
    }

    /**
     * @return array<int, array{name: string, description: string, shape: string, color: string}>
     */
    public function starterTemplates(): array
    {
        return [
            [
                'name' => 'پاسخ‌گوی بازخورد مهمانان',
                'description' => "وظیفهٔ تو خلاصه‌سازی و دسته‌بندی بازخوردها و تیکت‌های پشتیبانی مهمانان اقامتگاه است.\nهر بازخورد را به یکی از این دسته‌ها برسان: شکایت فنی، شکایت رفتاری، پیشنهاد، تقدیر. برای شکایت‌ها، لحن پاسخ باید رسمی، مؤدبانه و همراه با عذرخواهی کوتاه باشد؛ هیچ وعدهٔ مالی (تخفیف، بازگشت وجه) ندهد - فقط پیشنهاد مطرح کن که تیم پشتیبانی پیگیری می‌کند.",
                'shape' => 'teardrop',
                'color' => 'Brown',
            ],
            [
                'name' => 'دستیار پیش‌نویس محتوای شبکه اجتماعی',
                'description' => "وظیفهٔ تو نوشتن کپشن اینستاگرام برای معرفی اتاق‌ها/ویلاها و جاذبه‌های همدان است.\nهر کپشن حداکثر ۴ خط، لحن گرم و دعوت‌کننده (نه تبلیغاتی اغراق‌آمیز)، همراه با ۳ تا ۵ هشتگ مرتبط با گردشگری همدان. هیچ قیمتی ذکر نکن - فقط ارجاع بده به «برای رزرو پیام بدهید».",
                'shape' => 'flower',
                'color' => 'Red',
            ],
            [
                'name' => 'یادآور وظایف روزانه',
                'description' => "وظیفهٔ تو کمک به اولویت‌بندی تسک‌های روزانهٔ من است.\nوقتی فهرستی از تسک‌ها را برایت می‌فرستم، آن‌ها را بر اساس این ترتیب اولویت‌بندی کن: ۱) موعدهای سپری‌شده، ۲) موعدهای امروز، ۳) بقیه. برای هر تسک یک جمله دلیل اولویت بنویس. اگر اطلاعات موعد کامل نبود، به‌جای فرض کردن، صریحاً بپرس.",
                'shape' => 'pentagon',
                'color' => 'Yellow',
            ],
        ];
    }

    public function openCreateForm(): void
    {
        $this->reset(['newName', 'newDescription', 'newShape', 'newColor', 'newIsShared', 'newSpecialties', 'editingProfileId']);
        $this->showCreateForm = true;
    }

    /**
     * Opens the same create-form panel, pre-filled from an existing owned
     * profile - createProfile()/updateProfile() below branch on
     * $editingProfileId to decide whether to insert or update, so the UI
     * (ai-assistants-hub.blade.php) stays a single modal rather than a
     * second near-duplicate one.
     */
    public function openEditForm(int $profileId): void
    {
        $profile = AiAssistantProfile::query()->where('created_by', Auth::id())->find($profileId);

        if (! $profile) {
            return;
        }

        $this->editingProfileId = $profile->id;
        $this->newName = $profile->name ?? '';
        $this->newDescription = $profile->description ?? '';
        $this->newShape = $profile->avatar_shape;
        $this->newColor = $profile->avatar_color;
        $this->newIsShared = $profile->is_shared;
        $this->newSpecialties = implode('، ', $profile->specialties ?? []);
        $this->showCreateForm = true;
    }

    public function closeCreateForm(): void
    {
        $this->showCreateForm = false;
        $this->editingProfileId = null;
    }

    public function applyStarterTemplate(string $name, string $description, string $shape, string $color): void
    {
        $this->showCreateForm = true;
        $this->editingProfileId = null;
        $this->newName = $name;
        $this->newDescription = $description;
        $this->newShape = $shape;
        $this->newColor = $color;
    }

    /**
     * @return array{name: ?string, description: string, avatar_shape: string, avatar_color: string, is_shared: bool, specialties: list<string>}
     */
    private function validatedProfileFormData(): array
    {
        $data = $this->validate([
            'newName' => ['nullable', 'string', 'max:60'],
            'newDescription' => ['required', 'string', 'min:10'],
            'newShape' => ['required', 'string', 'in:'.implode(',', array_keys(AiAssistantAvatar::SHAPES))],
            'newColor' => ['required', 'string', 'in:'.implode(',', array_keys(AiAssistantAvatar::COLORS))],
            'newIsShared' => ['boolean'],
            'newSpecialties' => ['nullable', 'string', 'max:255'],
        ]);

        $specialties = collect(explode(',', $data['newSpecialties'] ?? ''))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['newName'] !== '' ? $data['newName'] : null,
            'description' => $data['newDescription'],
            'avatar_shape' => $data['newShape'],
            'avatar_color' => $data['newColor'],
            'is_shared' => $data['newIsShared'],
            'specialties' => $specialties,
        ];
    }

    public function createProfile(): void
    {
        $data = $this->validatedProfileFormData();

        $profile = AiAssistantProfile::create(['created_by' => Auth::id(), ...$data]);

        $this->showCreateForm = false;
        $this->openProfile($profile->id);

        Notification::make()->title('دستیار «'.$profile->displayName().'» ساخته شد.')->success()->send();
    }

    public function updateProfile(): void
    {
        $profile = AiAssistantProfile::query()->where('created_by', Auth::id())->find($this->editingProfileId);

        if (! $profile) {
            $this->closeCreateForm();

            return;
        }

        $profile->update($this->validatedProfileFormData());

        $this->closeCreateForm();

        Notification::make()->title('دستیار «'.$profile->displayName().'» به‌روزرسانی شد.')->success()->send();
    }

    /**
     * Starts a fresh profile from an existing one's configuration (name,
     * prompt, avatar, specialties) - the user's own, or someone else's
     * shared one as a starting point. Deliberately never copies the
     * source's knowledge base, watched sources, or consultable/shared
     * state: those are specific to the life this profile has already
     * lived (and, when cloning a stranger's shared profile, copying their
     * accumulated knowledge into a new private copy would leak it), so the
     * clone starts clean the same way a brand-new profile would.
     */
    public function duplicateProfile(int $profileId): void
    {
        $source = AiAssistantProfile::query()->visibleTo(Auth::user())->find($profileId);

        if (! $source) {
            return;
        }

        $profile = AiAssistantProfile::create([
            'created_by' => Auth::id(),
            'name' => trim(($source->name ?? $source->displayName()).' (کپی)'),
            'description' => $source->description,
            'avatar_shape' => $source->avatar_shape,
            'avatar_color' => $source->avatar_color,
            'is_shared' => false,
            'specialties' => $source->specialties,
            'model' => $source->model,
        ]);

        $this->openProfile($profile->id);

        Notification::make()->title('کپی «'.$profile->displayName().'» ساخته شد.')->success()->send();
    }

    public function deleteProfile(int $profileId): void
    {
        $profile = AiAssistantProfile::query()->where('created_by', Auth::id())->find($profileId);

        if (! $profile) {
            return;
        }

        if ($this->activeProfileId === $profile->id) {
            $this->closeProfile();
        }

        $profile->delete();

        Notification::make()->title('دستیار حذف شد.')->success()->send();
    }
}
