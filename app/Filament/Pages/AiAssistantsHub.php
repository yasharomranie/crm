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
                'color' => 'Amber',
            ],
            [
                'label' => 'دستیار حقوقی و قرارداد',
                'description' => 'پیش‌نویس و ممیزی قراردادهای روتین مجموعه.',
                'url' => ContractAssistant::getUrl(),
                'shape' => 'hexagon',
                'color' => 'Indigo',
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
                'color' => 'Teal',
            ],
            [
                'name' => 'دستیار پیش‌نویس محتوای شبکه اجتماعی',
                'description' => "وظیفهٔ تو نوشتن کپشن اینستاگرام برای معرفی اتاق‌ها/ویلاها و جاذبه‌های همدان است.\nهر کپشن حداکثر ۴ خط، لحن گرم و دعوت‌کننده (نه تبلیغاتی اغراق‌آمیز)، همراه با ۳ تا ۵ هشتگ مرتبط با گردشگری همدان. هیچ قیمتی ذکر نکن - فقط ارجاع بده به «برای رزرو پیام بدهید».",
                'shape' => 'flower',
                'color' => 'Pink',
            ],
            [
                'name' => 'یادآور وظایف روزانه',
                'description' => "وظیفهٔ تو کمک به اولویت‌بندی تسک‌های روزانهٔ من است.\nوقتی فهرستی از تسک‌ها را برایت می‌فرستم، آن‌ها را بر اساس این ترتیب اولویت‌بندی کن: ۱) موعدهای سپری‌شده، ۲) موعدهای امروز، ۳) بقیه. برای هر تسک یک جمله دلیل اولویت بنویس. اگر اطلاعات موعد کامل نبود، به‌جای فرض کردن، صریحاً بپرس.",
                'shape' => 'pentagon',
                'color' => 'Blue',
            ],
        ];
    }

    public function openCreateForm(): void
    {
        $this->reset(['newName', 'newDescription', 'newShape', 'newColor', 'newIsShared']);
        $this->showCreateForm = true;
    }

    public function closeCreateForm(): void
    {
        $this->showCreateForm = false;
    }

    public function applyStarterTemplate(string $name, string $description, string $shape, string $color): void
    {
        $this->showCreateForm = true;
        $this->newName = $name;
        $this->newDescription = $description;
        $this->newShape = $shape;
        $this->newColor = $color;
    }

    public function createProfile(): void
    {
        $data = $this->validate([
            'newName' => ['nullable', 'string', 'max:60'],
            'newDescription' => ['required', 'string', 'min:10', 'max:4000'],
            'newShape' => ['required', 'string', 'in:'.implode(',', array_keys(AiAssistantAvatar::SHAPES))],
            'newColor' => ['required', 'string', 'in:'.implode(',', array_keys(AiAssistantAvatar::COLORS))],
            'newIsShared' => ['boolean'],
        ]);

        $profile = AiAssistantProfile::create([
            'created_by' => Auth::id(),
            'name' => $data['newName'] !== '' ? $data['newName'] : null,
            'description' => $data['newDescription'],
            'avatar_shape' => $data['newShape'],
            'avatar_color' => $data['newColor'],
            'is_shared' => $data['newIsShared'],
        ]);

        $this->showCreateForm = false;
        $this->openProfile($profile->id);

        Notification::make()->title('دستیار «'.$profile->displayName().'» ساخته شد.')->success()->send();
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
