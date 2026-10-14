<?php

namespace App\Filament\Pages;

use App\Domain\Deployment\DeploymentTriggerService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Morilog\Jalali\Jalalian;

/**
 * Lets an admin request a production deploy (git pull + migrate + cache
 * rebuild) with one click instead of hand-editing a Cron Job's schedule
 * fields every time - this host disables SSH/shell access and PHP's own
 * proc_open, so this page only ever writes a flag file via
 * DeploymentTriggerService; a permanent Cron Job outside PHP does the
 * actual git/artisan work once it sees that file.
 */
class ProductionDeploy extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.production-deploy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'دیپلوی سرور';

    protected static ?string $title = 'دیپلوی سرور تولید';

    public static function accessControlPermission(): string
    {
        return 'production-deploy.access';
    }

    public function isDeployPending(): bool
    {
        return app(DeploymentTriggerService::class)->isPending();
    }

    public function lastDeployLog(): ?string
    {
        return app(DeploymentTriggerService::class)->lastLog();
    }

    public function lastDeployAt(): ?string
    {
        $deployedAt = app(DeploymentTriggerService::class)->lastDeployAt();

        return $deployedAt ? Jalalian::fromCarbon($deployedAt)->format('%d %B %Y ساعت %H:%M') : null;
    }

    public function requestDeploy(): void
    {
        app(DeploymentTriggerService::class)->requestDeploy();

        Notification::make()
            ->title('درخواست دیپلوی ثبت شد.')
            ->body('حداکثر تا یک دقیقه دیگر، تغییرات جدید روی سرور اعمال می‌شود.')
            ->success()
            ->send();
    }
}
