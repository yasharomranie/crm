<?php

namespace App\Filament\Resources\DigitalProjects\RelationManagers;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectWorkCategory;
use App\Models\DigitalProjectAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'اقدام‌ها';

    protected static ?string $modelLabel = 'اقدام';

    protected static ?string $pluralModelLabel = 'اقدام‌ها';

    /** The project page is a view page, where relation managers are read-only unless they say otherwise. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('اقدام')->required()->maxLength(255)->columnSpanFull(),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(DigitalProjectActionStatus::class)
                    ->default(DigitalProjectActionStatus::Open)
                    ->required(),
                DatePicker::make('due_on')->jalali()->displayFormat('Y/m/d')->label('مهلت'),
                Select::make('assigned_to')->label('مسئول')->relationship('assignee', 'name')->searchable()->preload(),
                Select::make('work_category')->label('نوع کار (برای گزارش مدیریتی)')->options(DigitalProjectWorkCategory::class)->placeholder('—')->helperText('اگر انتخاب کنید، این کار در ستون همان دسته از گزارش هفتگی شمرده می‌شود.'),
                Textarea::make('details')->label('توضیح')->rows(4)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn ($query) => $query->with('task'))
            ->columns([
                TextColumn::make('title')->label('اقدام')->wrap(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('due_on')
                    ->label('مهلت')
                    ->jalaliDate('Y/m/d')
                    ->placeholder('—')
                    ->color(fn (DigitalProjectAction $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('task.status')->label('وظیفه')->badge()->placeholder('—'),
                TextColumn::make('completed_at')->label('انجام شد')->jalaliDate('Y/m/d')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
            ])
            ->recordActions([
                Action::make('finish')
                    ->label('انجام شد')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (DigitalProjectAction $record) => $record->status !== DigitalProjectActionStatus::Done)
                    ->action(fn (DigitalProjectAction $record) => $record->update(['status' => DigitalProjectActionStatus::Done])),
                Action::make('toTask')
                    ->label('تبدیل به وظیفه')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('gray')
                    ->visible(fn (DigitalProjectAction $record) => $record->task === null
                        && $record->status !== DigitalProjectActionStatus::Done
                        && auth()->user()?->can('tasks.create'))
                    ->requiresConfirmation()
                    ->modalDescription('یک وظیفه با همین عنوان، توضیح، مسئول و مهلت در فهرست وظایف ساخته می‌شود.')
                    ->action(function (DigitalProjectAction $record): void {
                        $record->convertToTask(auth()->id());

                        Notification::make()->title('وظیفه ساخته شد.')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
