<?php

namespace App\Filament\Pages;

use App\Domain\Seo\WpConnector\WpFleetStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The status board of every connected WordPress site at once: which ones
 * have crashed, gone silent, block search engines or run an old plugin.
 * The facts come from App\Domain\Seo\WpConnector\WpFleetStatus.
 */
class SeoWpFleet extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-wp-fleet';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'وضعیت سایت‌های وردپرسی';

    protected static ?string $title = 'وضعیت سایت‌های وردپرسی';

    #[Url(as: 'state')]
    public ?string $state = null;

    public static function accessControlPermission(): string
    {
        return 'seo-wp-fleet.access';
    }

    /**
     * @return array{latest_version: string, counts: array<string, int>, rows: Collection<int, array<string, mixed>>}
     */
    public function overview(): array
    {
        return app(WpFleetStatus::class)->overview($this->state ?: null);
    }

    public function filterBy(?string $state): void
    {
        $this->state = $state && array_key_exists($state, WpFleetStatus::STATES) ? $state : null;
    }
}
