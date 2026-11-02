<?php

namespace App\Filament\Pages;

use App\Domain\System\ServerLogReader;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The newest errors of the application log, readable from the panel: for a
 * host without a terminal, this is the way to see why a page returned 500.
 */
class ServerErrorLog extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.server-error-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'گزارش خطاهای سرور';

    protected static ?string $title = 'گزارش خطاهای سرور';

    public bool $errorsOnly = true;

    public static function accessControlPermission(): string
    {
        return 'server-error-log.access';
    }

    public function showAll(bool $all): void
    {
        $this->errorsOnly = ! $all;
    }

    public function logFileName(): ?string
    {
        $file = app(ServerLogReader::class)->newestFile(storage_path('logs'));

        return $file ? basename($file) : null;
    }

    /**
     * @return list<array{time: string, level: string, headline: string, details: string}>
     */
    public function getEntries(): array
    {
        $reader = app(ServerLogReader::class);
        $file = $reader->newestFile(storage_path('logs'));

        return $file ? $reader->recent($file, 20, $this->errorsOnly) : [];
    }
}
