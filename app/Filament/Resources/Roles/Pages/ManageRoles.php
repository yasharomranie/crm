<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Console\Commands\SyncAccessControlPermissions;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    /**
     * A module added by a deploy has no permission rows until the sync runs,
     * and this host's deploy button does not run it; creating the missing
     * rows when the role screen opens means every module always shows its
     * checkboxes.
     */
    public function mount(): void
    {
        SyncAccessControlPermissions::createMissingPermissions();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('5xl'),
        ];
    }
}
