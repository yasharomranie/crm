<?php

namespace App\Filament\Pages;

use App\Domain\Ai\AiTeamMapData;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * A live "team map" of the AI assistants: who is on the team and how they
 * have been working together - consultations, chats handed over, and the
 * management assistant asking a specialist - drawn as a graph that the page
 * refreshes on its own. Read-only; everything comes from existing records.
 */
class AiTeamMap extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.ai-team-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'نقشهٔ تیم دستیاران';

    protected static ?string $title = 'نقشهٔ تیم دستیاران';

    public static function accessControlPermission(): string
    {
        return 'ai-team-map.access';
    }

    /** The mission the map is showing the team of; null shows every assistant. */
    #[Url(as: 'mission')]
    public ?int $missionId = null;

    public function selectMission(?int $id): void
    {
        $this->missionId = $id ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapData(): array
    {
        return app(AiTeamMapData::class)->build(Auth::user(), $this->missionId);
    }
}
