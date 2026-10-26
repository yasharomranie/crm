<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Enums\DigitalProjectStatus;
use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDigitalProject extends ViewRecord
{
    protected static string $resource = DigitalProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('تغییر وضعیت')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->fillForm(fn () => ['status' => $this->getRecord()->status->value])
                ->schema([
                    Select::make('status')->label('وضعیت جدید')->options(DigitalProjectStatus::class)->required(),
                    TextInput::make('note')->label('دلیل (اختیاری)')->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $project = $this->getRecord();
                    $project->statusChangeNote = filled($data['note'] ?? null) ? $data['note'] : null;
                    $project->update(['status' => $data['status']]);

                    Notification::make()->title('وضعیت پروژه ثبت شد.')->success()->send();
                }),
            Action::make('print')
                ->label('چاپ')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('digital-projects.print', $this->getRecord()), shouldOpenInNewTab: true),
            EditAction::make(),
        ];
    }
}
