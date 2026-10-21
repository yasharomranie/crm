<?php

namespace App\Filament\Pages;

use App\Domain\Ai\AiTeamMapData;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

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

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, feed: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function mapData(): array
    {
        return app(AiTeamMapData::class)->build(Auth::user());
    }
}
