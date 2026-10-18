<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printAll')
                ->label('چاپ همه‌ی بلیت‌ها')
                ->url(fn () => route('admission-orders.print', $this->record))
                ->openUrlInNewTab()
                ->color('gray'),
            OrderResource::refundUnusedAction(),
        ];
    }
}
