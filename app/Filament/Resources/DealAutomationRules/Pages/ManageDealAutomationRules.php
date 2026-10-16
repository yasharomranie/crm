<?php

namespace App\Filament\Resources\DealAutomationRules\Pages;

use App\Filament\Resources\DealAutomationRules\DealAutomationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDealAutomationRules extends ManageRecords
{
    protected static string $resource = DealAutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
