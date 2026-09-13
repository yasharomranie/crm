<?php

namespace App\Filament\Pages\Concerns;

/**
 * Gates a custom Filament page behind a single "{module}.access" permission
 * from the dynamic access-control system (config/access-control.php).
 * Pages using this trait are automatically hidden from the sidebar for any
 * user/role that lacks the permission (see Filament\Pages\Page::canAccess()
 * usage in registerNavigationItems()).
 */
trait HasAccessControlledPage
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can(static::accessControlPermission()) ?? false;
    }

    /**
     * The permission name that grants access to this page,
     * e.g. "ai-assistant.access".
     */
    abstract public static function accessControlPermission(): string;
}
