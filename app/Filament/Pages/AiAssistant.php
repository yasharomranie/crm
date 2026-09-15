<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Pages\Concerns\HasAiAssistantEngine;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AiAssistant extends Page
{
    use HasAccessControlledPage, HasAiAssistantEngine;

    protected string $view = 'filament.pages.ai-assistant';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'دستیار هوشمند';

    protected static ?string $title = 'دستیار هوشمند مدیریتی';

    public static function accessControlPermission(): string
    {
        return 'ai-assistant.access';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = count(static::computeAlerts());

        return $count > 0 ? (string) $count : null;
    }
}
